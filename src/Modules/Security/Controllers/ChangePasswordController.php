<?php
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

/**
 * Class ChangePasswordController
 *
 * Provides structural platform implementation and operational encapsulation.
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
            header('Location: /admin/login');
            exit();
        }

        $userId = $_SESSION['user_id'];
        $user = App::getCurrentUser();
        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            App::applyCsrfMiddleware();

            $password = $_POST['password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            // Strict Strength Validation Rules
            if (empty($password)) {
                $error = 'Password cannot be empty.';
            } elseif (strlen($password) < 8) {
                $error = 'For enhanced security, password must be at least 8 characters long.';
            } elseif ($password !== $confirm) {
                $error = 'Passwords do not match. Please re-type and try again.';
            } elseif (strtolower($password) === 'change_me') {
                $error = 'Please select a unique, strong password. "change_me" is blocked.';
            } else {
                // Securely Hash and Update Password
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                
                DB::query("UPDATE users SET password_hash = ? WHERE id = ?", [$newHash, $userId]);
                
                // Log security audit trail
                Logger::log($userId, 'force_change_password', 'user', $userId, [
                    'username' => $user->username,
                    'status' => 'success'
                ]);

                // Clear loaded static user properties so App reloads correct state on next bootstrap
                App::logoutUser();
                
                $_SESSION['success_flash'] = 'Password changed successfully! Please log in with your new password.';
                header('Location: /admin/login');
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
