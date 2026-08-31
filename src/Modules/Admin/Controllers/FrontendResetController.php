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
        
        // Locate token inside database
        $reset = DB::query("SELECT * FROM password_resets WHERE token = ? LIMIT 1", [$token])->fetch();
        
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
                $user = User::find($userId);
                if ($user) {
                    $user->password_hash = \password_hash($password, PASSWORD_DEFAULT);
                    $user->save();
                    
                    // Clear the used reset token from database
                    DB::query("DELETE FROM password_resets WHERE token = ?", [$token]);
                    
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
