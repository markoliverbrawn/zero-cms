<?php

declare(strict_types=1);

/**
 * File: src/Modules/Security/HealthChecks/ProjectRoot.php
 * Architectural Purpose: Locates the root of the installation being checked.
 * Package: Zero\Modules\Security\HealthChecks
 * Systemic Role: Lets health checks find sub-project files from any entry point.
 */

namespace Zero\Modules\Security\HealthChecks;

/**
 * Class ProjectRoot
 *
 * A sub-project's front controller and workers define SUBPROJECT_ROOT, but core's own CLI tools
 * (bin/health-check run from vendor/) only define APPLICATION_ROOT, which then points at
 * vendor/markoliverbrawn/zero-cms-core. This resolves the sub-project root either way.
 */
class ProjectRoot
{
    /**
     * Vendor path core lives at inside a sub-project.
     */
    private const VENDOR_SUFFIX = '/vendor/markoliverbrawn/zero-cms-core';

    /**
     * The root of the installation: the sub-project root when core is installed as a Composer
     * package, else the core checkout itself.
     *
     * @return string
     */
    public static function get(): string
    {
        return self::resolve(APPLICATION_ROOT, \defined('SUBPROJECT_ROOT') ? (string) SUBPROJECT_ROOT : null);
    }

    /**
     * Whether core is installed as a Composer package inside a sub-project.
     *
     * @return bool
     */
    public static function isSubProject(): bool
    {
        return self::get() !== \rtrim(APPLICATION_ROOT, '/');
    }

    /**
     * Resolve the installation root from the core root and an optional explicit sub-project root.
     *
     * @param string $applicationRoot Core's root directory.
     * @param string|null $subProjectRoot SUBPROJECT_ROOT when defined.
     * @return string
     */
    public static function resolve(string $applicationRoot, ?string $subProjectRoot): string
    {
        if ($subProjectRoot !== null && $subProjectRoot !== '') {
            return \rtrim($subProjectRoot, '/');
        }
        $applicationRoot = \rtrim($applicationRoot, '/');
        if (\str_ends_with($applicationRoot, self::VENDOR_SUFFIX)) {
            return \substr($applicationRoot, 0, -\strlen(self::VENDOR_SUFFIX));
        }

        return $applicationRoot;
    }
}
