<?php
// src/Views/themes/default/layout.php

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Support\AssetVersion;
use Zero\Support\I18n;
use Zero\Support\Str;
use Zero\Support\StyleBundle;

// Fetch dynamic pages for corporate sidebar
$siteId = App::getCurrentSiteId();
$sidebarPages = DB::query("SELECT title, slug FROM pages WHERE status = 'published' AND site_id = ? AND show_in_nav = 1 ORDER BY precedence ASC, title ASC", [$siteId])->fetchAll();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <?php
    $metaDescription = $meta_description ?? '';
    if (empty($metaDescription)) {
        if (isset($post) && is_object($post)) {
            $metaDescription = $post->summary ?? ($post->description ?? '');
        }
        if (empty($metaDescription)) {
            $metaDescription = App::getCurrentSite()->name . ' - High performance web experience.';
        }
    }
    $metaDescription = strip_tags($metaDescription);
    if (strlen($metaDescription) > 160) {
        $metaDescription = substr($metaDescription, 0, 157) . '...';
    }
    ?>
    <meta name="description" content="<?php echo Str::escape($metaDescription); ?>"/>
    <title>Zero CMS Corporate Portal</title>
    <link rel="icon" type="image/svg+xml" href="/assets/favicons/default.svg">
    <?php // Content-addressed, so the URL changes whenever any source stylesheet does. That is
          // what lets the response be cached immutably and removes the hand-maintained ?v= query
          // string this replaced. Resolved for the ACTIVE theme rather than hardcoded: a theme
          // that supplies no layout.php of its own inherits this one, and would otherwise be
          // served the default theme's stylesheet. ?>
    <link rel="stylesheet" href="<?php echo Str::escape(StyleBundle::url(App::getCurrentSite()->theme ?? 'default')); ?>">
    <meta name="csrf-token" content="<?php echo Str::escape($csrf ?? ''); ?>">
</head>
<body>

    <!-- Header Navigation -->
    <header>
        <h1>
            <a href="/">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="header-logo-icon">
                    <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                    <polyline points="2 17 12 22 22 17"></polyline>
                    <polyline points="2 12 12 17 22 12"></polyline>
                </svg>
                <span class="header-logo-text">Zero Corporate</span>
            </a>
        </h1>
        <nav>
            <a href="/"><?php echo I18n::t('home'); ?></a>
            <a href="/about">About Us</a>
            <a href="/admin/dashboard" class="admin-btn">Admin Console</a>
        </nav>
    </header>

    <!-- Content Grid Wrapper -->
    <div class="wrapper">
        
        <!-- Main Content Column -->
        <main>
            <?php echo $content; ?>
        </main>

        <!-- Sidebar Widgets Column -->
        <aside>
            <!-- WIDGET 1: Pages -->
            <?php if (!empty($sidebarPages)): ?>
                <div class="sidebar-widget">
                    <h3>Our Pages</h3>
                    <ul>
                        <?php foreach ($sidebarPages as $page): ?>
                            <li>
                                <a href="/<?php echo Str::escape($page['slug']); ?>">
                                    <?php echo Str::escape($page['title']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

        </aside>

    </div>

    <!-- Footer -->
    <footer>
        <small>Zero CMS Corporate &copy; <?php echo date('Y'); ?>. Standard corporate licensing rights reserved.</small>
    </footer>

    <?php // hero.js was absent from this list, and it is the only thing that copies a hero
          // video's data-src onto its source element -- so background videos never started on a
          // published page, working solely inside the admin block-builder preview which injects
          // the script itself. Each src carries a digest of the file's contents so the
          // far-future immutable cache header on .js is honest and a deployed fix still lands. ?>
    <?php foreach ([
        'hero',
        'testimonials',
        'accordion',
        'gallery',
        'masonry',
        'sub_pages',
        'form_builder',
    ] as $blockScript): ?>
    <script src="<?php echo Str::escape(AssetVersion::url('/assets/js/blocks/' . $blockScript . '.js')); ?>"></script>
    <?php endforeach; ?>
</body>
</html>
