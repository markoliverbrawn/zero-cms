<?php
/**
 * File: src/Core/Storage/AwsS3StorageDriver.php
 * Architectural Purpose: Core bootstrapping, system environment configuration, and utility class of the framework.
 * Package: Zero\Core\Storage
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Core\Storage;

use Zero\Core\Env;
use Exception;

/**
 * Class AwsS3StorageDriver
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class AwsS3StorageDriver implements StorageDriver
{
    protected string $bucketName;
    protected string $accessKey;
    protected string $secretKey;
    protected string $region;

    /**
     * __construct processing implementation helper.
     *
     * @param ?array $config Argument descriptor.
     * @return mixed Response output.
     */
    public function __construct(?array $config = null)
    {
        $this->bucketName = $config['bucket'] ?? Env::get('AWS_S3_BUCKET', '');
        $this->accessKey = $config['access_key'] ?? Env::get('AWS_ACCESS_KEY_ID', '');
        $this->secretKey = $config['secret_access_key'] ?? Env::get('AWS_SECRET_ACCESS_KEY', '');
        $this->region = $config['region'] ?? Env::get('AWS_DEFAULT_REGION', 'us-east-1');
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
        $prefix = rtrim($cleanPath, '/') . '/';

        // 1. List all keys matching the prefix via S3 ListObjectsV2 REST API
        $query = 'list-type=2&prefix=' . urlencode($prefix);
        $response = $this->sendRequest('GET', '', '', [], $query);

        if ($response['status'] !== 200) {
            return false;
        }

        // 2. Extract keys using zero-dependency XML regex parsing
        preg_match_all('/<Key>([^<]+)<\/Key>/i', $response['body'], $matches);
        $keys = $matches[1] ?? [];

        // 3. Delete each key sequentially
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * Strip leading local paths to keep the cloud layout clean.
     */
    protected function cleanPath(string $path): string
    {
        if (strpos($path, APPLICATION_ROOT) === 0) {
            $path = substr($path, strlen(APPLICATION_ROOT));
        }
        $path = ltrim($path, '/');
        if (strpos($path, 'public/') === 0) {
            $path = substr($path, 7);
        }
        return ltrim($path, '/');
    }

    /**
     * Delete a file from AWS S3.
     *
     * @param string $path The file path.
     * @return bool
     */
    public function delete(string $path): bool
    {
        $cleanPath = $this->cleanPath($path);
        $response = $this->sendRequest('DELETE', $cleanPath);

        // S3 returns 204 No Content on successful deletes
        return $response['status'] === 204 || $response['status'] === 200;
    }

    /**
     * Check if a file exists on AWS S3.
     *
     * @param string $path The file path.
     * @return bool
     */
    public function exists(string $path): bool
    {
        $cleanPath = $this->cleanPath($path);
        $response = $this->sendRequest('HEAD', $cleanPath);

        return $response['status'] === 200;
    }

    /**
     * Resolve file mime-types dynamically for headers injection.
     */
    protected function getMimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'txt' => 'text/plain',
            'pdf' => 'application/pdf',
            'json' => 'application/json',
            'zip' => 'application/zip',
            'mp4' => 'video/mp4'
        ];
        return $mimes[$ext] ?? 'application/octet-stream';
    }

    /**
     * Get the public URL for a given file path on AWS S3.
     *
     * @param string $path The file path.
     * @return string
     */
    public function getUrl(string $path): string
    {
        $cleanPath = $this->cleanPath($path);
        return "https://{$this->bucketName}.s3.{$this->region}.amazonaws.com/{$cleanPath}";
    }

    /**
     * Get a secure, temporary signed URL for a private file in AWS S3.
     *
     * @param string $path The file path.
     * @param int $expires The expiry time in seconds.
     * @return string
     */
    public function getSignedUrl(string $path, int $expires = 3600): string
    {
        $cleanPath = $this->cleanPath($path);
        
        $now = time();
        $amzDate = gmdate('Ymd\THis\Z', $now);
        $date = gmdate('Ymd', $now);
        $region = $this->region;
        $scope = "{$date}/{$region}/s3/aws4_request";
        
        $params = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => "{$this->accessKey}/{$scope}",
            'X-Amz-Date' => $amzDate,
            'X-Amz-Expires' => $expires,
            'X-Amz-SignedHeaders' => 'host',
        ];
        
        ksort($params);
        $queryParamsList = [];
        foreach ($params as $k => $v) {
            $queryParamsList[] = urlencode($k) . '=' . urlencode($v);
        }
        $canonicalQueryString = implode('&', $queryParamsList);
        
        $host = "{$this->bucketName}.s3.{$region}.amazonaws.com";
        $escapedPath = '';
        foreach (explode('/', $cleanPath) as $part) {
            $escapedPath .= '/' . rawurlencode($part);
        }
        $escapedPath = ltrim($escapedPath, '/');
        
        $canonicalUri = "/{$escapedPath}";
        $canonicalHeaders = "host:{$host}\n";
        $signedHeaders = "host";
        $payloadHash = "UNSIGNED-PAYLOAD";
        
        $canonicalRequest = "GET\n" .
            $canonicalUri . "\n" .
            $canonicalQueryString . "\n" .
            $canonicalHeaders . "\n" .
            $signedHeaders . "\n" .
            $payloadHash;
            
        $stringToSign = "AWS4-HMAC-SHA256\n" .
            $amzDate . "\n" .
            $scope . "\n" .
            hash('sha256', $canonicalRequest);
            
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        
        return "https://{$host}/{$escapedPath}?{$canonicalQueryString}&X-Amz-Signature={$signature}";
    }

    /**
     * Virtual directory creation for S3 flat namespaces.
     *
     * @param string $path The directory path.
     * @return bool
     */
    public function makeDirectory(string $path): bool
    {
        $cleanPath = rtrim($this->cleanPath($path), '/') . '/';
        // Create an empty virtual folder placeholder object
        return $this->write($cleanPath, '');
    }

    /**
     * Store an uploaded file.
     *
     * @param string $path The destination path.
     * @param string $tmpFilePath The temporary file path.
     * @return bool
     */
    public function putFile(string $path, string $tmpFilePath): bool
    {
        if (!file_exists($tmpFilePath)) {
            return false;
        }
        $content = file_get_contents($tmpFilePath);
        return $this->write($path, $content);
    }

    /**
     * Rename or move a file or directory.
     *
     * @param string $oldPath The original path.
     * @param string $newPath The target path.
     * @return bool
     */
    public function rename(string $oldPath, string $newPath): bool
    {
        $cleanOld = $this->cleanPath($oldPath);
        $cleanNew = $this->cleanPath($newPath);

        // 1. Copy object to new path via PUT Copy request
        $copySource = '/' . $this->bucketName . '/' . $cleanOld;
        $response = $this->sendRequest('PUT', $cleanNew, '', [
            'x-amz-copy-source' => $copySource
        ]);

        if ($response['status'] !== 200) {
            return false;
        }

        // 2. Delete the old original object
        return $this->delete($cleanOld);
    }

    /**
     * Direct AWS SigV4 signed HTTP REST API transceiver.
     */
    protected function sendRequest(string $method, string $path, string $payload = '', array $headers = [], string $queryString = ''): array
    {
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');
        $service = 's3';
        
        $host = "{$this->bucketName}.s3.{$this->region}.amazonaws.com";
        $endpoint = "https://{$host}/" . $path;
        if ($queryString !== '') {
            $endpoint .= '?' . $queryString;
        }

        $payloadHash = hash('sha256', $payload);

        // Core AWS S3 Mandatory Headers
        $headers['Host'] = $host;
        $headers['x-amz-content-sha256'] = $payloadHash;
        $headers['x-amz-date'] = $amzDate;

        // Sort header keys alphabetically for Signature matching
        uksort($headers, 'strcasecmp');

        // 1. Build Canonical Request
        $canonicalUri = '/' . str_replace('%2F', '/', rawurlencode($path));
        
        $canonicalHeaders = '';
        $signedHeadersList = [];
        foreach ($headers as $k => $v) {
            $lowerKey = strtolower($k);
            $canonicalHeaders .= $lowerKey . ':' . trim($v) . "\n";
            $signedHeadersList[] = $lowerKey;
        }
        $signedHeaders = implode(';', $signedHeadersList);

        $canonicalRequest = implode("\n", [
            $method,
            $canonicalUri,
            $queryString,
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash
        ]);

        // 2. Build String to Sign
        $credentialScope = "{$dateStamp}/{$this->region}/{$service}/aws4_request";
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest)
        ]);

        // 3. Generate Cryptographic AWS SigV4 Signing Key
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        // 4. Calculate Signature
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        // 5. Compile Authorization Header
        $headers['Authorization'] = "AWS4-HMAC-SHA256 Credential={$this->accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        // Format curl headers array
        $curlHeaders = [];
        foreach ($headers as $k => $v) {
            $curlHeaders[] = "{$k}: {$v}";
        }

        // 6. Execute direct curl payload transceiver
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        if ($method === 'PUT' || $method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $status,
            'body' => $body
        ];
    }

    /**
     * Write raw content to a file on AWS S3.
     *
     * @param string $path The destination path.
     * @param string $content The file content.
     * @return bool
     */
    public function write(string $path, string $content): bool
    {
        $cleanPath = $this->cleanPath($path);
        $mime = $this->getMimeType($cleanPath);

        $response = $this->sendRequest('PUT', $cleanPath, $content, [
            'Content-Type' => $mime
        ]);

        return $response['status'] === 200;
    }
}
