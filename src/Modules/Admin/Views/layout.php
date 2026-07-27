<?php
// Initialize dynamic internationalization
use Zero\Core\App;
use Zero\Models\Site;
use Zero\Models\User;
use Zero\Support\I18n;
use Zero\Support\Str;

I18n::init();

$currentUri = $_SERVER['REQUEST_URI'] ?? '';
function isActiveLink(string $pattern, string $currentUri): string {
    if ($pattern === '/admin/dashboard') {
        return ($currentUri === '/admin/dashboard') ? ' class="active"' : '';
    }
    return (strpos($currentUri, $pattern) !== false) ? ' class="active"' : '';
}

$user = App::getCurrentUser();
$userPrefs = $user ? User::getPreferencesForUser($user->id) : [];
$themePref = $userPrefs['theme'] ?? 'light';
$themePreset = $userPrefs['theme_preset'] ?? 'default';

// Resolve active site theme favicon for Admin Console layout header
$activeSite = App::getCurrentSite();
$activeTheme = $activeSite ? ($activeSite->theme ?? 'default') : 'default';
if ($activeTheme === 'default') {
    $activeTheme = 'corporate';
}
$adminFavicon = '/assets/favicons/' . $activeTheme . '.svg';
if (!file_exists(APPLICATION_ROOT . '/public' . $adminFavicon)) {
    $adminFavicon = '/assets/favicons/corporate.svg';
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Zero</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo Str::escape($adminFavicon); ?>">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?php echo time(); ?>">
    <?php if ($themePreset !== 'default' && file_exists(APPLICATION_ROOT . '/public/assets/css/admin-themes/admin-' . $themePreset . '.css')): ?>
        <link rel="stylesheet" href="/assets/css/admin-themes/admin-<?php echo Str::escape($themePreset); ?>.css">
    <?php endif; ?>
    <meta name="csrf-token" content="<?php echo Str::escape($csrf ?? ''); ?>">
</head>
<body class="<?php echo empty($session['user_id']) ? 'public-layout' : 'admin-layout'; ?>" data-theme="<?php echo Str::escape($themePref); ?>" data-preset="<?php echo Str::escape($themePreset); ?>">
    <header>
        <?php if (!empty($session['user_id'])): ?>
            <button type="button" id="sidebar-toggle" class="sidebar-toggle" aria-label="Toggle Sidebar">
                ☰
            </button>
        <?php endif; ?>
        <div class="logo">Z</div> 
        <h1><a href="/admin/dashboard">Admin</a></h1>
        <nav>
            <?php if (!empty($session['user_id'])): ?>
                <?php if ($user): ?>
                    <span class="header-username">
                        <?php echo I18n::t('logged_in_as'); ?> <strong><?php echo Str::escape($user->username); ?></strong>
                    </span>
                <?php endif; ?>
                <form class="logout" method="post" action="/admin/logout" style="display:inline">
                    <input type="hidden" name="csrf" value="<?php echo Str::escape($csrf ?? ''); ?>">
                    <button type="submit"><?php echo I18n::t('logout'); ?></button>
                </form>
                <?php include APPLICATION_ROOT . '/src/Modules/Admin/Views/theme-switcher.php'; ?>
            <?php endif; ?>
        </nav>
    </header>
    <?php if (!empty($session['user_id'])): ?>
    <?php 
    $site = App::getCurrentSite(); 
    
    $otherSites = [];
    if (App::getCurrentUserRole() === 'super_admin') {
        $allSites = Site::all();
        $currentSiteId = App::getCurrentSiteId();
        foreach ($allSites as $s) {
            if ($s->id !== $currentSiteId) {
                $otherSites[] = $s;
            }
        }
    }
    
    // Evaluate active states server-side to prevent client-side layout rendering flashing
    $isContentActive = isActiveLink('/posts', $currentUri) || 
                       isActiveLink('/pages', $currentUri) || 
                       isActiveLink('/files', $currentUri) || 
                       isActiveLink('/comments', $currentUri);
                       
    $isSecurityActive = isActiveLink('/users', $currentUri) || isActiveLink('/audit_logs', $currentUri) || isActiveLink('/security_audits', $currentUri);
    
    $isShopActive = isActiveLink('/products', $currentUri) || 
                    isActiveLink('/categories', $currentUri) || 
                    isActiveLink('/productvariants', $currentUri) || 
                    isActiveLink('/orders', $currentUri);
    ?>
    <aside>
        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a href="/admin/dashboard"<?php echo isActiveLink('/admin/dashboard', $currentUri); ?> title="<?php echo Str::escape(I18n::t('admin_dashboard')); ?>">
                    <span class="icon-svg sidebar-link-icon"><?php echo App::svg('dashboard'); ?></span>
                    <span class="sidebar-link-text"><?php echo I18n::t('admin_dashboard'); ?></span>
                </a>
            </li>
            
            <!-- SECTION 1: Content Management -->
            <li class="sidebar-section<?php echo $isContentActive ? '' : ' collapsed'; ?>">
                <button type="button" class="sidebar-section-toggle" title="<?php echo Str::escape(I18n::t('content_management')); ?>">
                    <span class="icon-svg sidebar-section-icon"><?php echo App::svg('book-open'); ?></span>
                    <span class="sidebar-section-title"><?php echo I18n::t('content_management'); ?></span>
                    <span class="icon-svg sidebar-section-arrow">
                        <?php echo App::svg('chevron-right'); ?>
                    </span>
                </button>
                <ul class="sidebar-submenu no-transition">
                    <?php if ($site && $site->isModuleEnabled('blog')): ?>
                        <li>
                            <a href="/admin/list/posts"<?php echo isActiveLink('/posts', $currentUri); ?> title="<?php echo Str::escape(I18n::t('manage_posts')); ?>">
                                <span class="icon-svg sidebar-link-icon"><?php echo App::svg('edit-3'); ?></span>
                                <span class="sidebar-link-text"><?php echo I18n::t('manage_posts'); ?></span>
                            </a>
                        </li>
                        <li>
                            <a href="/admin/list/comments"<?php echo isActiveLink('/comments', $currentUri); ?> title="Manage Comments">
                                <span class="icon-svg sidebar-link-icon"><?php echo App::svg('message-square'); ?></span>
                                <span class="sidebar-link-text">Manage Comments</span>
                            </a>
                        </li>
                        <li>
                            <a href="/admin/list/submissions"<?php echo isActiveLink('/submissions', $currentUri); ?> title="Form Submissions">
                                <span class="icon-svg sidebar-link-icon"><?php echo App::svg('clipboard'); ?></span>
                                <span class="sidebar-link-text">Form Submissions</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li>
                        <a href="/admin/list/pages"<?php echo isActiveLink('/pages', $currentUri); ?> title="<?php echo Str::escape(I18n::t('manage_pages')); ?>">
                            <span class="icon-svg sidebar-link-icon"><?php echo App::svg('file'); ?></span>
                            <span class="sidebar-link-text"><?php echo I18n::t('manage_pages'); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="/admin/list/files"<?php echo isActiveLink('/files', $currentUri); ?> title="<?php echo Str::escape(I18n::t('media_library')); ?>">
                            <span class="icon-svg sidebar-link-icon"><?php echo App::svg('image'); ?></span>
                            <span class="sidebar-link-text"><?php echo I18n::t('media_library'); ?></span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- SECTION 3: Shop Management -->
            <?php if ($site && $site->isModuleEnabled('shop')): ?>
            <li class="sidebar-section<?php echo $isShopActive ? '' : ' collapsed'; ?>">
                <button type="button" class="sidebar-section-toggle" title="Shop Management">
                    <span class="icon-svg sidebar-section-icon"><?php echo App::svg('shop'); ?></span>
                    <span class="sidebar-section-title">Shop Management</span>
                    <span class="icon-svg sidebar-section-arrow">
                        <?php echo App::svg('chevron-right'); ?>
                    </span>
                </button>
                <ul class="sidebar-submenu no-transition">
                    <li>
                        <a href="/admin/list/products"<?php echo isActiveLink('/products', $currentUri); ?> title="Manage Products">
                            <span class="icon-svg sidebar-link-icon"><?php echo App::svg('package'); ?></span>
                            <span class="sidebar-link-text">Manage Products</span>
                        </a>
                    </li>
                    <li>
                        <a href="/admin/list/categories"<?php echo isActiveLink('/categories', $currentUri); ?> title="Manage Categories">
                            <span class="icon-svg sidebar-link-icon"><?php echo App::svg('tag'); ?></span>
                            <span class="sidebar-link-text">Manage Categories</span>
                        </a>
                    </li>
                    <li>
                        <a href="/admin/list/productvariants"<?php echo isActiveLink('/productvariants', $currentUri); ?> title="Manage Variants">
                            <span class="icon-svg sidebar-link-icon"><?php echo App::svg('git-branch'); ?></span>
                            <span class="sidebar-link-text">Manage Variants</span>
                        </a>
                    </li>
                    <li>
                        <a href="/admin/list/orders"<?php echo isActiveLink('/orders', $currentUri); ?> title="Manage Orders">
                            <span class="icon-svg sidebar-link-icon"><?php echo App::svg('shopping-cart'); ?></span>
                            <span class="sidebar-link-text">Manage Orders</span>
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- SECTION 4: Forum Management -->
            <?php if ($site && ($site->isModuleEnabled('forum') || App::getCurrentUserRole() === 'super_admin')): ?>
            <?php
            $isForumActive = isActiveLink('/forum_boards', $currentUri) || 
                             isActiveLink('/forum_threads', $currentUri) || 
                             isActiveLink('/forum_posts', $currentUri);
            ?>
            <li class="sidebar-section<?php echo $isForumActive ? '' : ' collapsed'; ?>">
                <button type="button" class="sidebar-section-toggle" title="Forum Management">
                    <span class="icon-svg sidebar-section-icon"><?php echo App::svg('users'); ?></span>
                    <span class="sidebar-section-title">Forum Management</span>
                    <span class="icon-svg sidebar-section-arrow">
                        <?php echo App::svg('chevron-right'); ?>
                    </span>
                </button>
                <ul class="sidebar-submenu no-transition">
                    <li>
                        <a href="/admin/list/forum_boards"<?php echo isActiveLink('/forum_boards', $currentUri); ?> title="Manage Boards">
                            <span class="icon-svg sidebar-link-icon"><?php echo App::svg('layout'); ?></span>
                            <span class="sidebar-link-text">Manage Boards</span>
                        </a>
                    </li>
                    <li>
                        <a href="/admin/list/forum_threads"<?php echo isActiveLink('/forum_threads', $currentUri); ?> title="Manage Threads">
                            <span class="icon-svg sidebar-link-icon"><?php echo App::svg('message-square'); ?></span>
                            <span class="sidebar-link-text">Manage Threads</span>
                        </a>
                    </li>
                    <li>
                        <a href="/admin/list/forum_posts"<?php echo isActiveLink('/forum_posts', $currentUri); ?> title="Manage Posts">
                            <span class="icon-svg sidebar-link-icon"><?php echo App::svg('message-circle'); ?></span>
                            <span class="sidebar-link-text">Manage Posts</span>
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- SYSTEM SECTION -->
            <?php if (App::getCurrentUserRole() === 'super_admin' || ($site && $site->isModuleEnabled('queue'))): ?>
            <li class="sidebar-divider"></li>
            <li class="sidebar-section-header">System</li>

            <?php if (App::getCurrentUserRole() === 'super_admin'): ?>
                <li class="sidebar-item">
                    <a href="/admin/list/sites"<?php echo isActiveLink('/sites', $currentUri); ?> title="Manage Sites">
                        <span class="icon-svg sidebar-link-icon"><?php echo App::svg('home'); ?></span>
                        <span class="sidebar-link-text">Manage Sites</span>
                    </a>
                </li>
            <?php endif; ?>

            <!-- SECTION 2: Security -->
            <?php if (App::getCurrentUserRole() === 'super_admin'): ?>
            <li class="sidebar-section<?php echo $isSecurityActive ? '' : ' collapsed'; ?>">
                <button type="button" class="sidebar-section-toggle" title="<?php echo Str::escape(I18n::t('security')); ?>">
                    <span class="icon-svg sidebar-section-icon"><?php echo App::svg('shield'); ?></span>
                    <span class="sidebar-section-title"><?php echo I18n::t('security'); ?></span>
                    <span class="icon-svg sidebar-section-arrow">
                        <?php echo App::svg('chevron-right'); ?>
                    </span>
                </button>
                <ul class="sidebar-submenu no-transition">
                    <li>
                        <a href="/admin/list/users"<?php echo isActiveLink('/users', $currentUri); ?> title="<?php echo Str::escape(I18n::t('manage_users')); ?>">
                            <span class="icon-svg sidebar-link-icon"><?php echo App::svg('user'); ?></span>
                            <span class="sidebar-link-text"><?php echo I18n::t('manage_users'); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="/admin/list/audit_logs"<?php echo isActiveLink('/audit_logs', $currentUri); ?> title="Security Logs">
                            <span class="icon-svg sidebar-link-icon"><?php echo App::svg('clock'); ?></span>
                            <span class="sidebar-link-text">Security Logs</span>
                        </a>
                    </li>
                    <li>
                        <a href="/admin/list/security_audits"<?php echo isActiveLink('/security_audits', $currentUri); ?> title="Security Audits">
                            <span class="icon-svg sidebar-link-icon"><?php echo App::svg('clipboard'); ?></span>
                            <span class="sidebar-link-text">Security Audits</span>
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- SECTION 5: Job Queue -->
            <?php if ($site && ($site->isModuleEnabled('queue') || App::getCurrentUserRole() === 'super_admin')): ?>
            <?php
            $isQueueActive = isActiveLink('/queue_jobs', $currentUri);
            ?>
            <li class="sidebar-section<?php echo $isQueueActive ? '' : ' collapsed'; ?>">
                <button type="button" class="sidebar-section-toggle" title="Job Queue">
                    <span class="icon-svg sidebar-section-icon"><?php echo App::svg('clock'); ?></span>
                    <span class="sidebar-section-title">Job Queue</span>
                    <span class="icon-svg sidebar-section-arrow">
                        <?php echo App::svg('chevron-right'); ?>
                    </span>
                </button>
                <ul class="sidebar-submenu no-transition">
                    <li>
                        <a href="/admin/list/queue_jobs"<?php echo isActiveLink('/queue_jobs', $currentUri); ?> title="Manage Queue">
                            <span class="icon-svg sidebar-link-icon"><?php echo App::svg('clipboard'); ?></span>
                            <span class="sidebar-link-text">Manage Queue</span>
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>
            <?php endif; ?>
            
            <li class="sidebar-divider"></li>
            <li class="sidebar-item">
                <a href="/" title="<?php echo Str::escape(I18n::t('view_site')); ?>">
                    <span class="icon-svg sidebar-link-icon"><?php echo App::svg('external-link'); ?></span>
                    <span class="sidebar-link-text"><?php echo I18n::t('view_site'); ?></span>
                </a>
            </li>


        </ul>
    </aside>
    <div id="sidebar-backdrop" class="sidebar-backdrop"></div>
    <?php endif; ?>
    <main>
        <?php echo $content; ?>
    </main>
    <footer>
        <small>Zero-dependency CMS</small>
        <?php if (!empty($session['user_id'])): ?>
            <a href="/admin/preferences" title="<?php echo Str::escape(I18n::t('preferences')); ?>" class="footer-preferences-link">
                <span class="icon-svg" style="width: 16px; height: 16px;">
                    <?php echo App::svg('settings'); ?>
                </span>
            </a>
        <?php endif; ?>
    </footer>
    <!-- Modern Integrated Admin Confirmation Modal -->
    <div id="admin-confirm-modal" class="admin-modal-overlay">
        <div class="admin-modal-card">
            <div class="admin-modal-header">
                <span class="admin-modal-icon"><?php echo App::svg('shield'); ?></span>
                <h3 id="admin-modal-title" class="admin-modal-title">Confirm Action</h3>
            </div>
            <div class="admin-modal-body">
                <p id="admin-modal-message" class="admin-modal-message">Are you sure you want to proceed?</p>
                <div id="admin-modal-details" class="admin-modal-details"></div>
                <div id="admin-modal-note" class="admin-modal-note"></div>
                <div id="admin-modal-options" class="admin-modal-options"></div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" id="admin-modal-btn-cancel" class="btn-cancel">Cancel</button>
                <button type="button" id="admin-modal-btn-confirm" class="btn-confirm">Confirm</button>
            </div>
        </div>
    </div>
    <script src="/assets/js/admin.js"></script>
</body>
</html>
