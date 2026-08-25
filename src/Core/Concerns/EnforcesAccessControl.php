<?php

declare(strict_types=1);

/**
 * File: src/Core/Concerns/EnforcesAccessControl.php
 * Architectural Purpose: Authentication/authorization middleware application, the RBAC permission
 * checks (authorize()/requirePermission()) backed by Zero\Support\Permissions, and the
 * access-denied view configuration, extracted out of App.php.
 * Package: Zero\Core\Concerns
 */

namespace Zero\Core\Concerns;

use Zero\Http\Middleware\AuthMiddleware;
use Zero\Http\Middleware\CsrfMiddleware;
use Zero\Http\Middleware\RateLimitMiddleware;
use Zero\Modules\Security\Middleware\ContentSecurityPolicyMiddleware;
use Zero\Modules\Security\Middleware\ForcePasswordChangeMiddleware;
use Zero\Support\Permissions;

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
     * Determine whether the current logged-in user's role has been granted a given RBAC
     * permission key, either explicitly or via the 'super_admin' universal wildcard.
     *
     * @param string $permission Argument descriptor.
     * @return bool Response output.
     */
    public static function authorize(string $permission): bool
    {
        return Permissions::roleHas(self::getCurrentUserRole(), $permission);
    }

    /**
     * Look up the RBAC permission key required to edit/delete/export a given model's records, or
     * null if that model has no restriction beyond the generic backoffice/content permissions.
     *
     * @param string $modelName Argument descriptor.
     * @return string|null Response output.
     */
    public static function permissionForModel(string $modelName): ?string
    {
        return Permissions::permissionForModel($modelName);
    }

    /**
     * Register (or overwrite) the RBAC permission key required to edit/delete/export a given
     * model's records via the generic admin controllers. Intended to be called from a module's
     * own Module::init() for models that module owns.
     *
     * @param string $model Argument descriptor.
     * @param string $permission Argument descriptor.
     * @return void
     */
    public static function registerModelPermission(string $model, string $permission): void
    {
        Permissions::registerModelPermission($model, $permission);
    }

    /**
     * Grant an RBAC permission key to one or more roles. Intended to be called from a module's own
     * Module::init() to declare permissions that module's own domain owns.
     *
     * @param string $permission Argument descriptor.
     * @param array $grantedToRoles Argument descriptor.
     * @return void
     */
    public static function registerPermission(string $permission, array $grantedToRoles): void
    {
        Permissions::register($permission, $grantedToRoles);
    }

    /**
     * Enforce Role-Based Access Control (RBAC): hard-fail with a 403 access-denied page unless the
     * current logged-in user's role has been granted the given permission key.
     *
     * @param string $permission Argument descriptor.
     * @return mixed Response output.
     */
    public static function requirePermission(string $permission)
    {
        self::ensureSession();

        if (self::authorize($permission)) {
            return;
        }

        \http_response_code(403);
        self::render(self::$accessDeniedView, [
            'currentRole' => self::getCurrentUserRole(),
            'requiredPermission' => $permission
        ]);
        exit;
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
