<?php

declare(strict_types=1);

/**
 * File: src/Core/Concerns/ManagesCurrentContext.php
 * Architectural Purpose: The currently-resolved tenant Site/User/homepage accessors and mutators,
 * plus login/logout. Extracted out of App.php.
 * Package: Zero\Core\Concerns
 */

namespace Zero\Core\Concerns;

use Zero\Database\DB;
use Zero\Support\Emailer;

/**
 * Trait ManagesCurrentContext
 */
trait ManagesCurrentContext
{
    protected static $currentSite = null;
    protected static $currentHomepage = null;
    protected static $currentUser = null;

    /**
     * Retrieve the pre-loaded site homepage page record.
     */
    public static function getCurrentHomepage()
    {
        if (self::$currentHomepage === null) {
            self::bootstrap();
        }
        return self::$currentHomepage;
    }

    /**
     * Identify and cache the active tenant site model.
     */
    public static function getCurrentSite()
    {
        if (self::$currentSite === null) {
            self::bootstrap();
        }
        return self::$currentSite;
    }

    /**
     * Identify and cache the active tenant site ID.
     */
    public static function getCurrentSiteId(): string
    {
        $site = self::getCurrentSite();
        return $site ? ($site->id ?? '') : '';
    }

    /**
     * Retrieve the cached current User record.
     */
    public static function getCurrentUser()
    {
        if (self::$currentUser === null && !empty($_SESSION['user_id'])) {
            self::bootstrap();
        }
        return self::$currentUser;
    }

    /**
     * Get the active logged-in user's role. Defaults to 'guest'.
     */
    public static function getCurrentUserRole(): string
    {
        $user = self::getCurrentUser();
        return $user ? ($user->role ?? 'editor') : 'guest';
    }

    /**
     * Login user processing implementation helper.
     *
     * @param mixed $userId Argument descriptor.
     * @return mixed Response output.
     */
    public static function loginUser($userId)
    {
        self::ensureSession();
        if (!\headers_sent()) {
            \session_regenerate_id(true);
        }
        $_SESSION['is_admin'] = true;
        $_SESSION['user_id'] = $userId;
        
        // Re-bootstrap App instantly on login to ensure newly authenticated user maps to static fields
        self::bootstrap();
    }

    /**
     * Logout user processing implementation helper.
     *
     * @return mixed Response output.
     */
    public static function logoutUser()
    {
        self::ensureSession();
        $_SESSION = [];
        if (\session_status() === PHP_SESSION_ACTIVE && !\headers_sent()) {
            \session_regenerate_id(true);
        }
        
        // Clear caches
        self::$currentUser = null;
    }

    /**
     * Sets the current site attribute configuration value.
     *
     * @param mixed $site Argument descriptor.
     * @return void Response output.
     */
    public static function setCurrentSite($site): void
    {
        self::$currentSite = $site;
        // Reset DB Column Cache and clear Identity Map to avoid cross-tenant caching pollution
        DB::clearColumnCache();
        // Keep Emailer's forced-recipient redirect (test mode / demo sites) in sync with whichever
        // tenant is now active -- Queue/Scheduler jobs swap the site context per-job via this
        // setter (see QueueManager/Scheduler), so a job for a demo site must redirect its mail
        // even though no HTTP bootstrap ran to apply this for it.
        Emailer::setForceRecipient($site->email_override ?? null);
    }

    /**
     * Sets the current user attribute configuration value.
     *
     * @param mixed $user Argument descriptor.
     * @return void Response output.
     */
    public static function setCurrentUser($user): void
    {
        self::$currentUser = $user;
    }

}
