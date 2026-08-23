<?php
// Initialize dynamic internationalization
use Zero\Core\App;
use Zero\Models\Site;
use Zero\Models\User;
use Zero\Support\AssetVersion;
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
$adminFavicon = '/assets/favicons/' . $activeTheme . '.svg';
if (!file_exists(APPLICATION_ROOT . '/public' . $adminFavicon)) {
    $adminFavicon = '/assets/favicons/default.svg';
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Zero</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo Str::escape($adminFavicon); ?>">
    <?php
    // Was a timestamp-based cache-buster, which changed on every request and so
    // re-downloaded the stylesheet on every single page load. A content digest
    // caches it properly while still replacing it the moment the file actually changes.
    ?>
    <link rel="stylesheet" href="<?php echo Str::escape(AssetVersion::url('/assets/css/admin.css')); ?>">
    <?php if ($themePreset !== 'default' && file_exists(APPLICATION_ROOT . '/public/assets/css/admin-themes/admin-' . $themePreset . '.css')): ?>
        <link rel="stylesheet" href="<?php echo Str::escape(AssetVersion::url('/assets/css/admin-themes/admin-' . $themePreset . '.css')); ?>">
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
    $sections = App::getAdminSidebarSections();
    ?>
    <aside>
        <ul class="sidebar-menu">
            <?php
            $renderedSystemHeader = false;
            foreach ($sections as $sectionId => $section):
                if (!App::isSidebarItemVisible($section, $site)) {
                    continue;
                }

                $visibleLinks = [];
                if (!empty($section['links'])) {
                    foreach ($section['links'] as $link) {
                        if (App::isSidebarItemVisible($link, $site)) {
                            $visibleLinks[] = $link;
                        }
                    }
                }

                if (empty($section['url']) && empty($visibleLinks)) {
                    continue;
                }

                if (!empty($section['is_system']) && !$renderedSystemHeader) {
                    echo '<li class="sidebar-divider"></li>';
                    echo '<li class="sidebar-section-header">System</li>';
                    $renderedSystemHeader = true;
                }

                if (!empty($section['url'])):
                    $activeAttr = isActiveLink($section['url'], $currentUri);
                    ?>
                    <li class="sidebar-item">
                        <a href="<?php echo Str::escape($section['url']); ?>"<?php echo $activeAttr; ?> title="<?php echo Str::escape($section['title']); ?>">
                            <span class="icon-svg sidebar-link-icon"><?php echo App::svg($section['icon']); ?></span>
                            <span class="sidebar-link-text"><?php echo Str::escape($section['title']); ?></span>
                        </a>
                    </li>
                <?php else:
                    $isSectionActive = false;
                    foreach ($visibleLinks as $link) {
                        if (isActiveLink($link['url'], $currentUri) !== '') {
                            $isSectionActive = true;
                            break;
                        }
                    }
                    ?>
                    <li class="sidebar-section<?php echo $isSectionActive ? '' : ' collapsed'; ?>">
                        <button type="button" class="sidebar-section-toggle" title="<?php echo Str::escape($section['title']); ?>">
                            <span class="icon-svg sidebar-section-icon"><?php echo App::svg($section['icon']); ?></span>
                            <span class="sidebar-section-title"><?php echo Str::escape($section['title']); ?></span>
                            <span class="icon-svg sidebar-section-arrow">
                                <?php echo App::svg('chevron-right'); ?>
                            </span>
                        </button>
                        <ul class="sidebar-submenu no-transition">
                            <?php foreach ($visibleLinks as $link): ?>
                                <?php $activeAttr = isActiveLink($link['url'], $currentUri); ?>
                                <li>
                                    <a href="<?php echo Str::escape($link['url']); ?>"<?php echo $activeAttr; ?> title="<?php echo Str::escape($link['title']); ?>">
                                        <span class="icon-svg sidebar-link-icon"><?php echo App::svg($link['icon']); ?></span>
                                        <span class="sidebar-link-text"><?php echo Str::escape($link['title']); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
            
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
    <script src="<?php echo Str::escape(AssetVersion::url('/assets/js/admin.js')); ?>"></script>
</body>
</html>