<?php

namespace Zero\Core;

use Zero\Support\Security;
use Zero\Support\Str;
use Zero\Database\DB;
use Zero\Http\Middleware\AuthMiddleware;
use Zero\Http\Middleware\CsrfMiddleware;
use Zero\Http\Middleware\RateLimitMiddleware;
use Zero\Http\Router;
use Zero\Interfaces\Module as ModuleInterface;
use Zero\Core\Env;
use Zero\Models\Media;
use Zero\Models\Page;
use Zero\Models\Site;
use Zero\Models\User;
use Zero\Modules\Security\Middleware\ContentSecurityPolicyMiddleware;
use Zero\Modules\Security\Middleware\ForcePasswordChangeMiddleware;
use Zero\Modules\Security\Models\AuditLog;
use Zero\Modules\Security\Models\SecurityAudit;
use Zero\Core\Template;
use Zero\Core\Storage\Storage;

class App
{
    protected static $currentSite = null;
    protected static $currentHomepage = null;
    protected static $currentUser = null;
    protected static $bootstrapped = false;
    protected static $modules = [];
    protected static $viewDirs = [];
    protected static $accessDeniedView = 'admin/access-denied';
    protected static $registeredBlocks = [];
    protected static $registeredModels = [];
    protected static $themeFallbacks = [];
    protected static $adminSidebarSections = [];
    protected static $themeStylesheets = [];
    protected static $nonce = '';

    public static function appendBenchmarkWidget()
    {
        if (Env::get('BENCHMARKING') !== 'true') {
            return;
        }

        $queryLog = DB::getQueryLog();
        $queryCount = DB::getQueryCount();
        $totalTime = DB::getTotalQueryTime();
        
        // Calculate total page execution runtime
        $totalPageTime = microtime(true) - (defined('REQUEST_START_TIME') ? REQUEST_START_TIME : $_SERVER['REQUEST_TIME_FLOAT']);

        ?>
        <!-- Custom Zero-Dependency Widescreen Benchmark Stats -->
        <div id="db-benchmark-widget" style="position: fixed; bottom: 20px; right: 20px; width: 450px; max-height: 400px; background: #0f172a; border: 1px solid #1e293b; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); font-family: monospace; z-index: 99999999; color: #f8fafc; overflow: hidden; display: flex; flex-direction: column;">
            <!-- Header (Click to toggle) -->
            <div id="db-benchmark-header" style="padding: 12px 18px; background: #1e293b; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none;">
                <div style="font-weight: bold; font-size: 0.82rem; display: flex; align-items: center; gap: 8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#00ffcc" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <span>SYSTEM BENCHMARK</span>
                </div>
                <div style="font-size: 0.76rem; color: #00ffcc; font-weight: bold;">
                    <strong><?php echo $queryCount; ?></strong> Qs in <strong><?php echo number_format($totalTime * 1000, 2); ?>ms</strong> | Page: <strong><?php echo number_format($totalPageTime * 1000, 2); ?>ms</strong>
                </div>
            </div>
            <!-- Body (Collapsible scroll drawer) -->
            <div id="db-benchmark-body" style="display: none; padding: 15px; overflow-y: auto; flex-grow: 1; max-height: 320px; font-size: 0.75rem; background: #0b0f19;">
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php if (empty($queryLog)): ?>
                        <div style="color: #64748b; font-style: italic; text-align: center; padding: 15px 0;">No queries run in this request.</div>
                    <?php else: ?>
                        <?php foreach ($queryLog as $idx => $q): ?>
                            <div style="border-bottom: 1px solid #1e293b; padding-bottom: 10px;">
                                <div style="display: flex; justify-content: space-between; color: #64748b; margin-bottom: 4px; font-size: 0.68rem; font-weight: bold;">
                                    <span>QUERY #<?php echo $idx + 1; ?></span>
                                    <span style="color: #00ffcc; font-weight: bold;"><?php echo number_format($q['duration'] * 1000, 2); ?>ms</span>
                                </div>
                                <div style="color: #ffffff; white-space: pre-wrap; word-break: break-all; margin-bottom: 6px; line-height: 1.4; background: #151d30; padding: 6px; border-radius: 4px; border: 1px solid #1e293b; font-family: monospace; font-size: 0.72rem;"><?php echo Str::escape($q['sql']); ?></div>
                                <?php if (!empty($q['params'])): ?>
                                    <div style="color: #94a3b8; font-size: 0.68rem; word-break: break-all; background: #0d121f; padding: 4px; border-radius: 2px;">
                                        <strong style="color: #ff0055;">BINDS:</strong> <?php echo Str::escape(json_encode($q['params'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <script nonce="<?php echo self::getNonce(); ?>">
        document.addEventListener('DOMContentLoaded', () => {
            const widget = document.getElementById('db-benchmark-widget');
            const header = document.getElementById('db-benchmark-header');
            const body = document.getElementById('db-benchmark-body');
            if (!widget || !header || !body) return;

            let isExpanded = false;
            let isDragging = false;
            let hasMoved = false;
            let currentX = 0;
            let currentY = 0;
            let initialX = 0;
            let initialY = 0;
            let xOffset = 0;
            let yOffset = 0;

            // Header Toggle Expand/Collapse (Only if we didn't drag!)
            header.addEventListener('click', (e) => {
                if (hasMoved) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                isExpanded = !isExpanded;
                body.style.display = isExpanded ? 'block' : 'none';
            });

            // Vanilla Drag and Drop Logic
            header.addEventListener('mousedown', dragStart);
            document.addEventListener('mousemove', drag);
            document.addEventListener('mouseup', dragEnd);

            header.addEventListener('touchstart', dragStart, { passive: true });
            document.addEventListener('touchmove', drag, { passive: false });
            document.addEventListener('touchend', dragEnd);

            function dragStart(e) {
                hasMoved = false;
                if (e.type === 'touchstart') {
                    initialX = e.touches[0].clientX - xOffset;
                    initialY = e.touches[0].clientY - yOffset;
                } else {
                    initialX = e.clientX - xOffset;
                    initialY = e.clientY - yOffset;
                }

                if (e.target === header || header.contains(e.target)) {
                    isDragging = true;
                    header.style.cursor = 'grabbing';
                }
            }

            function drag(e) {
                if (!isDragging) return;

                if (e.cancelable) {
                    e.preventDefault();
                }

                let clientX, clientY;
                if (e.type === 'touchmove') {
                    clientX = e.touches[0].clientX;
                    clientY = e.touches[0].clientY;
                } else {
                    clientX = e.clientX;
                    clientY = e.clientY;
                }

                currentX = clientX - initialX;
                currentY = clientY - initialY;

                // Threshold to distinguish dragging from clicking
                if (Math.abs(currentX - xOffset) > 5 || Math.abs(currentY - yOffset) > 5) {
                    hasMoved = true;
                }

                xOffset = currentX;
                yOffset = currentY;

                widget.style.transform = `translate(${currentX}px, ${currentY}px)`;
            }

            function dragEnd() {
                if (!isDragging) return;
                initialX = currentX;
                initialY = currentY;
                isDragging = false;
                header.style.cursor = 'grab';
            }

            header.style.cursor = 'grab';
        });
        </script>
        <?php
    }

    

    public static function applyAuthMiddleware()
    {
        self::ensureSession();
        $authMiddleware = new AuthMiddleware();
        $authMiddleware->handle(function() {
            // If authentication passed, verify if the user has a default system password active
            self::applyForcePasswordChangeMiddleware();
        });
    }

    

    public static function applyContentSecurityPolicyMiddleware()
    {
        $cspMiddleware = new ContentSecurityPolicyMiddleware();
        $cspMiddleware->handle(function() {
            // Passed
        });
    }

    

    public static function applyCsrfMiddleware()
    {
        self::ensureSession();
        $csrfMiddleware = new CsrfMiddleware();
        $csrfMiddleware->handle(function() {
            // If this anonymous function executes, it means CSRF verification passed.
        });
    }

    

    public static function applyForcePasswordChangeMiddleware()
    {
        self::ensureSession();
        $forcePasswordMiddleware = new ForcePasswordChangeMiddleware();
        $forcePasswordMiddleware->handle(function() {
            // Passed
        });
    }

    

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
            http_response_code(403);
            self::render(self::$accessDeniedView, [
                'currentRole' => $currentRole,
                'requiredRole' => $requiredRole
            ]);
            exit;
        }
    }

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
        if (Env::get('ENVIRONMENT') === 'dev' && php_sapi_name() !== 'cli') {
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
                    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
                    $ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
                    $staticExts = ['css', 'js', 'svg', 'woff2', 'png', 'jpg', 'jpeg', 'gif', 'mp4', 'ico'];
                    if (!in_array($ext, $staticExts)) {
                        $setupWizard = new \Zero\Modules\Admin\Controllers\SetupWizardController();
                        $setupWizard->handleRequest();
                        exit;
                    }
                }
            } catch (\Exception $e) {
                // If tables don't exist yet, we also trigger the setup wizard!
                $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
                $ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
                $staticExts = ['css', 'js', 'svg', 'woff2', 'png', 'jpg', 'jpeg', 'gif', 'mp4', 'ico'];
                if (!in_array($ext, $staticExts)) {
                    $setupWizard = new \Zero\Modules\Admin\Controllers\SetupWizardController();
                    $setupWizard->handleRequest();
                    exit;
                }
            }
        }

        $host = explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0];
        $userId = $_SESSION['user_id'] ?? null;

        $userFound = self::bootstrapFetchSiteAndUser($host, $userId);

        if ($userId !== null && !$userFound) {
            self::logoutUser();
        }

        self::bootstrapResolveSiteOrFallback($host);

        // Pre-load the active site's homepage page record dynamically during app bootstrapping
        if (self::$currentSite !== null) {
            $siteId = self::$currentSite->id ?? '';
            $homepageId = self::$currentSite->homepage_id ?? '';
            
            $homePage = null;
            if (!empty($homepageId)) {
                $homePage = Page::find($homepageId);
            }
            
            if ($homePage === null) {
                // Fallback: Query pages table for an empty slug ("") homepage under active site
                $pages = Page::where('slug', '');
                if (!empty($pages)) {
                    $homePage = $pages[0];
                }
            }
            
            if ($homePage === null) {
                // Fallback: Query pages table for "home" slug page under active site
                $pages = Page::where('slug', 'home');
                if (!empty($pages)) {
                    $homePage = $pages[0];
                }
            }
            
            if ($homePage === null) {
                // Fallback 3: Query pages table for the first page under active site (by precedence, then created_at)
                try {
                    $sql = "SELECT id FROM pages WHERE site_id = ? AND deleted_at IS NULL ORDER BY precedence ASC, created_at ASC LIMIT 1";
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
        self::registerModel('audit_logs', AuditLog::class);
        self::registerModel('files', Media::class);
        self::registerModel('pages', Page::class);
        self::registerModel('security_audits', SecurityAudit::class);
        self::registerModel('sites', Site::class);
        self::registerModel('users', User::class);

        // Populate standard core dashboard, content, and security sidebar items
        self::initializeDefaultSidebar();

        // Auto-discover and register all modular capabilities on bootstrap!
        self::discoverAndRegisterModules();

        self::ensureSession();
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

    /**
     * Scan the src/Modules directory to automatically discover all active module classes
     * and register their custom namespaces and routes in the Router.
     */
    public static function discoverAndRegisterModules()
    {
        self::discoverModules();

        // Dynamically register each module's namespaces, routes, and views with central Router & App
        foreach (self::$modules as $module) {
            // Dynamically derive the namespace prefix using reflection under PSR-4
            $ref = new \ReflectionClass($module);
            $namespace = $ref->getNamespaceName() . '\\';
            Router::registerModuleNamespace($namespace, $module->getId());
            Router::register($module->getRoutes(), null, $module->getId());

            // Convention-based View Registration: Check if a /Views directory exists inside the module folder
            $moduleDir = dirname($ref->getFileName());
            $viewsDir = $moduleDir . '/Views';
            if (is_dir($viewsDir)) {
                self::registerViewDir($module->getId(), $viewsDir);
            }

            // Run optional initialization method on module (for dynamic block registrations, filters, etc.)
            if (method_exists($module, 'init')) {
                $module->init();
            }
        }
    }

    

    public static function discoverModules()
    {
        if (!empty(self::$modules)) {
            return;
        }

        $modulesDir = APPLICATION_ROOT . '/src/Modules';
        if (!is_dir($modulesDir)) {
            return;
        }

        $folders = scandir($modulesDir);
        foreach ($folders as $folder) {
            if ($folder === '.' || $folder === '..') {
                continue;
            }

            $className = "Zero\\Modules\\{$folder}\\Module";
            if (class_exists($className)) {
                $module = new $className();
                if ($module instanceof ModuleInterface) {
                    self::$modules[$module->getId()] = $module;
                }
            }
        }
    }

    

    /**
     * Dynamically and recursively collect and eager-load all media assets referenced inside page builder blocks
     * by scanning for standardized 'media_id' and 'media_ids' fields. Prevents any N+1 query loops!
     * Returns an associative array of [media_id => physical_path].
     */
    public static function eagerLoadBlockMedia(array $blocks): array
    {
        $mediaIds = [];
        
        $collectIds = function($data) use (&$collectIds, &$mediaIds) {
            if (!is_array($data)) {
                return;
            }
            
            foreach ($data as $key => $val) {
                if ($key === 'media_id') {
                    if (is_string($val) && strlen($val) === 36 && strpos($val, '/') === false) {
                        $mediaIds[] = $val;
                    }
                } elseif ($key === 'media_ids' && is_array($val)) {
                    foreach ($val as $v) {
                        if (is_string($v) && strlen($v) === 36 && strpos($v, '/') === false) {
                            $mediaIds[] = $v;
                        }
                    }
                }
                
                // Recursively check child arrays (such as 'items' inside accordion or masonry)
                if (is_array($val)) {
                    $collectIds($val);
                }
            }
        };

        $collectIds($blocks);
        
        $mediaIdMap = [];
        if (!empty($mediaIds)) {
            $filteredIds = array_filter(array_unique($mediaIds));
            if (!empty($filteredIds)) {
                $placeholders = implode(',', array_fill(0, count($filteredIds), '?'));
                $sql = "SELECT id, path FROM media WHERE id IN ($placeholders) AND deleted_at IS NULL";
                $stmt = DB::query($sql, array_values($filteredIds));
                while ($row = $stmt->fetch()) {
                    $mediaIdMap[$row['id']] = $row['path'];
                }
            }
        }
        return $mediaIdMap;
    }

    public static function ensureSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            // SECURITY REMEDIATION: Enforce strict secure session cookie configurations
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => isset($_SERVER['HTTPS']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'),
                'cookie_samesite' => 'Lax'
            ]);
        }
    }

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
     * Get all registered admin sidebar sections sorted by precedence,
     * with their nested links also sorted by precedence.
     *
     * @return array
     */
    public static function getAdminSidebarSections(): array
    {
        // Sort sections by precedence
        uasort(self::$adminSidebarSections, function($a, $b) {
            return ($a['precedence'] ?? 100) <=> ($b['precedence'] ?? 100);
        });

        // Sort links within each section by precedence
        foreach (self::$adminSidebarSections as $id => &$section) {
            if (!empty($section['links'])) {
                usort($section['links'], function($a, $b) {
                    return ($a['precedence'] ?? 100) <=> ($b['precedence'] ?? 100);
                });
            }
        }
        unset($section);

        return self::$adminSidebarSections;
    }

    

    public static function getMigrationClasses(): array
    {
        $classes = [];
        foreach (self::getModules() as $module) {
            $mig = $module->getMigrationClass();
            if ($mig) {
                $classes[] = $mig;
            }
        }
        return $classes;
    }

    

    public static function getModelClass(string $name): ?string
    {
        return self::$registeredModels[$name] ?? null;
    }

    

    public static function getModules(): array
    {
        self::discoverModules();
        return self::$modules;
    }

    /**
     * Get the dynamic CSP cryptographic nonce.
     *
     * @return string
     */
    public static function getNonce(): string
    {
        return self::$nonce;
    }

    

    public static function getRegisteredBlocks(): array
    {
        return self::$registeredBlocks;
    }

    

    public static function getRegisteredModels(): array
    {
        return self::$registeredModels;
    }

    /**
     * Get the registered stylesheet path for a theme.
     *
     * @param string $themeName
     * @return string|null
     */
    public static function getThemeStylesheet(string $themeName): ?string
    {
        if (isset(self::$themeStylesheets[$themeName])) {
            return self::$themeStylesheets[$themeName];
        }

        $path = "/assets/css/themes/{$themeName}/{$themeName}.css";
        if (file_exists(APPLICATION_ROOT . '/public' . $path)) {
            return $path;
        }
        return null;
    }

    /**
     * Populate standard core dashboard, content, and security sidebar items.
     *
     * @return void
     */
    protected static function initializeDefaultSidebar(): void
    {
        // Standalone Dashboard
        self::registerAdminSidebarSection('dashboard', [
            'title' => \Zero\Support\I18n::t('admin_dashboard'),
            'url' => '/admin/dashboard',
            'icon' => 'dashboard',
            'precedence' => 10
        ]);

        // Collapsible Content Management
        self::registerAdminSidebarSection('content', [
            'title' => \Zero\Support\I18n::t('content_management'),
            'icon' => 'book-open',
            'precedence' => 100
        ]);

        // Core Content Links
        self::registerAdminSidebarLink('content', [
            'title' => \Zero\Support\I18n::t('manage_pages'),
            'url' => '/admin/list/pages',
            'icon' => 'file',
            'precedence' => 40
        ]);

        self::registerAdminSidebarLink('content', [
            'title' => \Zero\Support\I18n::t('media_library'),
            'url' => '/admin/list/files',
            'icon' => 'image',
            'precedence' => 50
        ]);

        // Standalone Sites Management (System, Super Admin only)
        self::registerAdminSidebarSection('sites', [
            'title' => 'Manage Sites',
            'url' => '/admin/list/sites',
            'icon' => 'home',
            'super_admin_only' => true,
            'is_system' => true,
            'precedence' => 400
        ]);

        // Collapsible Security Management (System, Super Admin only)
        self::registerAdminSidebarSection('security', [
            'title' => \Zero\Support\I18n::t('security'),
            'icon' => 'shield',
            'super_admin_only' => true,
            'is_system' => true,
            'precedence' => 410
        ]);

        self::registerAdminSidebarLink('security', [
            'title' => \Zero\Support\I18n::t('manage_users'),
            'url' => '/admin/list/users',
            'icon' => 'user',
            'precedence' => 10
        ]);

        self::registerAdminSidebarLink('security', [
            'title' => 'Security Logs',
            'url' => '/admin/list/audit_logs',
            'icon' => 'clock',
            'precedence' => 20
        ]);

        self::registerAdminSidebarLink('security', [
            'title' => 'Security Audits',
            'url' => '/admin/list/security_audits',
            'icon' => 'clipboard',
            'precedence' => 30
        ]);
    }

    /**
     * Check if the application is running in a command-line interface (CLI) context.
     */
    public static function isCli(): bool
    {
        return PHP_SAPI === 'cli' || defined('CLI_CONTEXT');
    }

    /**
     * Helper to resolve if a sidebar item (section or link) is visible
     * to the current logged-in user under the active tenant site.
     *
     * @param array $item
     * @param \Zero\Models\Site|null $site
     * @return bool
     */
    public static function isSidebarItemVisible(array $item, ?\Zero\Models\Site $site): bool
    {
        $role = self::getCurrentUserRole();

        // 1. Check super admin only restriction
        if (!empty($item['super_admin_only']) && $role !== 'super_admin') {
            return false;
        }

        // 2. Check module dependency (super admins bypass module-disabled restrictions in the back-office)
        if (!empty($item['module_dependency'])) {
            $module = $item['module_dependency'];
            $isEnabled = $site && $site->isModuleEnabled($module);
            if (!$isEnabled && $role !== 'super_admin') {
                return false;
            }
        }

        return true;
    }

    

    public static function loginUser($userId)
    {
        self::ensureSession();
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['is_admin'] = true;
        $_SESSION['user_id'] = $userId;
        
        // Re-bootstrap App instantly on login to ensure newly authenticated user maps to static fields
        self::bootstrap();
    }

    

    public static function logoutUser()
    {
        self::ensureSession();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }
        
        // Clear caches
        self::$currentUser = null;
    }

    /**
     * Register a link under a specific admin sidebar section.
     *
     * @param string $sectionId
     * @param array $linkConfig
     * @return void
     */
    public static function registerAdminSidebarLink(string $sectionId, array $linkConfig): void
    {
        if (!isset(self::$adminSidebarSections[$sectionId])) {
            self::registerAdminSidebarSection($sectionId, [
                'title' => ucfirst($sectionId),
                'precedence' => 500
            ]);
        }

        self::$adminSidebarSections[$sectionId]['links'][] = array_merge([
            'title' => '',
            'url' => '',
            'icon' => 'file',
            'module_dependency' => null,
            'super_admin_only' => false,
            'precedence' => 100
        ], $linkConfig);
    }

    /**
     * Register a new admin sidebar section or top-level item.
     *
     * @param string $id
     * @param array $config
     * @return void
     */
    public static function registerAdminSidebarSection(string $id, array $config): void
    {
        self::$adminSidebarSections[$id] = array_merge([
            'id' => $id,
            'title' => '',
            'icon' => 'file',
            'url' => null,
            'module_dependency' => null,
            'super_admin_only' => false,
            'is_system' => false,
            'precedence' => 100,
            'links' => []
        ], $config);
    }

    

    public static function registerBlock(string $type, array $config)
    {
        self::$registeredBlocks[$type] = $config;
    }

    

    public static function registerModel(string $name, string $class)
    {
        self::$registeredModels[$name] = $class;
    }

    

    public static function registerThemeFallback(string $themeName)
    {
        self::$themeFallbacks[] = $themeName;
    }

    /**
     * Register a stylesheet path dynamically for a theme.
     *
     * @param string $themeName
     * @param string $stylesheetPath
     * @return void
     */
    public static function registerThemeStylesheet(string $themeName, string $stylesheetPath): void
    {
        self::$themeStylesheets[$themeName] = $stylesheetPath;
    }

    

    public static function registerViewDir(string $prefix, string $dirPath)
    {
        self::$viewDirs[$prefix] = rtrim($dirPath, '/');
    }

    

    public static function render($view, $data = [])
    {
        $phpFile = null;
        $layoutFile = null;

        // Check if the view starts with any registered custom view directory prefix (e.g. 'admin/')
        foreach (self::$viewDirs as $prefix => $dirPath) {
            if (strpos($view, $prefix . '/') === 0) {
                $subView = substr($view, strlen($prefix) + 1);
                $phpFile = $dirPath . '/' . $subView . '.php';
                $layoutFile = $dirPath . '/layout.php';
                break;
            }
        }

        if ($phpFile === null) {
            // Frontend Multi-tenant Theme Resolver
            $site = self::getCurrentSite();
            $theme = $site ? ($site->theme ?? 'default') : 'default';
            
            $phpFile = APPLICATION_ROOT . '/src/Views/themes/' . $theme . '/' . $view . '.php';
            if (!file_exists($phpFile)) {
                // Graceful fallback to default theme view
                $phpFile = APPLICATION_ROOT . '/src/Views/themes/default/' . $view . '.php';
            }
            if (!file_exists($phpFile)) {
                // Graceful fallback to dynamically registered module theme fallbacks!
                foreach (self::$themeFallbacks as $fbTheme) {
                    $fbFile = APPLICATION_ROOT . '/src/Views/themes/' . $fbTheme . '/' . $view . '.php';
                    if (file_exists($fbFile)) {
                        $phpFile = $fbFile;
                        break;
                    }
                }
            }
            $layoutFile = APPLICATION_ROOT . '/src/Views/themes/' . $theme . '/layout.php';
            if (!file_exists($layoutFile)) {
                $layoutFile = APPLICATION_ROOT . '/src/Views/themes/default/layout.php';
            }
        }

        if (file_exists($phpFile)) {
            // ensure csrf token is available to templates
            $data['csrf'] = Security::csrfToken();
            $data['error'] = $data['error'] ?? '';
            $data['session'] = $_SESSION;
    
            $content = Template::renderFile($phpFile, $data);
            if (file_exists($layoutFile)) {
                $data['content'] = $content;
                $data['error'] = $data['error'] ?? '';
                echo Template::renderFile($layoutFile, $data);
                self::appendBenchmarkWidget(); // Inject unified benchmark overlay!
                return;
            }
        }

        // If .php file not found, directly return "View not found".
        echo "View not found: " . Str::escape($view);
        return;
    }

    /**
     * Render a unified, sliding-window pagination HTML block.
     * Preserves active query parameters automatically and scales up safely.
     *
     * @param array $pagination Pagination metadata array
     * @param string $baseUrl Base URL string (e.g. '/shop/catalog' or '/blog')
     * @param array $queryParams Current $_GET parameters array to merge and preserve
     * @return string Compiled HTML string
     */
    public static function renderPagination(array $pagination, string $baseUrl, array $queryParams = []): string
    {
        $currentPage = isset($pagination['currentPage']) ? (int)$pagination['currentPage'] : 1;
        $totalPages = isset($pagination['totalPages']) ? (int)$pagination['totalPages'] : 1;

        if ($totalPages <= 1) {
            return '';
        }

        // Clean and prepare query parameters, skipping 'page' as it is appended dynamically
        $cleanedParams = [];
        foreach ($queryParams as $k => $v) {
            if ($k !== 'page' && $v !== null && $v !== '') {
                $cleanedParams[$k] = $v;
            }
        }

        $buildUrl = function($pageNum) use ($baseUrl, $cleanedParams) {
            $params = array_merge($cleanedParams, ['page' => $pageNum]);
            return $baseUrl . '?' . http_build_query($params);
        };

        // Sliding window range calculation
        $range = 2;
        $startPage = $currentPage - $range;
        $endPage = $currentPage + $range;

        if ($startPage < 1) {
            $endPage += abs($startPage) + 1;
            $startPage = 1;
        }
        if ($endPage > $totalPages) {
            $startPage -= ($endPage - $totalPages);
            $endPage = $totalPages;
        }
        $startPage = max(1, $startPage);

        $showFirst = ($startPage > 1);
        $showLast = ($endPage < $totalPages);

        // Buffer the baseline template render
        ob_start();
        $partialPath = APPLICATION_ROOT . '/src/Views/themes/default/partials/pagination.php';
        if (file_exists($partialPath)) {
            include $partialPath;
        } else {
            // Inline fallback markup if the partial file is missing
            ?>
            <nav class="unified-pagination-wrapper">
                <?php if ($currentPage > 1): ?>
                    <a href="<?php echo Str::escape($buildUrl($currentPage - 1)); ?>" class="pagination-btn page-nav-prev">Prev</a>
                <?php endif; ?>
                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <?php if ($i === $currentPage): ?>
                        <span class="pagination-btn active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo Str::escape($buildUrl($i)); ?>" class="pagination-btn"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?php echo Str::escape($buildUrl($currentPage + 1)); ?>" class="pagination-btn page-nav-next">Next</a>
                <?php endif; ?>
            </nav>
            <?php
        }
        return ob_get_clean();
    }

    /**
     * Renders a highly-polished, high-contrast, developer-friendly fallback page
     * when a requested host domain is not registered inside the multi-tenant database.
     */
    public static function renderSiteNotFoundPage(string $host): void
    {
        http_response_code(404);

        $env = strtolower(Env::get('ENVIRONMENT', 'production'));
        $isDev = ($env === 'development' || $env === 'dev');

        $originalHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $parts = explode(':', $originalHost);
        $portSuffix = isset($parts[1]) ? ':' . $parts[1] : '';

        // Query database to fetch all currently configured and active multi-tenant sites
        $activeSites = [];
        try {
            $stmt = DB::query("SELECT name, domain FROM sites WHERE deleted_at IS NULL ORDER BY name ASC");
            $activeSites = $stmt->fetchAll();
        } catch (\Exception $e) {
            // Fallback if database itself is not initialized
        }

        $templatePath = APPLICATION_ROOT . '/src/Views/errors/site-not-found.php';
        if (file_exists($templatePath)) {
            echo Template::renderFile($templatePath, [
                'host' => $host,
                'isDev' => $isDev,
                'portSuffix' => $portSuffix,
                'activeSites' => $activeSites
            ]);
        } else {
            echo "404 Site Not Found";
        }
    }

    

    public static function setAccessDeniedView(string $view)
    {
        self::$accessDeniedView = $view;
    }

    

    public static function setCurrentSite($site): void
    {
        self::$currentSite = $site;
        // Reset DB Column Cache and clear Identity Map to avoid cross-tenant caching pollution
        DB::clearColumnCache();
    }

    

    public static function setCurrentUser($user): void
    {
        self::$currentUser = $user;
    }

    /**
     * Set the dynamic CSP cryptographic nonce.
     *
     * @param string $nonce
     * @return void
     */
    public static function setNonce(string $nonce): void
    {
        self::$nonce = $nonce;
    }

    

    public static function slugify($text)
    {
        return Str::slug($text);
    }

    /**
     * Slashes-friendly URL path slugifier for manual parent-child page nesting.
     * Keeps method sorting alphabetically correct (slugify -> slugifyPath).
     */
    public static function slugifyPath($text)
    {
        return Str::slugPath($text);
    }

    /**
     * Render the content of an SVG file stored in assets/svgs/
     */
    public static function svg(string $name): string
    {
        $path = APPLICATION_ROOT . '/public/assets/svgs/' . $name . '.svg';
        if (file_exists($path)) {
            return file_get_contents($path);
        }
        return '';
    }
}
