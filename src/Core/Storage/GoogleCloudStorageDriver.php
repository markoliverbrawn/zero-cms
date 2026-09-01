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
 * private objects.
 */
class GoogleCloudStorageDriver implements StorageDriver
{
    protected string $bucketName;
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
        $token = $this->getAccessToken();

        // 1. List all objects matching the prefix
        $url = "https://storage.googleapis.com/storage/v1/b/{$this->bucketName}/o?prefix=" . \urlencode($prefix);
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
            $deleteUrl = "https://storage.googleapis.com/storage/v1/b/{$this->bucketName}/o/" . \urlencode($name);
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
        $publicUrlPrefix = "https://storage.googleapis.com/{$this->bucketName}/";
        if (\strpos($path, $publicUrlPrefix) === 0) {
            return \substr($path, \strlen($publicUrlPrefix));
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

        $url = "https://storage.googleapis.com/storage/v1/b/{$this->bucketName}/o/" . \urlencode($cleanPath);
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

        $url = "https://storage.googleapis.com/storage/v1/b/{$this->bucketName}/o/" . \urlencode($cleanPath);
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
            [$response, $status] = $this->curlWithRetry(function () use ($metadataUrl) {
                $ch = curl_init($metadataUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => ['Metadata-Flavor: Google'],
                    CURLOPT_TIMEOUT => 2
                ]);
                return $ch;
            });

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
     * Get the public URL for a GCS file path.
     *
     * @param string $path The file path.
     * @return string
     */
    public function getUrl(string $path): string
    {
        return "https://storage.googleapis.com/{$this->bucketName}/" . $this->cleanPath($path);
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
        
        $keyPath = Env::get('GCS_KEY_FILE');
        if (empty($keyPath) || !\file_exists($keyPath)) {
            throw new Exception("GCS Private Key file is required to generate Signed URLs.");
        }

        $keyData = \json_decode(\file_get_contents($keyPath), true);
        $privateKey = $keyData['private_key'] ?? null;
        $clientEmail = $keyData['client_email'] ?? null;

        if (!$privateKey || !$clientEmail) {
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
            'X-Goog-Expires' => $expires,
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
        
        $canonicalUri = "/{$this->bucketName}/{$escapedPath}";
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
        if (!openssl_sign($stringToSign, $signature, $privateKey, 'SHA256')) {
            throw new Exception("Signing failed.");
        }

        $hexSignature = \bin2hex($signature);
        return "https://storage.googleapis.com/{$this->bucketName}/{$escapedPath}?{$canonicalQueryString}&X-Goog-Signature={$hexSignature}";
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

        $isPrivate = (\strpos($cleanPath, 'storage/private/') === 0);
        $acl = $isPrivate ? 'private' : Env::get('GCS_PREDEFINED_ACL', '');
        $aclParam = !empty($acl) ? '&predefinedAcl=' . \urlencode($acl) : '';

        $url = "https://storage.googleapis.com/upload/storage/v1/b/{$this->bucketName}/o?uploadType=media{$aclParam}&name=" . \urlencode($cleanPath);
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

        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $status === 200;
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

        $url = "https://storage.googleapis.com/storage/v1/b/{$this->bucketName}/o/" . \urlencode($cleanPath) . "?alt=media";
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

        $acl = Env::get('GCS_PREDEFINED_ACL', '');
        $aclParam = !empty($acl) ? '?destinationPredefinedAcl=' . \urlencode($acl) : '';

        // GCS has no native rename. Copy to new path, then delete original.
        $copyUrl = "https://storage.googleapis.com/storage/v1/b/{$this->bucketName}/o/" . \urlencode($cleanOld) . "/copyTo/b/{$this->bucketName}/o/" . \urlencode($cleanNew) . $aclParam;
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

        $isPrivate = (\strpos($cleanPath, 'storage/private/') === 0);
        $acl = $isPrivate ? 'private' : Env::get('GCS_PREDEFINED_ACL', '');
        $aclParam = !empty($acl) ? '&predefinedAcl=' . \urlencode($acl) : '';

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

        $url = "https://storage.googleapis.com/upload/storage/v1/b/{$this->bucketName}/o?uploadType=media{$aclParam}&name=" . \urlencode($cleanPath);
        [$response, $status, $curlError] = $this->curlWithRetry(function () use ($url, $token, $mime, $content) {
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

        if ($status === 200) {
            return true;
        }

        \error_log("GoogleCloudStorageDriver::write() failed for '{$cleanPath}': " . self::describeGcsFailure($status, $response, $curlError));
        return false;
    }

    /**
     * Execute a curl request with transient-failure retry: HTTP 429/5xx, or a failed transfer
     * (curl_exec() returning false), are retried up to $maxAttempts total with exponential
     * backoff; any other response (including any other 4xx) returns immediately on the first
     * attempt, since retrying a malformed/unauthorized request can't change the outcome.
     *
     * @param callable $buildRequest () => resource|\CurlHandle  Builds and returns a fresh,
     *                  fully-configured curl handle for one attempt -- a new handle each time,
     *                  since a handle that already ran can't be safely re-executed.
     * @param int $maxAttempts Total attempts including the first, before giving up.
     * @param int $baseDelayMs Delay before the first retry; doubles on each subsequent retry.
     * @return array{0: string|false, 1: int, 2: string} [$response, $httpStatus, $curlError] from
     *                  the final attempt, whichever one that was.
     */
    private function curlWithRetry(callable $buildRequest, int $maxAttempts = 3, int $baseDelayMs = 250): array
    {
        $delayMs = $baseDelayMs;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $ch = $buildRequest();
            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $isTransient = ($response === false) || $status === 429 || $status >= 500;
            if (!$isTransient || $attempt === $maxAttempts) {
                return [$response, $status, $curlError];
            }

            \usleep($delayMs * 1000);
            $delayMs *= 2;
        }

        // Unreachable (the loop above always returns by its last iteration), kept for static analysis.
        return [false, 0, ''];
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
