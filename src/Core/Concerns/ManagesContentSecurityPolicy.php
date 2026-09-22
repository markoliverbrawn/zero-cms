<?php

declare(strict_types=1);

/**
 * File: src/Core/Concerns/ManagesContentSecurityPolicy.php
 * Architectural Purpose: Module bootstrapping registry hook for the Content-Security-Policy header.
 * Package: Zero\Core\Concerns
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Core\Concerns;

/**
 * Trait ManagesContentSecurityPolicy
 *
 * Lets modules extend the core Content-Security-Policy directives (e.g. to allow a
 * third-party script/frame/connect origin) without editing core. Additive only --
 * a module can widen a directive's allowed sources but cannot remove or replace one
 * of the core baseline sources, so the security baseline stays authoritative.
 */
trait ManagesContentSecurityPolicy
{
    protected static $cspAdditions = [];

    /**
     * Get all sources registered by modules, keyed by directive.
     *
     * @return array
     */
    public static function getCspAdditions(): array
    {
        return self::$cspAdditions;
    }

    /**
     * Register an additional allowed source for a Content-Security-Policy directive.
     *
     * @param string $directive e.g. 'script-src', 'frame-src', 'connect-src'.
     * @param string $source e.g. 'https://js.stripe.com'.
     * @return void
     */
    public static function registerCspSource(string $directive, string $source): void
    {
        self::$cspAdditions[$directive][] = $source;
    }
}
