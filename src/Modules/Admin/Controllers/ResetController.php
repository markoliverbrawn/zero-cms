<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Controllers/ResetController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Admin\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Http\Middleware\AuthThrottlingMiddleware;
use Zero\Interfaces\Controller;
use Zero\Support\Logger;
use Zero\Support\Security;

/**
 * Class ResetController
 *
 * Back-office password-reset completion at /admin/reset. Validates the emailed token, applies the
 * new password, and consumes the token so the link cannot be replayed.
 */
class ResetController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($param)
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $token = $_GET['token'] ?? ($_POST['token'] ?? '');
        if ($method === 'POST') {
            App::applyCsrfMiddleware();
            $ip = Security::getClientIp();

            // Enforce centralized rate limiting and progressive lockout protection via Middleware
            AuthThrottlingMiddleware::handle('password_reset', 'admin/reset', ['token' => $token], function() {});

            $new = $_POST['password'] ?? '';
            
            // Security Hardening: Enforce strong password complexity policy
            if (\strlen($new) < 10 || !\preg_match('/[A-Z]/', $new) || !\preg_match('/[a-z]/', $new) || !\preg_match('/[0-9]/', $new)) {
                App::render('admin/reset', [
                    'token' => $token,
                    'error' => 'Password is too weak. It must be at least 10 characters long and contain uppercase, lowercase, and numeric characters.'
                ]);
                exit;
            }

            $row = DB::query('SELECT * FROM password_resets WHERE token = ? LIMIT 1', [$token])->fetch();
            if (!$row || \strtotime($row['expires_at']) < \time()) {
                // Log failed attempt to increment rate limit counter
                Logger::log(null, 'password_reset_failed', 'user', null, [
                    'ip_address' => $ip,
                    'token' => $token
                ]);
                App::render('admin/reset', ['error' => 'Invalid or expired token']);
                exit;
            }
            $hash = \password_hash($new, PASSWORD_DEFAULT);
            DB::query('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $row['user_id']]);
            
            // Security Hardening: Invalidate and rotate ALL pending password resets for this user upon success
            DB::query('DELETE FROM password_resets WHERE user_id = ?', [$row['user_id']]);
            
            Logger::log($row['user_id'], 'password_reset_success', 'user', $row['user_id'], ['ip_address' => Security::getClientIp()]);
            App::render('admin/reset', ['success' => true]);
            exit;
        }
        App::render('admin/reset', ['token' => $token]);
        exit;
    }
}
