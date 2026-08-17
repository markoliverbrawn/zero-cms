<?php

declare(strict_types=1);

/**
 * File: src/Http/Middleware/AuthThrottlingMiddleware.php
 * Architectural Purpose: HTTP request routing, request filtering middleware, or dynamic content-security controllers.
 * Package: Zero\Http\Middleware
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Http\Middleware;

use Zero\Core\App;
use Zero\Support\Logger;
use Zero\Support\Security;

/**
 * Class AuthThrottlingMiddleware
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class AuthThrottlingMiddleware
{
    /**
     * Intercept and throttle authentication attempts (login and password resets) based on username/IP.
     *
     * @param string $action Action category ('login' or 'password_reset').
     * @param string $view View template path to render on lockout.
     * @param array $viewData View parameters to pass to the template on lockout.
     * @param callable $next The callback to proceed with on success.
     */
    public static function handle(string $action, string $view, array $viewData, callable $next)
    {
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            $username = \trim($_POST['username'] ?? '');
            if (empty($username)) {
                // Fallback to IP address if no username is present (e.g., during reset completion)
                $username = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            }

            if (!Security::checkAuthRateLimit($action, $username)) {
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                Logger::log(null, $action . '_lockout', 'user', null, [
                    'username' => $username,
                    'ip_address' => $ip
                ]);

                $errorMsg = $action === 'login' 
                    ? 'Too many failed login attempts. Please try again in 15 minutes.' 
                    : 'Too many failed password reset attempts. Please try again in 15 minutes.';

                $viewData['error'] = $errorMsg;
                if (!isset($viewData['csrf'])) {
                    $viewData['csrf'] = Security::csrfToken();
                }

                App::render($view, $viewData);
                exit;
            }
        }

        return $next();
    }
}
