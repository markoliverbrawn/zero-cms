<?php

namespace Zero\Http\Controllers;

abstract class ApiController
{
    /**
     * Authenticate standard Header-based API Handshakes.
     * 
     * @return array Loaded user record row.
     */
    protected function authenticate(): array
    {
        $token = null;

        // 1. Resolve bearer token from Authorization header (handling Apache/Nginx web headers and CLI SERVER backups)
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
        }

        // 2. Fallback to custom X-API-Key header
        if (empty($token)) {
            $token = $headers['X-API-Key'] ?? $headers['x-api-key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
        }

        // 3. Fallback to query string parameter (for easy testing/lookups!)
        if (empty($token)) {
            $token = $_GET['api_key'] ?? '';
        }

        if (empty($token)) {
            $this->respond([
                'success' => false,
                'error' => 'Unauthorized: Missing API Key'
            ], 401);
        }

        // 4. Query user from database securely using hashed representation
        $hashedToken = hash('sha256', $token);
        $row = DB::query("SELECT * FROM users WHERE api_token = ? LIMIT 1", [$hashedToken])->fetch();
        if (!$row) {
            $this->respond([
                'success' => false,
                'error' => 'Unauthorized: Invalid API Key'
            ], 401);
        }

        // 5. Verify tenant-level scoping boundaries
        $siteId = App::getCurrentSiteId();
        if ($row['role'] !== 'super_admin' && !empty($row['site_id']) && $row['site_id'] !== $siteId) {
            $this->respond([
                'success' => false,
                'error' => 'Forbidden: API Key does not match active site tenant'
            ], 403);
        }

        return $row;
    }
/**
     * Terminate request with a standard JSON output response.
     */
    protected function respond(array $data, int $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    }
