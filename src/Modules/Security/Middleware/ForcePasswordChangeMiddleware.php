<?php

namespace Zero\Modules\Security\Middleware;

use Zero\Core\App;

class ForcePasswordChangeMiddleware
{
    public function handle(callable $next)
    {
        App::ensureSession();

        $uri = $_SERVER['REQUEST_URI'] ?? '';

        // Allow pass-through for change password controller, logout, or static assets to prevent infinite redirection
        if (strpos($uri, '/admin/change-password') === 0 || strpos($uri, '/admin/logout') === 0) {
            return $next();
        }

        $user = App::getCurrentUser();
        
        // Default seed installation password hash check
        $defaultHash = '$2y$10$2tdsRK0UD/QvrVPFoz1WZOtodh33dRR1jfRzQbkDDpUuBfHZJPzhC';
        if ($user !== null && $user->password_hash === $defaultHash) {
            header('Location: /admin/change-password');
            exit();
        }

        return $next();
    }
}
