<?php

declare(strict_types=1);

/**
 * File: src/Modules/Security/Controllers/ChangePasswordController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Security\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Security\Controllers;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Interfaces\Controller;
use Zero\Support\Logger;
use Zero\Support\Security;

/**
 * Class ChangePasswordController
 *
 * Handles a password change for the signed-in account, including the forced change
 * ForcePasswordChangeMiddleware redirects into.
 */
class ChangePasswordController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($param)
    {
        // Enforce active session and baseline authentication
        App::ensureSession();
        if (!isset($_SESSION['user_id']) || App::getCurrentUser() === null) {
            \header('Location: /admin/login');
            exit();
        }

        $userId = $_SESSION['user_id'];
        $user = App::getCurrentUser();
        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            App::applyCsrfMiddleware();

            $current = (string) ($_POST['current_password'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');
            $policyError = Security::passwordPolicyError($password);

            // The current password is required even on a forced change: a session alone (stolen,
            // or left open) must not be enough to take the account over.
            if (!Security::verifyPassword($current, $user->password_hash ?? null)) {
                $error = 'Your current password is incorrect.';
                Logger::log($userId, 'password_change_failed', 'user', $userId, [
                    'username' => $user->username,
                    'ip_address' => Security::getClientIp()
                ]);
            } elseif ($password !== $confirm) {
                $error = 'Passwords do not match. Please re-type and try again.';
            } elseif ($policyError !== null) {
                $error = $policyError;
            } else {
                // Securely Hash and Update Password
                $newHash = \password_hash($password, PASSWORD_DEFAULT);
                
                // password_changed_at signs out the account's other sessions (see
                // bootstrapFetchSiteAndUser()); this one is signed out just below.
                DB::query("UPDATE users SET password_hash = ?, password_changed_at = ? WHERE id = ?", [$newHash, \gmdate('Y-m-d H:i:s'), $userId]);
                
                // Log security audit trail
                Logger::log($userId, 'force_change_password', 'user', $userId, [
                    'username' => $user->username,
                    'status' => 'success'
                ]);

                // Clear loaded static user properties so App reloads correct state on next bootstrap
                App::logoutUser();
                
                $_SESSION['success_flash'] = 'Password changed successfully! Please log in with your new password.';
                \header('Location: /admin/login');
                exit();
            }
        }

        App::render('security/change-password', [
            'user' => $user,
            'error' => $error,
            'success' => $success,
            'title' => 'Change Default Password'
        ]);
        exit;
    }
}
