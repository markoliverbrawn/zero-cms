<?php

declare(strict_types=1);

/**
 * File: src/Core/Concerns/ResolvesTenantContext.php
 * Architectural Purpose: The per-request tenant/user lookup query, one-time model/module/session
 * initialization, and CLI-context detection that App::bootstrap() drives. Extracted out of App.php.
 * Package: Zero\Core\Concerns
 */

namespace Zero\Core\Concerns;

use Zero\Database\DB;
use Zero\Models\Media;
use Zero\Models\Page;
use Zero\Models\Site;
use Zero\Models\User;
use Zero\Modules\Security\Models\AuditLog;
use Zero\Modules\Security\Models\SecurityAudit;

/**
 * Trait ResolvesTenantContext
 */
trait ResolvesTenantContext
{

    /**
     * Executes the consolidated UNION ALL query to fetch both the active tenant Site
     * and the logged-in User in a single roundtrip, registering them in identity caches.
     */
    protected static function bootstrapFetchSiteAndUser(string $host, ?string $userId): bool
    {
        if ($userId) {
            $sql = "
                SELECT 
                    'site' AS record_type, id, name, domain, theme, enabled_modules,
                    homepage_id, timezone, default_language,
                    NULL AS email, NULL AS password_hash, NULL AS role, NULL AS site_id, NULL AS preferences, 
                    created_at, updated_at 
                FROM sites WHERE domain = ?
                UNION ALL
                SELECT 
                    'user' AS record_type, id, username AS name, NULL AS domain, NULL AS theme, NULL AS enabled_modules,
                    NULL AS homepage_id, NULL AS timezone, NULL AS default_language,
                    email, password_hash, role, site_id, preferences, 
                    created_at, updated_at 
                FROM users WHERE id = ?
            ";
            $params = [$host, $userId];
        } else {
            $sql = "
                SELECT 
                    'site' AS record_type, id, name, domain, theme, enabled_modules,
                    homepage_id, timezone, default_language,
                    NULL AS email, NULL AS password_hash, NULL AS role, NULL AS site_id, NULL AS preferences, 
                    created_at, updated_at 
                FROM sites WHERE domain = ?
            ";
            $params = [$host];
        }

        $userFound = false;
        try {
            $stmt = DB::query($sql, $params);
            $rows = $stmt->fetchAll();
            
            foreach ($rows as $row) {
                if ($row['record_type'] === 'site') {
                    $siteData = [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'domain' => $row['domain'],
                        'theme' => $row['theme'],
                        'enabled_modules' => $row['enabled_modules'] ?? '[]',
                        'homepage_id' => $row['homepage_id'] ?? null,
                        'timezone' => $row['timezone'] ?? 'UTC',
                        'default_language' => $row['default_language'] ?? 'en',
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at']
                    ];
                    require_once APPLICATION_ROOT . '/src/Models/Site.php';
                    self::$currentSite = new Site($siteData);
                    DB::setIdentity('sites', $row['id'], self::$currentSite);
                } elseif ($row['record_type'] === 'user') {
                    $userFound = true;
                    $userData = [
                        'id' => $row['id'],
                        'username' => $row['name'], // Aliased as 'name' in UNION query
                        'email' => $row['email'],
                        'role' => $row['role'],
                        'site_id' => $row['site_id'],
                        'preferences' => $row['preferences'],
                        'password_hash' => $row['password_hash'],
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at']
                    ];
                    require_once APPLICATION_ROOT . '/src/Models/User.php';
                    self::$currentUser = new User($userData);
                    DB::setIdentity('users', $row['id'], self::$currentUser);
                }
            }
        } catch (\Exception $e) {
            // Safe fallback during seeding or database initialization
        }

        return $userFound;
    }

    /**
     * Registers core models and discovers modules on bootstrap initialization.
     */
    protected static function bootstrapInitialize(): void
    {
        // Enforce Content Security Policy and standard security response headers platform-wide
        self::applyContentSecurityPolicyMiddleware();

        // Register core models dynamically in the core on bootstrap!
        self::registerModel('audit_logs', AuditLog::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class);
        self::registerModel('files', Media::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class);
        self::registerModel('pages', Page::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class);
        self::registerModel('security_audits', SecurityAudit::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class);
        self::registerModel('sites', Site::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class);
        self::registerModel('users', User::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class);

        // Populate standard core dashboard, content, and security sidebar items
        self::initializeDefaultSidebar();

        // Auto-discover and register all modular capabilities on bootstrap!
        self::discoverAndRegisterModules();

        self::ensureSession();
    }

    /**
     * Ensure session processing implementation helper.
     *
     * @return mixed Response output.
     */
    public static function ensureSession()
    {
        if (\session_status() === PHP_SESSION_NONE) {
            // SECURITY REMEDIATION: Enforce strict secure session cookie configurations
            \session_start([
                'cookie_httponly' => true,
                'cookie_secure' => isset($_SERVER['HTTPS']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'),
                'cookie_samesite' => 'Lax'
            ]);
        }
    }

    /**
     * Check if the application is running in a command-line interface (CLI) context.
     */
    public static function isCli(): bool
    {
        return PHP_SAPI === 'cli' || \defined('CLI_CONTEXT');
    }

}
