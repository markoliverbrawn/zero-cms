<?php
// src/Views/themes/default/layout.php

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Support\I18n;

// Fetch dynamic pages and posts for corporate sidebar
$siteId = App::getCurrentSiteId();
$sidebarPages = DB::query("SELECT title, slug FROM pages WHERE status = 'published' AND site_id = ? AND show_in_nav = 1 ORDER BY precedence ASC, title ASC", [$siteId])->fetchAll();
$sidebarPosts = DB::query("SELECT title, slug FROM blog_posts WHERE status = 'published' AND site_id = ? ORDER BY created_at DESC LIMIT 5", [$siteId])->fetchAll();
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
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8'); ?>"/>
    <title>Zero CMS Corporate Portal</title>
    <link rel="icon" type="image/svg+xml" href="/assets/favicons/corporate.svg">
    <link rel="stylesheet" href="/assets/css/main-default.css?v=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, "UTF-8"); ?>">
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
                                <a href="/<?php echo htmlspecialchars($page['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- WIDGET 2: Recent Corporate Blog Posts -->
            <?php if (!empty($sidebarPosts)): ?>
                <div class="sidebar-widget">
                    <h3>Recent News</h3>
                    <ul>
                        <?php foreach ($sidebarPosts as $post): ?>
                            <li>
                                <a href="/post/<?php echo htmlspecialchars($post['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?>
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

    <script src="/assets/js/blocks/testimonials.js"></script>
    <script src="/assets/js/blocks/accordion.js"></script>
    <script src="/assets/js/blocks/gallery.js"></script>
    <script src="/assets/js/blocks/masonry.js"></script>
    <script src="/assets/js/blocks/sub_pages.js"></script>
    <script src="/assets/js/blocks/form_builder.js"></script>
</body>
</html>
