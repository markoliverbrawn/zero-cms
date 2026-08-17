<?php
// src/Views/themes/kitchensink/layout.php

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Support\I18n;
use Zero\Support\Str;

// Fetch dynamic pages, blog posts, and shop categories for the Kitchen Sink widgets
$siteId = App::getCurrentSiteId();
$sidebarPages = DB::query("SELECT title, slug FROM pages WHERE status = 'published' AND site_id = ? AND show_in_nav = 1 ORDER BY precedence ASC, title ASC", [$siteId])->fetchAll();
$sidebarPosts = DB::query("SELECT title, slug FROM blog_posts WHERE status = 'published' AND site_id = ? ORDER BY created_at DESC LIMIT 5", [$siteId])->fetchAll();
$sidebarCategories = DB::query("SELECT title, slug FROM shop_categories WHERE site_id = ? ORDER BY title ASC LIMIT 5", [$siteId])->fetchAll();
?>
<!doctype html>
<html lang="<?php echo I18n::getLang(); ?>">
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
    <title>Zero CMS — Kitchen Sink Showroom</title>
    <link rel="icon" type="image/svg+xml" href="/assets/favicons/kitchensink.svg">
    <link rel="stylesheet" href="/assets/css/main-kitchensink.css?v=1.0">
    <meta name="csrf-token" content="<?php echo Str::escape($csrf ?? ''); ?>">
</head>
<body>

    <!-- Header Navigation -->
    <header>
        <div class="header-container">
            <h1>
                <a href="/">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="header-logo-icon">
                        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                        <polyline points="2 17 12 22 22 17"></polyline>
                        <polyline points="2 12 12 17 22 12"></polyline>
                    </svg>
                    <span class="header-logo-text">Kitchen Sink</span>
                </a>
            </h1>
            <nav>
                <a href="/">Home Showcase</a>
                <a href="/blog">Blog News</a>
                <a href="/shop">Luxe Shop</a>
                <a href="/admin/dashboard" class="admin-btn">Admin Panel</a>
            </nav>
        </div>
    </header>

    <!-- Main Content Container Grid -->
    <div class="wrapper">
        
        <!-- Main Panel -->
        <main>
            <?php echo $content; ?>
        </main>

        <!-- Sidebar Widgets Panel -->
        <aside>
            <!-- WIDGET 0: Search Widget -->
            <div class="sidebar-widget">
                <h3>Search Showroom</h3>
                <form method="get" action="/search" class="sidebar-search-container">
                    <?php echo App::makeFormField('text', 'q', [
                        'value' => $_GET['q'] ?? '',
                        'required' => true,
                        'attributes' => ['class' => 'sidebar-search-input', 'placeholder' => 'Enter keywords...'],
                        'showLabel' => false,
                        'guessHelperTextKey' => false,
                    ])->render(); ?>
                    <button type="submit" class="admin-btn sidebar-search-btn">Go</button>
                </form>
            </div>

            <!-- WIDGET 1: Pages Directory -->
            <?php if (!empty($sidebarPages)): ?>
                <div class="sidebar-widget">
                    <h3>Explore Pages</h3>
                    <ul>
                        <?php foreach ($sidebarPages as $page): ?>
                            <li>
                                <a href="/<?php echo Str::escape($page['slug']); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--neon-cyan);">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                    </svg>
                                    <?php echo Str::escape($page['title']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- WIDGET 2: Shop Categories Directory -->
            <?php if (!empty($sidebarCategories)): ?>
                <div class="sidebar-widget">
                    <h3>Shop Categories</h3>
                    <ul>
                        <?php foreach ($sidebarCategories as $cat): ?>
                            <li>
                                <a href="/shop/catalog?category=<?php echo Str::escape($cat['slug']); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--neon-pink);">
                                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                                        <line x1="7" y1="7" x2="7.01" y2="7"></line>
                                    </svg>
                                    <?php echo Str::escape($cat['title']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- WIDGET 3: Recent Editorial News -->
            <?php if (!empty($sidebarPosts)): ?>
                <div class="sidebar-widget">
                    <h3>Recent News</h3>
                    <ul>
                        <?php foreach ($sidebarPosts as $post): ?>
                            <li>
                                <a href="/post/<?php echo Str::escape($post['slug']); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--accent-color);">
                                        <path d="M12 20h9"></path>
                                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                                    </svg>
                                    <?php echo Str::escape($post['title']); ?>
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
        <small>Zero CMS Kitchen Sink &copy; <?php echo date('Y'); ?>. Fully loaded feature-complete multi-tenant showroom platform.</small>
    </footer>

    <!-- Load Block builder interaction scripts -->
    <script src="/assets/js/blocks/accordion.js"></script>
    <script src="/assets/js/blocks/testimonials.js"></script>
    <script src="/assets/js/blocks/gallery.js"></script>
    <script src="/assets/js/blocks/masonry.js"></script>
    <script src="/assets/js/blocks/sub_pages.js"></script>
    <script src="/assets/js/blocks/form_builder.js"></script>
    <script src="/assets/js/shop-product.js"></script>
</body>
</html>
