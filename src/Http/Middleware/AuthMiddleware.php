<?php

declare(strict_types=1);

/**
 * File: src/Http/Middleware/AuthMiddleware.php
 * Architectural Purpose: HTTP request routing, request filtering middleware, or dynamic content-security controllers.
 * Package: Zero\Http\Middleware
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Http\Middleware;

use Zero\Core\App;

/**
 * Class AuthMiddleware
 *
 * Gate in front of authenticated back-office routes. Re-resolves the session user against the
 * database rather than trusting the session alone, enforces the administrative role set, and
 * redirects to the configurable login URL when either check fails.
 */
class AuthMiddleware
{
    protected static $loginUrl = '/admin/login';
    protected static $defaultRedirect = '/admin/dashboard';

    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param callable $next Argument descriptor.
     * @return mixed Response output.
     */
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
            \header('Location: ' . self::$loginUrl);
            exit();
        }

        // Centralized RBAC Hardening: Restrict back-office access to administrative roles only
        if (!App::authorize('backoffice.access')) {
            \http_response_code(403);
            App::render('admin/access-denied', [
                'currentRole' => App::getCurrentUserRole(),
                'requiredPermission' => 'backoffice.access'
            ]);
            exit();
        }

        // User is logged in and valid, proceed to the next middleware or route handler
        return $next();
    }

    /**
     * Sets the default redirect attribute configuration value.
     *
     * @param string $url Argument descriptor.
     * @return mixed Response output.
     */
    public static function setDefaultRedirect(string $url)
    {
        self::$defaultRedirect = $url;
    }

    /**
     * Sets the login url attribute configuration value.
     *
     * @param string $url Argument descriptor.
     * @return mixed Response output.
     */
    public static function setLoginUrl(string $url)
    {
        self::$loginUrl = $url;
    }
}
