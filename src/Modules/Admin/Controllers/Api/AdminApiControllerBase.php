<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Controllers/Api/AdminApiControllerBase.php
 * Architectural Purpose: Shared session+CSRF authentication for every back-office AJAX/API
 * controller under Zero\Modules\Admin\Controllers\Api, used in place of the base ApiController's
 * external API-key authentication (which back-office session-driven requests don't carry).
 * Package: Zero\Modules\Admin\Controllers\Api
 */

namespace Zero\Modules\Admin\Controllers\Api;

use Zero\Database\DB;
use Zero\Http\Controllers\ApiController;
use Zero\Support\Permissions;
use Zero\Support\Security;

/**
 * Class AdminApiControllerBase
 */
abstract class AdminApiControllerBase extends ApiController
{
    /**
     * Override authenticate() to enforce session-based and CSRF protection for back-office AJAX calls.
     */
    protected function authenticate(): array
    {
        if (\session_status() === PHP_SESSION_NONE) {
            \session_start();
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            $this->respond([
                'success' => false,
                'error' => 'Unauthorized: Session expired or invalid'
            ], 401);
        }

        $user = DB::query("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1", [$userId])->fetch();
        if (!$user) {
            $this->respond([
                'success' => false,
                'error' => 'Unauthorized: User not found'
            ], 401);
        }

        // Every back-office API is staff-only: a signed-in member (e.g. a self-registered shop
        // customer) has a session too, but must not reach any of these endpoints.
        if (!Permissions::roleHas((string) ($user['role'] ?? ''), 'backoffice.access')) {
            $this->respond([
                'success' => false,
                'error' => 'Forbidden: Back-office access required'
            ], 403);
        }

        // Apply CSRF validation for state-modifying requests (POST, PUT, PATCH, DELETE)
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (\in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            // Check HTTP headers first, then fallback to JSON body or POST variables
            $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '';

            if (empty($csrfToken)) {
                $rawBody = \file_get_contents('php://input');
                $jsonData = \json_decode($rawBody, true);
                $csrfToken = $jsonData['csrf'] ?? '';
            }

            if (empty($csrfToken) || !Security::csrfVerify($csrfToken)) {
                $this->respond([
                    'success' => false,
                    'error' => 'Forbidden: Invalid CSRF Token'
                ], 403);
            }
        }

        return $user;
    }

    /**
     * Parse the incoming request body as JSON, falling back to standard $_POST fields.
     *
     * @return array
     */
    protected function parseBody(): array
    {
        $raw = \file_get_contents('php://input');
        return \json_decode($raw, true) ?? $_POST;
    }
}
