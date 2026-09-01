<?php

declare(strict_types=1);

/**
 * File: src/Core/Concerns/BootstrapsApp.php
 * Architectural Purpose: Top-level App::bootstrap() orchestration and the site-resolution /
 * input-sanitization steps it drives, extracted out of App.php.
 * Package: Zero\Core\Concerns
 */

namespace Zero\Core\Concerns;

use Zero\Core\Env;
use Zero\Database\DB;
use Zero\Models\Page;
use Zero\Support\Emailer;
use Zero\Support\Security;

/**
 * Trait BootstrapsApp
 */
trait BootstrapsApp
{
    protected static $bootstrapped = false;

    /**
     * Unified, Single-Query Bootstrap Routine.
     * Fetches both the active Site Tenant and the currently logged-in User in a single SQL query!
     */
    public static function bootstrap()
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        self::bootstrapInitialize();
        self::bootstrapSanitizeInputs();

        // DEV MODE SETUP WIZARD INTERCEPT
        if (Env::get('ENVIRONMENT') === 'dev' && \php_sapi_name() !== 'cli') {
            try {
                $sitesTableExists = DB::query("SHOW TABLES LIKE 'sites'")->fetch();
                $usersTableExists = DB::query("SHOW TABLES LIKE 'users'")->fetch();
                
                $siteCount = 0;
                $userCount = 0;
                
                if ($sitesTableExists) {
                    $siteCount = (int) DB::query("SELECT COUNT(*) FROM sites")->fetchColumn();
                }
                if ($usersTableExists) {
                    $userCount = (int) DB::query("SELECT COUNT(*) FROM users")->fetchColumn();
                }
                
                if (!$sitesTableExists || !$usersTableExists || ($siteCount === 0 && $userCount === 0)) {
                    $uri = \parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
                    $ext = \strtolower(\pathinfo($uri, PATHINFO_EXTENSION));
                    $staticExts = ['css', 'js', 'svg', 'woff2', 'png', 'jpg', 'jpeg', 'gif', 'mp4', 'ico'];
                    if (!\in_array($ext, $staticExts)) {
                        $setupWizard = new \Zero\Modules\Admin\Controllers\SetupWizardController();
                        $setupWizard->handleRequest();
                        exit;
                    }
                }
            } catch (\Exception $e) {
                // If tables don't exist yet, we also trigger the setup wizard!
                $uri = \parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
                $ext = \strtolower(\pathinfo($uri, PATHINFO_EXTENSION));
                $staticExts = ['css', 'js', 'svg', 'woff2', 'png', 'jpg', 'jpeg', 'gif', 'mp4', 'ico'];
                if (!\in_array($ext, $staticExts)) {
                    $setupWizard = new \Zero\Modules\Admin\Controllers\SetupWizardController();
                    $setupWizard->handleRequest();
                    exit;
                }
            }
        }

        // Pass the full Host header (including port, if any) through as-is -- a site's domain may
        // now optionally include a port (e.g. 'test.localhost:8370'), and
        // bootstrapFetchSiteAndUser() itself falls back to the bare hostname if nothing matches
        // the exact host:port. Security::resolveTrustedHost() prefers X-Forwarded-Host (proxies
        // like Cloudflare rewrite Host to their own edge/origin hostname before the request
        // reaches the app, so HTTP_HOST alone would resolve every request to the same wrong
        // tenant) but only once a configured TRUSTED_PROXY_SECRET verifies it -- this drives the
        // tenant lookup below, so an unverified forged value could redirect resolution to an
        // arbitrary registered domain.
        $host = Security::resolveTrustedHost();
        $userId = $_SESSION['user_id'] ?? null;

        $userFound = self::bootstrapFetchSiteAndUser($host, $userId);

        if ($userId !== null && !$userFound) {
            self::logoutUser();
        }

        self::bootstrapResolveSiteOrFallback($host);

        // Redirect every email this tenant sends to its configured override address (test mode /
        // demo sites), if one is set. bootstrapFetchSiteAndUser() assigns $currentSite directly
        // rather than through setCurrentSite(), so this has to be applied here too rather than
        // relying solely on that setter's own copy of this same wiring.
        Emailer::setForceRecipient(self::$currentSite->email_override ?? null);

        // Pre-load the active site's homepage page record dynamically during app bootstrapping
        if (self::$currentSite !== null) {
            $siteId = self::$currentSite->id ?? '';
            $homepageId = self::$currentSite->homepage_id ?? '';

            // Mirror HasSlug::findBySlug()'s guest restriction here: unlike that lookup, these
            // homepage fallbacks go through Page::find()/where() (which apply no status filter
            // at all), so a draft page picked by any of them would otherwise render unfiltered
            // at "/" for anonymous visitors regardless of its published state.
            $isAdmin = isset($_SESSION['user_id']);
            $isVisibleHomepage = function ($page) use ($isAdmin) {
                return $page !== null && ($isAdmin || ($page->status ?? null) === 'published');
            };

            $homePage = null;
            if (!empty($homepageId)) {
                $candidate = Page::find($homepageId);
                if ($isVisibleHomepage($candidate)) {
                    $homePage = $candidate;
                }
            }

            if ($homePage === null) {
                // Fallback: Query pages table for an empty slug ("") homepage under active site
                $pages = Page::where('slug', '');
                foreach ($pages as $candidate) {
                    if ($isVisibleHomepage($candidate)) {
                        $homePage = $candidate;
                        break;
                    }
                }
            }

            if ($homePage === null) {
                // Fallback: Query pages table for "home" slug page under active site
                $pages = Page::where('slug', 'home');
                foreach ($pages as $candidate) {
                    if ($isVisibleHomepage($candidate)) {
                        $homePage = $candidate;
                        break;
                    }
                }
            }

            if ($homePage === null) {
                // Fallback 3: Query pages table for the first page under active site (by precedence, then created_at)
                try {
                    $statusFilter = $isAdmin ? '' : "AND status = 'published' ";
                    $sql = "SELECT id FROM pages WHERE site_id = ? AND deleted_at IS NULL {$statusFilter}ORDER BY precedence ASC, created_at ASC LIMIT 1";
                    $stmt = DB::query($sql, [$siteId]);
                    $row = $stmt->fetch();
                    if ($row) {
                        $homePage = Page::find($row['id']);
                    }
                } catch (\Exception $e) {
                    // Safe fallback
                }
            }
            
            self::$currentHomepage = $homePage;
        }

        // Enforce strict multi-tenant site isolation for active sessions
        if (self::$currentUser !== null) {
            $userRole = self::$currentUser->role;
            $userSiteId = self::$currentUser->site_id;
            $currentSiteId = self::$currentSite ? (self::$currentSite->id ?? '') : '';

            if (!($userRole === 'super_admin' || $userSiteId === $currentSiteId)) {
                self::logoutUser();
                self::$currentUser = null;
            }
        }
    }

    /**
     * Resolves the active site, rendering the secure "Site Not Found" error page on web requests
     * if the requested domain host does not match any registered site tenant in the database.
     */
    protected static function bootstrapResolveSiteOrFallback(string $host): void
    {
        if (self::$currentSite === null) {
            if (self::isCli()) {
                return;
            }
            self::renderSiteNotFoundPage($host);
            exit;
        }
    }

    /**
     * Lockdown safety: recursively sanitizes standard request query inputs.
     */
    protected static function bootstrapSanitizeInputs(): void
    {
        require_once APPLICATION_ROOT . '/src/Support/Security.php';
        $_GET = Security::sanitizeInput($_GET, true);
        $_POST = Security::sanitizeInput($_POST, false);
        $_REQUEST = Security::sanitizeInput($_REQUEST, false);
    }

}
