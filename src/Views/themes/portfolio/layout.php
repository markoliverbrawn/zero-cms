<?php
// src/Views/themes/portfolio/layout.php
use Zero\Core\App;
use Zero\Support\I18n;
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
    <title>ZERO Portfolio - Dynamic Design Agency</title>
    <link rel="icon" type="image/svg+xml" href="/assets/favicons/portfolio.svg">
    <link rel="stylesheet" href="/assets/css/main-portfolio.css?v=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, "UTF-8"); ?>">
</head>
<body>

    <!-- Header Navigation -->
    <header>
        <h1>
            <a href="/">
                <span class="header-logo-bracket">[</span>
                ZERO STUDIO
                <span class="header-logo-bracket">]</span>
            </a>
        </h1>
        <nav>
            <?php $currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH); ?>
            <a href="/" class="<?php echo ($currentUri === '/' || $currentUri === '') ? 'active' : ''; ?>">Studio</a>
            <a href="/about" class="<?php echo ($currentUri === '/about') ? 'active' : ''; ?>">Agency</a>
            <a href="/showcase" class="<?php echo ($currentUri === '/showcase') ? 'active' : ''; ?>">Showcase</a>
            <a href="/admin/dashboard" style="color: #ffffff; border: 1px solid #ffffff; padding: 6px 14px; border-radius: var(--border-radius);">Portal</a>
        </nav>
    </header>

    <!-- Content Wrapper -->
    <div class="portfolio-wrapper">
        <main>
            <?php echo $content; ?>
        </main>
    </div>

    <!-- Footer -->
    <footer>
        &copy; <?php echo date('Y'); ?> Zero Studio. Co-engineered digitally. All rights reserved.
    </footer>

    <script src="/assets/js/blocks/testimonials.js"></script>
    <script src="/assets/js/blocks/accordion.js"></script>
    <script src="/assets/js/blocks/gallery.js"></script>
    <script src="/assets/js/blocks/masonry.js"></script>
    <script src="/assets/js/blocks/sub_pages.js"></script>
</body>
</html>
