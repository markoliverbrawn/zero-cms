<?php

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Support\Logger;
use Zero\Interfaces\Controller;
use Zero\Models\User;
use Zero\Support\Security;

class RegisterController implements Controller
{
    public function handle($param)
    {
        App::ensureSession();
        if (App::getCurrentUser()) {
            header('Location: /shop/account');
            exit;
        }

        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            App::applyCsrfMiddleware();
            
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (empty($username) || empty($email) || empty($password)) {
                $error = 'All fields are required.';
            } elseif ($password !== $confirm) {
                $error = 'Passwords do not match.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } else {
                // Check if username already exists globally or for this site
                $existsUser = DB::query("SELECT id FROM users WHERE username = ? LIMIT 1", [$username])->fetch();
                $existsEmail = DB::query("SELECT id FROM users WHERE email = ? LIMIT 1", [$email])->fetch();

                if ($existsUser) {
                    $error = 'Username already taken.';
                } elseif ($existsEmail) {
                    $error = 'Email address already registered.';
                } else {
                    $userId = Security::uuidv7();
                    $siteId = App::getCurrentSiteId();
                    
                    // Create new customer account strictly partitioned to the active tenant site!
                    $newUser = new User([
                        'id' => $userId,
                        'site_id' => $siteId,
                        'username' => $username,
                        'email' => $email,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'role' => 'member', // Register as standard customer 'member' (CSP & RBAC hardened)
                        'preferences' => json_encode(['theme' => 'light', 'addresses' => []]),
                        'created_at' => gmdate('Y-m-d H:i:s'),
                        'updated_at' => gmdate('Y-m-d H:i:s')
                    ]);
                    
                    $newUser->save();
                    
                    // Log the customer in instantly!
                    App::loginUser($userId);
                    
                    Logger::log($userId, 'registration_success', 'user', $userId, ['username' => $username, 'ip_address' => $_SERVER['REMOTE_ADDR']]);
                    
                    header('Location: /shop/account');
                    exit;
                }
            }

            Logger::log(null, 'registration_failed', 'user', null, ['username' => $username, 'email' => $email, 'error' => $error ?? '']);
            App::render('register', ['error' => $error ?? '']);
            exit;
        }

        App::render('register');
        exit;
    }
}
