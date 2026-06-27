<?php

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Support\Logger;
use Zero\Interfaces\Controller;

class FrontendLoginController implements Controller
{
    public function handle($param)
    {
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            App::applyCsrfMiddleware();
            $user = $_POST['username'] ?? '';
            $pass = $_POST['password'] ?? '';

            $row = DB::query('SELECT * FROM users WHERE username = ? LIMIT 1', [$user])->fetch();
            if ($row && password_verify($pass, $row['password_hash'])) {
                // Multi-Tenant User Separation Constraint:
                // Frontend users must belong to the active site/domain, unless they are super_admin!
                $userRole = $row['role'] ?? 'editor';
                $userSiteId = $row['site_id'] ?? null;
                $currentSiteId = App::getCurrentSiteId();

                if ($userRole === 'super_admin' || $userSiteId === $currentSiteId) {
                    App::loginUser($row['id']);
                    Logger::log($row['id'], 'frontend_login_success', 'user', $row['id'], ['username' => $user, 'ip_address' => $_SERVER['REMOTE_ADDR']]);
                    
                    // Forward to original requested page if present, otherwise fallback to home
                    $redirectTo = $_SESSION['redirect_to'] ?? '/';
                    unset($_SESSION['redirect_to']); // Clean up session!
                    header('Location: ' . $redirectTo);
                    exit;
                }
            }

            $error = 'Invalid credentials';
            Logger::log(null, 'frontend_login_failed', 'user', null, ['username' => $user, 'ip_address' => $_SERVER['REMOTE_ADDR']]);
            
            // SECURITY REMEDIATION: Throttle brute force dictionary attempts
            sleep(1);
            
            App::render('login', ['error' => $error]);
            exit;
        }
        
        App::render('login');
        exit;
    }
}
