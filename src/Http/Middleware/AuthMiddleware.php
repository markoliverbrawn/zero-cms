<?php

namespace Zero\Http\Middleware;

use Zero\Core\App;

class AuthMiddleware
{
    protected static $loginUrl = '/admin/login';
    protected static $defaultRedirect = '/admin/dashboard';

    public function handle(callable $next)
    {
        // Start session if not already started
        App::ensureSession();

        // Check if user is logged in and actually exists inside our database!
        if (!isset($_SESSION['user_id']) || App::getCurrentUser() === null) {
            // Store the original requested URI so we can forward them back after successful login!
            $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'] ?? self::$defaultRedirect;

            // Cleanly invalidate legacy session if they had an expired/invalid user_id
            if (isset($_SESSION['user_id'])) {
                App::logoutUser();
            }
            
            // Redirect gracefully to login page
            header('Location: ' . self::$loginUrl);
            exit();
        }

        // Centralized RBAC Hardening: Restrict back-office access to administrative roles only
        $currentRole = App::getCurrentUserRole();
        if ($currentRole !== 'editor' && $currentRole !== 'super_admin') {
            http_response_code(403);
            App::render('admin/access_denied', [
                'currentRole' => $currentRole,
                'requiredRole' => 'editor'
            ]);
            exit();
        }

        // User is logged in and valid, proceed to the next middleware or route handler
        return $next();
    }


    public static function setDefaultRedirect(string $url)
    {
        self::$defaultRedirect = $url;
    }

    

    public static function setLoginUrl(string $url)
    {
        self::$loginUrl = $url;
    }

    }
