<?php

declare(strict_types=1);

/**
 * File: src/Http/Controllers/ApiController.php
 * Architectural Purpose: HTTP request routing, request filtering middleware, or dynamic content-security controllers.
 * Package: Zero\Http\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Http\Controllers;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Support\Logger;
use Zero\Support\Security;

/**
 * Class ApiController
 *
 * Abstract base for REST API controllers. Authenticates a caller by token -- an Authorization
 * Bearer header, an X-API-Key header, or an api_key query parameter -- rejecting a token whose
 * owning user falls outside the requested tenant, and hands subclasses a JSON response writer.
 */
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
        $headers = \function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (\str_starts_with($authHeader, 'Bearer ')) {
            $token = \substr($authHeader, 7);
        }

        // 2. Fallback to custom X-API-Key header
        if (empty($token)) {
            $token = $headers['X-API-Key'] ?? $headers['x-api-key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
        }

        // Keys are accepted from headers only. A key in the query string ends up in access logs,
        // proxy logs and Referer headers.

        if (empty($token)) {
            $this->respond([
                'success' => false,
                'error' => 'Unauthorized: Missing API Key'
            ], 401);
        }

        // Throttle per client IP (Security::rateLimit buckets by IP and site). Keying on the
        // submitted token as well gave every guessed token a fresh bucket, so guessing was never
        // throttled.
        App::applyRateLimitMiddleware('api', 1); // at most 1 request per second per IP

        // 4. Query user from database securely using hashed representation
        $hashedToken = \hash('sha256', $token);
        $row = DB::query("SELECT * FROM users WHERE api_token = ? AND deleted_at IS NULL LIMIT 1", [$hashedToken])->fetch();
        if (!$row) {
            $this->respond([
                'success' => false,
                'error' => 'Unauthorized: Invalid API Key'
            ], 401);
        }

        // 5. Verify tenant-level scoping boundaries
        $siteId = App::getCurrentSiteId();
        // Same rule as a session (BootstrapsApp): only super_admin may act across sites; a global
        // (site_id NULL) key belonging to any other role is not valid on any site.
        if (!($row['role'] === 'super_admin' || ($row['site_id'] !== null && $row['site_id'] === $siteId))) {
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
        \http_response_code($statusCode);
        \header('Content-Type: application/json; charset=utf-8');
        echo \json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
