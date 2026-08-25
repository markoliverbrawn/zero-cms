<?php

declare(strict_types=1);

/**
 * File: src/Support/Permissions.php
 * Architectural Purpose: The role-based-access-control permission registry. Holds core's own
 * baseline role->permission grants and model->permission mappings, plus the additive registration
 * API (register()/registerModelPermission()) that lets a module declare its own permission keys
 * and grants from its own Module::init(), the same way a module self-registers its models, sidebar
 * links, or settings schema elsewhere in the framework. Core never hardcodes a module-owned
 * permission key here (Rule 2) -- it only ships the mechanism plus the permissions it genuinely
 * owns (backoffice access, generic content edit/destructive actions, and the core users/sites
 * models).
 * Package: Zero\Support
 */

namespace Zero\Support;

/**
 * Class Permissions
 *
 * Static role->permission and model->permission registry, consulted by
 * Zero\Core\Concerns\EnforcesAccessControl's authorize()/requirePermission()/permissionForModel().
 */
final class Permissions
{
    /**
     * Model name => permission key required to edit/delete/export that model's records via the
     * generic admin controllers. Core registers its own (users, sites); modules add their own via
     * registerModelPermission().
     *
     * @var array<string, string>
     */
    private static array $modelPermissions = [
        'users' => 'users.manage',
        'sites' => 'sites.manage',
    ];

    /**
     * Role => list of granted permission keys. 'super_admin' uses the '*' sentinel to mean "every
     * permission" rather than enumerating every key that ever gets registered. A role/permission
     * combination that was never explicitly granted is denied by default -- register() is only
     * ever called to grant, never to deny.
     *
     * @var array<string, array<int, string>>
     */
    private static array $rolePermissions = [
        'super_admin' => ['*'],
        'admin' => ['backoffice.access', 'content.edit', 'users.manage', 'modules.manage'],
        'editor' => ['backoffice.access', 'content.edit'],
    ];

    /**
     * Look up the permission key required to edit/delete/export a given model's records, or null
     * if that model has no restriction beyond the generic backoffice/content permissions.
     *
     * @param string $model
     * @return string|null
     */
    public static function permissionForModel(string $model): ?string
    {
        return self::$modelPermissions[$model] ?? null;
    }

    /**
     * Grant a permission key to one or more roles. Intended to be called once per permission by
     * whichever module owns that permission's domain, typically from its Module::init().
     *
     * @param string $permission
     * @param array<int, string> $grantedToRoles
     * @return void
     */
    public static function register(string $permission, array $grantedToRoles): void
    {
        foreach ($grantedToRoles as $role) {
            self::$rolePermissions[$role][] = $permission;
        }
    }

    /**
     * Register (or overwrite) the permission key required to edit/delete/export a given model's
     * records via the generic admin controllers.
     *
     * @param string $model
     * @param string $permission
     * @return void
     */
    public static function registerModelPermission(string $model, string $permission): void
    {
        self::$modelPermissions[$model] = $permission;
    }

    /**
     * Determine whether a role has been granted a given permission, either explicitly or via the
     * 'super_admin' universal wildcard.
     *
     * @param string $role
     * @param string $permission
     * @return bool
     */
    public static function roleHas(string $role, string $permission): bool
    {
        $granted = self::$rolePermissions[$role] ?? [];

        return \in_array('*', $granted, true) || \in_array($permission, $granted, true);
    }
}
