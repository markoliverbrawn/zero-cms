<?php

declare(strict_types=1);

/**
 * File: src/Modules/Security/Middleware/ForcePasswordChangeMiddleware.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Security\Middleware
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Security\Middleware;

use Zero\Core\App;

/**
 * Class ForcePasswordChangeMiddleware
 *
 * Diverts a signed-in user whose password is flagged as needing replacement to the change-password
 * screen, so the rest of the back office stays unreachable until they do.
 */
class ForcePasswordChangeMiddleware
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param callable $next Argument descriptor.
     * @return mixed Response output.
     */
    public function handle(callable $next)
    {
        App::ensureSession();

        $uri = $_SERVER['REQUEST_URI'] ?? '';

        // Allow pass-through for change password controller, logout, or static assets to prevent infinite redirection
        if (\strpos($uri, '/admin/change-password') === 0 || \strpos($uri, '/admin/logout') === 0) {
            return $next();
        }

        $user = App::getCurrentUser();
        
        // Default seed installation password hash check
        $defaultHash = '$2y$10$2tdsRK0UD/QvrVPFoz1WZOtodh33dRR1jfRzQbkDDpUuBfHZJPzhC';
        if ($user !== null && $user->password_hash === $defaultHash) {
            \header('Location: /admin/change-password');
            exit();
        }

        return $next();
    }
}
