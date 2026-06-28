<?php
// src/Views/themes/guide/layout.php

use Zero\Core\App;
use Zero\Core\Env;
use Zero\Database\DB;
use Zero\Support\I18n;

// Fetch dynamic pages and posts for sidebar widgets
$siteId = App::getCurrentSiteId();
$sidebarPages = DB::query("SELECT title, slug FROM pages WHERE status = 'published' AND site_id = ? AND show_in_nav = 1 ORDER BY precedence ASC, title ASC", [$siteId])->fetchAll();
$sidebarPosts = DB::query("SELECT title, slug, created_at FROM blog_posts WHERE status = 'published' AND site_id = ? ORDER BY created_at DESC LIMIT 5", [$siteId])->fetchAll();

// Determine if this is the homepage
$isHomepage = (isset($post) && property_exists($post, 'slug') && $post->slug === '')
           || (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
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
    <title><?php echo htmlspecialchars($isHomepage ? (App::getCurrentSite()->name . ' | Zero Dependencies. Infinite Speed.') : (($post->title ?? 'Docs') . ' | ' . App::getCurrentSite()->name), ENT_QUOTES, 'UTF-8'); ?></title>
    
    <!-- Combined & Optimized Asset-Bundled CSS (1 Request, 0% FOUC, Max Lighthouse Points) -->
    <link rel="stylesheet" href="/assets/css/main-guide.css?v=1.0">
    
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, "UTF-8"); ?>">
</head>
<body>

<!-- TopNavBar -->
<nav class="nav-bar">
    <div class="nav-container">
        <div style="display: flex; align-items: center; gap: 8px;">
            <a href="/" class="logo-group">
                <div class="logo-minimal">Z</div>
                <span><?php echo htmlspecialchars(App::getCurrentSite()->name, ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
        </div>
        
        <div style="display: flex; align-items: center; gap: 24px;">
            <!-- Search bar -->
            <form method="get" action="/search" class="nav-search-form">
                <input type="text" name="q" placeholder="Search docs..." class="nav-search-input" value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                <span class="material-symbols-outlined search-icon">search</span>
            </form>
            
            <div class="nav-links">
                <a class="<?php echo strpos($_SERVER['REQUEST_URI'] ?? '', '/docs') === 0 ? 'active' : ''; ?>" href="/docs">Docs</a>
                <a class="<?php echo strpos($_SERVER['REQUEST_URI'] ?? '', '/blog') === 0 ? 'active' : ''; ?>" href="/blog">Blog</a>
                <a class="nav-link-normal" style="color: var(--on-surface-variant); text-decoration: none;" href="/admin/dashboard">Admin</a>
            </div>
        </div>
        
        <button onclick="window.location.href='/admin/dashboard'" class="btn-deploy">
            Deploy
        </button>
    </div>
</nav>

<main class="<?php echo $isHomepage ? 'homepage-main' : ''; ?>">
<?php if ($isHomepage): ?>
    <!-- Render Homepage dynamic page-builder blocks -->
    <?php echo $content; ?>

    <?php if (Env::get('BENCHMARKING', '1') === '1'): ?>
    <!-- Performance Comparison Section -->
    <section class="benchmarking-section">
        <div class="benchmarking-container">
            <div class="section-header">
                <span class="header-caps">Benchmarking the future</span>
                <h2 class="header-title">Engineered for Velocity</h2>
            </div>
            <div class="benchmarking-grid">
                <div class="comparison-bars">
                    <div class="bar-row">
                        <div class="bar-info">
                            <span>NO_DEP CMS (Core Bundle)</span>
                            <span class="highlight">18 KB</span>
                        </div>
                        <div class="bar-outer">
                            <div class="bar-fill fill-accent"></div>
                        </div>
                    </div>
                    <div class="bar-row opacity-muted">
                        <div class="bar-info">
                            <span>Traditional CMS Frameworks</span>
                            <span>2.4 MB+</span>
                        </div>
                        <div class="bar-outer">
                            <div class="bar-fill fill-muted"></div>
                        </div>
                    </div>
                    <div class="metric-badges-grid">
                        <div class="metric-badge-card border-top-accent">
                            <p class="badge-label">Render Time</p>
                            <p class="badge-val text-accent">0.8ms</p>
                        </div>
                        <div class="metric-badge-card">
                            <p class="badge-label">API Latency</p>
                            <p class="badge-val">12ms</p>
                        </div>
                    </div>
                </div>
                <div class="score-card">
                    <div class="score-inner">
                        <div class="score-value">99</div>
                        <div class="score-label">LIGHTHOUSE SCORE</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    
<?php else: ?>
    <!-- Documentation Dual-Column Layout (Inner pages) -->
    <div class="docs-layout">
        
        <!-- Left Column: Sidebar -->
        <aside class="docs-sidebar">
            <!-- Search Widget (Mobile fallback) -->
            <form method="get" action="/search" class="mobile-search-form">
                <input type="text" name="q" placeholder="Search..." class="nav-search-input" value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                <span class="material-symbols-outlined search-icon">search</span>
            </form>

            <!-- WIDGET 1: Topics Navigation -->
            <?php if (!empty($sidebarPages)): ?>
                <?php
                $tree = [];
                $children = [];
                foreach ($sidebarPages as $page) {
                    if ($page['slug'] === '') continue;
                    $slashPos = strpos($page['slug'], '/');
                    if ($slashPos !== false) {
                        $parentSlug = substr($page['slug'], 0, $slashPos);
                        $children[$parentSlug][] = $page;
                    } else {
                        $tree[$page['slug']] = [
                            'page' => $page,
                            'children' => []
                        ];
                    }
                }
                foreach ($children as $parentSlug => $childList) {
                    if (isset($tree[$parentSlug])) {
                        $tree[$parentSlug]['children'] = $childList;
                    } else {
                        foreach ($childList as $child) {
                            $tree[$child['slug']] = [
                                'page' => $child,
                                'children' => []
                            ];
                        }
                    }
                }
                ?>
                <div class="sidebar-widget">
                    <h3 class="widget-title">Documentation Topics</h3>
                    <ul class="widget-list">
                        <?php foreach ($tree as $parentSlug => $node): ?>
                            <?php $parent = $node['page']; ?>
                            <li class="parent-topic">
                                <a href="/<?php echo htmlspecialchars($parent['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($parent['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </li>
                            <?php foreach ($node['children'] as $child): ?>
                                <li class="child-topic">
                                    <a href="/<?php echo htmlspecialchars($child['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($child['title'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- WIDGET 2: Recent Blog Publications -->
            <?php if (!empty($sidebarPosts)): ?>
                <div class="sidebar-widget">
                    <h3 class="widget-title">Recent Publications</h3>
                    <ul class="widget-list">
                        <?php foreach ($sidebarPosts as $p): ?>
                            <li class="publication-item">
                                <a href="/post/<?php echo htmlspecialchars($p['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                                <small><?php echo date('M d, Y', strtotime($p['created_at'])); ?></small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </aside>

        <!-- Right Column: Content -->
        <div class="docs-content-wrapper glass-panel" style="padding: 24px; border-radius: 2px;">
            <?php echo $content; ?>
        </div>

    </div>
<?php endif; ?>
</main>

<!-- Modern, Large, Structured Footer -->
<footer class="footer">
    <div class="footer-grid">
        <!-- Column 1: Site Info & Branding -->
        <div class="brand-section">
            <div class="brand-logo-row">
                <span class="brand-dot"></span>
                <span><?php echo htmlspecialchars(App::getCurrentSite()->name, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <p>
                A high-performance, zero-dependency, multi-tenant headless CMS and high-contrast e-commerce platform built natively for Serverless edge runtimes.
            </p>
            <p class="copyright-text">
                &copy; <?php echo date('Y'); ?> Zero CMS. All rights reserved by the kernel collective.
            </p>
        </div>

        <!-- Column 2: Documentation (Nav) -->
        <div class="footer-col">
            <h4>Documentation</h4>
            <ul>
                <li><a href="/docs/intro">Getting Started</a></li>
                <li><a href="/docs/benchmarks">Performance Audit</a></li>
                <li><a href="/docs/intro">Architecture Blueprint</a></li>
            </ul>
        </div>

        <!-- Column 3: Ecosystem & Modules -->
        <div class="footer-col">
            <h4>Ecosystem</h4>
            <ul>
                <li><a href="/docs/intro">PCI Commerce Engine</a></li>
                <li><a href="/docs/intro">Security Telemetry</a></li>
                <li><a href="/docs/intro">Zero-Trust Auditing</a></li>
            </ul>
        </div>

        <!-- Column 4: Administrative Back-Office -->
        <div class="footer-col">
            <h4>Back-Office</h4>
            <ul>
                <li><a href="/admin/dashboard">Admin Dashboard</a></li>
                <li><a href="/admin/dashboard">Database Seeder</a></li>
                <li><a href="/admin/dashboard">Scheduled Runner</a></li>
            </ul>
        </div>
    </div>
</footer>

<script src="/assets/js/blocks/testimonials.js"></script>
<script src="/assets/js/blocks/accordion.js"></script>
<script src="/assets/js/blocks/gallery.js"></script>
<script src="/assets/js/blocks/masonry.js"></script>
<script src="/assets/js/blocks/baseline.js"></script>

<script nonce="<?php echo \Zero\Core\App::getNonce(); ?>">
    // Micro-interaction for glass-panel hover effects
    document.querySelectorAll('.glass-panel').forEach(card => {
        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            card.style.setProperty('--mouse-x', `${x}px`);
            card.style.setProperty('--mouse-y', `${y}px`);
        });
    });

    // Zero-overhead Intersection Observer to dynamically draw the glowing H2 neon lines on scroll
    if ('IntersectionObserver' in window) {
        const titleObserver = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    obs.unobserve(entry.target); // Unobserve to reclaim memory and CPU instantly!
                }
            });
        }, {
            threshold: 0.15,
            rootMargin: '0px 0px -40px 0px' // Sleek triggering boundary
        });

        document.querySelectorAll('.block-section-title').forEach(title => {
            titleObserver.observe(title);
        });
    } else {
        // Fallback for legacy user agents
        document.querySelectorAll('.block-section-title').forEach(title => {
            title.classList.add('visible');
        });
    }
</script>

</body>
</html>
