<?php

declare(strict_types=1);

/**
 * File: src/Core/Concerns/EnforcesAccessControl.php
 * Architectural Purpose: Authentication/authorization middleware application and the
 * role-based-access-denied view configuration, extracted out of App.php.
 * Package: Zero\Core\Concerns
 */

namespace Zero\Core\Concerns;

use Zero\Http\Middleware\AuthMiddleware;
use Zero\Http\Middleware\CsrfMiddleware;
use Zero\Http\Middleware\RateLimitMiddleware;
use Zero\Modules\Security\Middleware\ContentSecurityPolicyMiddleware;
use Zero\Modules\Security\Middleware\ForcePasswordChangeMiddleware;

/**
 * Trait EnforcesAccessControl
 */
trait EnforcesAccessControl
{
    protected static $accessDeniedView = 'admin/access-denied';

    /**
     * Applies authentication and role authorization check middleware filters onto routes.
     *
     * @return mixed Response output.
     */
    public static function applyAuthMiddleware()
    {
        self::ensureSession();
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->handle(function() {
            // If authentication passed, verify if the user has a default system password active
            self::applyForcePasswordChangeMiddleware();
        });
    }

    /**
     * Applies content security policy (CSP) headers middleware to prevent script injections.
     *
     * @return mixed Response output.
     */
    public static function applyContentSecurityPolicyMiddleware()
    {
        $cspMiddleware = new ContentSecurityPolicyMiddleware();
        $cspMiddleware->handle(function() {
            // Passed
        });
    }

    /**
     * Applies cross-site request forgery (CSRF) token verification middleware for form submissions.
     *
     * @return mixed Response output.
     */
    public static function applyCsrfMiddleware()
    {
        self::ensureSession();
        $csrfMiddleware = new CsrfMiddleware();
        $csrfMiddleware->handle(function() {
            // If this anonymous function executes, it means CSRF verification passed.
        });
    }

    /**
     * Applies force password change middleware checking if user must change password.
     *
     * @return mixed Response output.
     */
    public static function applyForcePasswordChangeMiddleware()
    {
        self::ensureSession();
        $forcePasswordMiddleware = new ForcePasswordChangeMiddleware();
        $forcePasswordMiddleware->handle(function() {
            // Passed
        });
    }

    /**
     * Applies rate limiting middleware throttling requests from a single client IP.
     *
     * @param string $key Argument descriptor.
     * @param int $limitSeconds Argument descriptor.
     * @return mixed Response output.
     */
    public static function applyRateLimitMiddleware(string $key, int $limitSeconds)
    {
        self::ensureSession();
        RateLimitMiddleware::handle($key, $limitSeconds, function() {
            // If this anonymous function executes, rate limit passed.
        });
    }

    /**
     * Enforce Role-Based Access Control (RBAC) security checks on sensitive admin features.
     */
    public static function applyRoleMiddleware(string $requiredRole)
    {
        self::ensureSession();
        
        $currentRole = self::getCurrentUserRole();
        if ($currentRole === 'super_admin') {
            return;
        }

        if ($currentRole !== $requiredRole) {
            \http_response_code(403);
            self::render(self::$accessDeniedView, [
                'currentRole' => $currentRole,
                'requiredRole' => $requiredRole
            ]);
            exit;
        }
    }

    /**
     * Sets the access denied view attribute configuration value.
     *
     * @param string $view Argument descriptor.
     * @return mixed Response output.
     */
    public static function setAccessDeniedView(string $view)
    {
        self::$accessDeniedView = $view;
    }

}
