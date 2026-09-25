<?php

declare(strict_types=1);

/**
 * File: src/Core/Storage/GoogleCloudStorageDriver.php
 * Architectural Purpose: Core bootstrapping, system environment configuration, and utility class of the framework.
 * Package: Zero\Core\Storage
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Core\Storage;

use Exception;
use Zero\Core\Env;

/**
 * Class GoogleCloudStorageDriver
 *
 * StorageDriver for Google Cloud Storage. Exchanges an RS256-signed service-account JWT for an
 * OAuth2 access token over raw cURL rather than using a vendor SDK, and issues signed URLs for
 * private objects. Without a key file it falls back to the platform's workload identity: the
 * metadata server for access tokens, and the IAM Credentials signBlob API for signed URLs.
 *
 * Objects under storage/private/ go to a separate bucket when GCS_PRIVATE_BUCKET_NAME is set. A
 * bucket with uniform bucket-level access has no per-object ACLs, so a private object can only be
 * kept out of a publicly readable media bucket by storing it in a bucket with no public grant.
 */
class GoogleCloudStorageDriver implements StorageDriver
{
    protected string $bucketName;
    protected string $privateBucketName;
    protected ?string $accessToken = null;
    protected ?int $tokenExpiresAt = null;

    /**
     * __construct processing implementation helper.
     *
     * @return mixed Response output.
     */
    public function __construct()
    {
        $this->bucketName = Env::get('GCS_BUCKET_NAME', Env::get('GCS_BUCKET', ''));
        $this->privateBucketName = (string)Env::get('GCS_PRIVATE_BUCKET_NAME', '');
    }

    /**
     * Resolve which bucket holds an object. Paths under storage/private/ live in the private bucket
     * when one is configured; otherwise (the legacy layout) every object shares the main bucket and
     * private ones rely on a per-object ACL, which only a fine-grained bucket supports.
     *
     * @param string $cleanPath The object key, already passed through cleanPath().
     * @return string The bucket name.
     */
    protected function bucketFor(string $cleanPath): string
    {
        return ($this->privateBucketName !== '' && $this->isPrivatePath($cleanPath))
            ? $this->privateBucketName
            : $this->bucketName;
    }

    /**
     * Clean all contents inside a directory.
     *
     * @param string $path The directory path.
     * @return bool
     */
    public function cleanDirectory(string $path): bool
    {
        $cleanPath = $this->cleanPath($path);
        $prefix = \rtrim($cleanPath, '/') . '/';
        $bucket = $this->bucketFor($prefix);
        $token = $this->getAccessToken();

        // 1. List all objects matching the prefix
        $url = "https://storage.googleapis.com/storage/v1/b/{$bucket}/o?prefix=" . \urlencode($prefix);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"]
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            return false;
        }

        $data = \json_decode($response, true);
        $items = $data['items'] ?? [];

        // 2. Sequentially delete each object
        foreach ($items as $item) {
            $name = $item['name'];
            $deleteUrl = "https://storage.googleapis.com/storage/v1/b/{$bucket}/o/" . \urlencode($name);
            $delCh = curl_init($deleteUrl);
            curl_setopt_array($delCh, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"]
            ]);
            curl_exec($delCh);
            curl_close($delCh);
        }

        return true;
    }

    /**
     * Strip leading local paths to keep the cloud layout clean.
     *
     * media.path is populated from this driver's own getUrl() output (see
     * FileManagerService::uploadFile()), so every read()/exists()/delete()/rename() call this
     * driver receives back from the rest of the app is that full public URL, not a bare object
     * key. Without unwrapping it back to an object key here first, every one of those calls
     * builds its GCS API request against the literal URL string and 404s -- silently, since
     * read() treats a 404 as "not found" rather than an error -- which is what breaks on-demand
     * image variant generation (MediaVariantController re-reads the original via Storage::read())
     * and dimension probing (FileManagerService::probeDimensions()) under STORAGE_DRIVER=gcs.
     */
    protected function cleanPath(string $path): string
    {
        foreach (\array_filter([$this->bucketName, $this->privateBucketName]) as $bucket) {
            $bucketUrlPrefix = "https://storage.googleapis.com/{$bucket}/";
            if (\strpos($path, $bucketUrlPrefix) === 0) {
                return \substr($path, \strlen($bucketUrlPrefix));
            }
        }

        if (\strpos($path, Storage::getRoot()) === 0) {
            $path = \substr($path, \strlen(Storage::getRoot()));
        }
        $path = \ltrim($path, '/');
        if (\strpos($path, 'public/') === 0) {
            $path = \substr($path, 7);
        }
        return \ltrim($path, '/');
    }

    /**
     * Delete a file from GCS.
     *
     * @param string $path The file path.
     * @return bool
     */
    public function delete(string $path): bool
    {
        $cleanPath = $this->cleanPath($path);
        $token = $this->getAccessToken();

        $url = "https://storage.googleapis.com/storage/v1/b/{$this->bucketFor($cleanPath)}/o/" . \urlencode($cleanPath);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"]
        ]);

        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $status === 204;
    }

    /**
     * Check if a file exists on GCS.
     *
     * @param string $path The file path.
     * @return bool
     */
    public function exists(string $path): bool
    {
        $cleanPath = $this->cleanPath($path);
        $token = $this->getAccessToken();

        $url = "https://storage.googleapis.com/storage/v1/b/{$this->bucketFor($cleanPath)}/o/" . \urlencode($cleanPath);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"]
        ]);

        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $status === 200;
    }

    /**
     * Authenticates with Google API using JWT or fetches from Metadata Server on Cloud Run.
     */
    protected function getAccessToken(): string
    {
        if ($this->accessToken && $this->tokenExpiresAt > \time()) {
            return $this->accessToken;
        }

        $keyPath = Env::get('GCS_KEY_FILE');
        if (empty($keyPath) || !\file_exists($keyPath)) {
            // Fallback: Fetch JWT-less OAuth2 Access Token from the Google Metadata Server natively on Cloud Run / GCP!
            $metadataUrl = 'http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token';
            $result = CurlRetrier::execute(function () use ($metadataUrl) {
                $ch = curl_init($metadataUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['Metadata-Flavor: Google'],
                    CURLOPT_TIMEOUT => 2
                ]);
                return $ch;
            });
            $status = $result['status'];
            $response = $result['body'];

            if ($status === 200 && !empty($response)) {
                $data = \json_decode($response, true);
                if (isset($data['access_token'])) {
                    $this->accessToken = $data['access_token'];
                    $this->tokenExpiresAt = \time() + \intval($data['expires_in'] ?? 3500) - 60; // 60s buffer
                    return $this->accessToken;
                }
            }

            throw new Exception("GCS Key File is missing/not found, and Google Metadata Server token resolution failed.");
        }

        $keyData = \json_decode(\file_get_contents($keyPath), true);
        $privateKey = $keyData['private_key'] ?? null;
        $clientEmail = $keyData['client_email'] ?? null;

        if (!$privateKey || !$clientEmail) {
            throw new Exception("Malformed Google Service Account Key JSON.");
        }

        // 1. Construct JWT Header and Claims
        $now = \time();
        $header = \rtrim(\strtr(\base64_encode(\json_encode(['alg' => 'RS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
        $claimSet = \rtrim(\strtr(\base64_encode(\json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/devstorage.full_control',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ])), '+/', '-_'), '=');

        // 2. Sign JWT using native OpenSSL RSA-SHA256
        $assertionInput = "{$header}.{$claimSet}";
        $signature = '';
        if (!openssl_sign($assertionInput, $signature, $privateKey, 'SHA256')) {
            throw new Exception("OpenSSL JWT Signing failed.");
        }
        $encodedSignature = \rtrim(\strtr(\base64_encode($signature), '+/', '-_'), '=');
        $jwt = "{$assertionInput}.{$encodedSignature}";

        // 3. Exchange JWT for OAuth2 Access Token via cURL
        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => \http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            throw new Exception("Google OAuth token exchange failed: " . $response);
        }

        $tokenData = \json_decode($response, true);
        $this->accessToken = $tokenData['access_token'];
        $this->tokenExpiresAt = \time() + \intval($tokenData['expires_in']) - 60; // 60s buffer

        return $this->accessToken;
    }

    /**
     * Resolve the runtime service account's email from the metadata server, for signing URLs when
     * no key file is configured.
     *
     * @return string The service account email.
     * @throws Exception If the metadata server is unreachable or returns no email.
     */
    protected function getServiceAccountEmail(): string
    {
        $metadataUrl = 'http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/email';
        $result = CurlRetrier::execute(function () use ($metadataUrl) {
            $ch = curl_init($metadataUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Metadata-Flavor: Google'],
                CURLOPT_TIMEOUT => 2
            ]);
            return $ch;
        });

        $email = \trim((string)$result['body']);
        if ($result['status'] !== 200 || $email === '') {
            throw new Exception("Signed URLs need a GCS_KEY_FILE or a runtime service account, and the metadata server returned no service account email (" . self::describeGcsFailure($result['status'], $result['body'], $result['error']) . ").");
        }

        return $email;
    }

    /**
     * Get a secure, temporary signed URL for a private file in GCS.
     *
     * @param string $path The file path.
     * @param int $expires The expiry time in seconds.
     * @return string
     */
    public function getSignedUrl(string $path, int $expires = 3600): string
    {
        $cleanPath = $this->cleanPath($path);
        $bucket = $this->bucketFor($cleanPath);

        // With a key file, sign locally; without one, sign as the runtime service account through
        // the IAM Credentials API (workload identity exposes no private key to sign with).
        $keyData = $this->readKeyFile();
        $privateKey = $keyData['private_key'] ?? null;
        $clientEmail = $keyData !== null ? ($keyData['client_email'] ?? null) : $this->getServiceAccountEmail();

        if ($keyData !== null && (!$privateKey || !$clientEmail)) {
            throw new Exception("Malformed Google Service Account Key JSON.");
        }

        $now = \time();
        $datetime = \gmdate('Ymd\THis\Z', $now);
        $date = \gmdate('Ymd', $now);
        $scope = "{$date}/auto/storage/goog4_request";

        $params = [
            'X-Goog-Algorithm' => 'GOOG4-RSA-SHA256',
            'X-Goog-Credential' => "{$clientEmail}/{$scope}",
            'X-Goog-Date' => $datetime,
            'X-Goog-Expires' => (string)$expires,
            'X-Goog-SignedHeaders' => 'host',
        ];

        \ksort($params);
        $queryParamsList = [];
        foreach ($params as $k => $v) {
            $queryParamsList[] = \urlencode($k) . '=' . \urlencode($v);
        }
        $canonicalQueryString = \implode('&', $queryParamsList);

        $escapedPath = '';
        foreach (\explode('/', $cleanPath) as $part) {
            $escapedPath .= '/' . \rawurlencode($part);
        }
        $escapedPath = \ltrim($escapedPath, '/');
        
        $canonicalUri = "/{$bucket}/{$escapedPath}";
        $canonicalHeaders = "host:storage.googleapis.com\n";
        $signedHeaders = "host";
        $payloadHash = "UNSIGNED-PAYLOAD";

        $canonicalRequest = "GET\n" .
            $canonicalUri . "\n" .
            $canonicalQueryString . "\n" .
            $canonicalHeaders . "\n" .
            $signedHeaders . "\n" .
            $payloadHash;

        $stringToSign = "GOOG4-RSA-SHA256\n" .
            $datetime . "\n" .
            $scope . "\n" .
            \hash('sha256', $canonicalRequest);

        $signature = '';
        if ($privateKey !== null) {
            if (!openssl_sign($stringToSign, $signature, $privateKey, 'SHA256')) {
                throw new Exception("Signing failed.");
            }
        } else {
            $signature = $this->signBlob($clientEmail, $stringToSign);
        }

        $hexSignature = \bin2hex($signature);
        return "https://storage.googleapis.com/{$bucket}/{$escapedPath}?{$canonicalQueryString}&X-Goog-Signature={$hexSignature}";
    }

    /**
     * Get the public URL for a GCS file path.
     *
     * @param string $path The file path.
     * @return string
     */
    public function getUrl(string $path): string
    {
        $cleanPath = $this->cleanPath($path);
        return "https://storage.googleapis.com/{$this->bucketFor($cleanPath)}/" . $cleanPath;
    }

    /**
     * Whether an object key belongs to the private storage area.
     *
     * @param string $cleanPath The object key, already passed through cleanPath().
     * @return bool
     */
    protected function isPrivatePath(string $cleanPath): bool
    {
        return \strpos($cleanPath, 'storage/private/') === 0;
    }

    /**
     * Create virtual directory.
     *
     * @param string $path The directory path.
     * @return bool
     */
    public function makeDirectory(string $path): bool
    {
        return true;
    }

    /**
     * The predefinedAcl to send for an object, or '' to send none. A private object in the private
     * bucket needs no ACL, and a uniform-access bucket rejects one outright ("Cannot insert legacy
     * ACL"). Only the legacy single-bucket layout still marks private objects with an ACL.
     *
     * @param string $cleanPath The object key, already passed through cleanPath().
     * @return string
     */
    protected function predefinedAclFor(string $cleanPath): string
    {
        if ($this->isPrivatePath($cleanPath)) {
            return $this->privateBucketName !== '' ? '' : 'private';
        }
        return (string)Env::get('GCS_PREDEFINED_ACL', '');
    }

    /**
     * A hint appended to write failure logs for private objects stored the legacy way (in the main
     * bucket, marked with an ACL), which is what fails on a uniform bucket-level access bucket.
     *
     * @param string $cleanPath The object key, already passed through cleanPath().
     * @return string The hint, or '' when it doesn't apply.
     */
    protected function privateLayoutHint(string $cleanPath): string
    {
        if (!$this->isPrivatePath($cleanPath) || $this->privateBucketName !== '') {
            return '';
        }
        return ' (private objects are being stored in the main bucket with a per-object ACL, which a uniform bucket-level access bucket rejects; set GCS_PRIVATE_BUCKET_NAME to a bucket with no public access)';
    }

    /**
     * Upload an uploaded file to GCS.
     *
     * @param string $path The destination path.
     * @param string $tmpFilePath The temporary file path.
     * @return bool
     */
    public function putFile(string $path, string $tmpFilePath): bool
    {
        $cleanPath = $this->cleanPath($path);
        $token = $this->getAccessToken();
        $mime = \mime_content_type($tmpFilePath) ?: 'application/octet-stream';

        $acl = $this->predefinedAclFor($cleanPath);
        $aclParam = $acl !== '' ? '&predefinedAcl=' . \urlencode($acl) : '';

        $url = "https://storage.googleapis.com/upload/storage/v1/b/{$this->bucketFor($cleanPath)}/o?uploadType=media{$aclParam}&name=" . \urlencode($cleanPath);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => \file_get_contents($tmpFilePath),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$token}",
                "Content-Type: {$mime}",
                "Content-Length: " . \filesize($tmpFilePath)
            ]
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            \error_log("GoogleCloudStorageDriver::putFile() failed for '{$cleanPath}': " . self::describeGcsFailure($status, $response, '') . $this->privateLayoutHint($cleanPath));
            return false;
        }
        return true;
    }

    /**
     * Read the raw bytes of an object out of GCS via an authenticated media download.
     *
     * @param string $path The file path.
     * @return string|null The object contents, or null when the object does not exist.
     * @throws Exception If the bucket responds with an unexpected status.
     */
    public function read(string $path): ?string
    {
        $cleanPath = $this->cleanPath($path);
        $token = $this->getAccessToken();

        $url = "https://storage.googleapis.com/storage/v1/b/{$this->bucketFor($cleanPath)}/o/" . \urlencode($cleanPath) . "?alt=media";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"],
            CURLOPT_TIMEOUT => 20
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status === 404) {
            return null;
        }
        if ($status !== 200 || $body === false) {
            throw new Exception("GCS read failed with HTTP status {$status} for object: {$cleanPath}");
        }

        return (string)$body;
    }

    /**
     * Load the service account key file named by GCS_KEY_FILE, if one is configured and present.
     *
     * @return array|null The decoded key, or null when there's no key file (workload identity).
     */
    protected function readKeyFile(): ?array
    {
        $keyPath = Env::get('GCS_KEY_FILE');
        if (empty($keyPath) || !\file_exists($keyPath)) {
            return null;
        }
        $keyData = \json_decode((string)\file_get_contents($keyPath), true);
        return \is_array($keyData) ? $keyData : [];
    }

    /**
     * Rename/move a file on GCS.
     *
     * @param string $oldPath The original path.
     * @param string $newPath The target path.
     * @return bool
     */
    public function rename(string $oldPath, string $newPath): bool
    {
        $cleanOld = $this->cleanPath($oldPath);
        $cleanNew = $this->cleanPath($newPath);
        $token = $this->getAccessToken();

        $acl = $this->predefinedAclFor($cleanNew);
        $aclParam = $acl !== '' ? '?destinationPredefinedAcl=' . \urlencode($acl) : '';

        // GCS has no native rename. Copy to new path (possibly across the public/private buckets),
        // then delete original.
        $copyUrl = "https://storage.googleapis.com/storage/v1/b/{$this->bucketFor($cleanOld)}/o/" . \urlencode($cleanOld) . "/copyTo/b/{$this->bucketFor($cleanNew)}/o/" . \urlencode($cleanNew) . $aclParam;
        $ch = curl_init($copyUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"]
        ]);

        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status === 200) {
            return $this->delete($oldPath);
        }

        return false;
    }

    /**
     * Sign bytes as a service account through the IAM Credentials signBlob API, for signed URLs
     * when no key file is available. Requires iamcredentials.googleapis.com to be enabled and the
     * account to hold roles/iam.serviceAccountTokenCreator on itself.
     *
     * @param string $serviceAccountEmail The account to sign as.
     * @param string $data The bytes to sign.
     * @return string The raw RSA-SHA256 signature.
     * @throws Exception If the API call fails or returns no signature.
     */
    protected function signBlob(string $serviceAccountEmail, string $data): string
    {
        $token = $this->getAccessToken();
        $url = 'https://iamcredentials.googleapis.com/v1/projects/-/serviceAccounts/' . \rawurlencode($serviceAccountEmail) . ':signBlob';
        $payload = (string)\json_encode(['payload' => \base64_encode($data)]);

        $result = CurlRetrier::execute(function () use ($url, $token, $payload) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer {$token}",
                    'Content-Type: application/json'
                ]
            ]);
            return $ch;
        });

        if ($result['status'] !== 200) {
            throw new Exception("Signing a URL as {$serviceAccountEmail} through the IAM Credentials API failed: " . self::describeGcsFailure($result['status'], $result['body'], $result['error']) . ". The account needs roles/iam.serviceAccountTokenCreator on itself, and iamcredentials.googleapis.com must be enabled.");
        }

        $decoded = \json_decode((string)$result['body'], true);
        $signature = \base64_decode((string)($decoded['signedBlob'] ?? ''), true);
        if ($signature === false || $signature === '') {
            throw new Exception("The IAM Credentials API returned no signature when signing a URL as {$serviceAccountEmail}.");
        }

        return $signature;
    }

    /**
     * Write raw text or binary content to GCS with proper Content-Type.
     *
     * Retries transient failures (HTTP 429, 5xx, or a failed transfer) with exponential backoff --
     * a single overloaded bucket or momentary network blip previously surfaced as a bare `false`,
     * which callers treat as fatal (e.g. aborting an entire multi-tenant reseed on what was really
     * a one-off hiccup). Non-transient failures (any other 4xx) fail immediately since a retry
     * can't fix a bad request/auth/permission error.
     *
     * @param string $path The destination path.
     * @param string $content The text or binary content.
     * @return bool
     */
    public function write(string $path, string $content): bool
    {
        $cleanPath = $this->cleanPath($path);
        $token = $this->getAccessToken();

        $acl = $this->predefinedAclFor($cleanPath);
        $aclParam = $acl !== '' ? '&predefinedAcl=' . \urlencode($acl) : '';

        $ext = \strtolower(\pathinfo($cleanPath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'zip' => 'application/zip',
            'json' => 'application/json',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'html' => 'text/html',
            'mp4' => 'video/mp4',
        ];
        $mime = $mimeTypes[$ext] ?? 'text/plain';

        $url = "https://storage.googleapis.com/upload/storage/v1/b/{$this->bucketFor($cleanPath)}/o?uploadType=media{$aclParam}&name=" . \urlencode($cleanPath);
        $result = CurlRetrier::execute(function () use ($url, $token, $mime, $content) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $content,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer {$token}",
                    "Content-Type: {$mime}",
                    "Content-Length: " . \strlen($content)
                ]
            ]);
            return $ch;
        });

        if ($result['status'] === 200) {
            return true;
        }

        \error_log("GoogleCloudStorageDriver::write() failed for '{$cleanPath}': " . self::describeGcsFailure($result['status'], $result['body'], $result['error']) . $this->privateLayoutHint($cleanPath));
        return false;
    }

    /**
     * Render a failed GCS response into a diagnosable message: GCS error responses are JSON with
     * a useful `error.message`, so surface that directly rather than a raw status code; fall back
     * to the curl transport error when the transfer itself never completed (empty/false response).
     */
    private static function describeGcsFailure(int $status, $response, string $curlError): string
    {
        if ($response === false || $response === '') {
            return $curlError !== ''
                ? "curl transport error: {$curlError}"
                : "empty response (HTTP status {$status})";
        }

        $decoded = \json_decode((string)$response, true);
        $message = $decoded['error']['message'] ?? null;
        if ($message !== null) {
            return "HTTP {$status}: {$message}";
        }

        return "HTTP {$status}: " . \substr((string)$response, 0, 500);
    }
}
