<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Controllers/FrontendResetController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Admin\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Interfaces\Controller;
use Zero\Models\User;
use Zero\Support\Logger;
use Zero\Support\Security;

/**
 * Class FrontendResetController
 *
 * Theme-side password-reset completion, the front-of-site counterpart to ResetController, rendered
 * inside the active theme for public member accounts.
 */
class FrontendResetController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($param)
    {
        App::ensureSession();
        if (App::getCurrentUser()) {
            \header('Location: /shop/account');
            exit;
        }

        $token = $_GET['token'] ?? $_POST['token'] ?? '';
        $token = \is_string($token) ? $token : '';

        // Locate the token, scoped to the tenant that issued it, and only for member accounts:
        // this flow signs the user straight in, so back-office tokens must use /admin/reset.
        $reset = $token === '' ? false : DB::query(
            "SELECT r.* FROM password_resets r JOIN users u ON u.id = r.user_id
             WHERE r.token = ? AND r.site_id = ? AND u.site_id = r.site_id AND u.deleted_at IS NULL
               AND u.role NOT IN ('super_admin', 'admin', 'editor') LIMIT 1",
            [\hash('sha256', $token), App::getCurrentSiteId()]
        )->fetch();
        
        if (!$reset || \strtotime($reset['expires_at']) < \time()) {
            App::render('reset', ['error' => 'Your password recovery token is invalid or has expired. Please request a new recovery link.']);
            exit;
        }

        $userId = $reset['user_id'];
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'POST') {
            App::applyCsrfMiddleware();
            
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (empty($password) || empty($confirm)) {
                $error = 'All fields are required.';
            } elseif ($password !== $confirm) {
                $error = 'Passwords do not match.';
            } elseif (\strlen($password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } else {
                // Update User password hash
                // security-scan-ignore: unscoped-lookup -- $userId comes from a password_resets row already scoped to this site and to a member of it
                $user = User::find($userId);
                if ($user) {
                    $user->password_hash = \password_hash($password, PASSWORD_DEFAULT);
                    $user->save();
                    // Ends the member's other sessions (see bootstrapFetchSiteAndUser()).
                    DB::query("UPDATE users SET password_changed_at = ? WHERE id = ?", [\gmdate('Y-m-d H:i:s'), $userId]);

                    // Clear every pending reset token for this member, not just the one used
                    DB::query("DELETE FROM password_resets WHERE user_id = ?", [$userId]);
                    
                    Logger::log($userId, 'frontend_password_reset_success', 'user', $userId, ['ip_address' => Security::getClientIp()]);
                    
                    // Log the user in instantly!
                    App::loginUser($userId);
                    
                    $_SESSION['success_msg'] = 'Password updated successfully! Welcome to your Account Portal.';
                    \header('Location: /shop/account');
                    exit;
                }
                $error = 'User account no longer exists.';
            }

            App::render('reset', ['error' => $error, 'token' => $token]);
            exit;
        }

        App::render('reset', ['token' => $token]);
        exit;
    }
}
