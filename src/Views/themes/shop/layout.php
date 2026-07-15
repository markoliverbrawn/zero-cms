<?php
// src/Views/themes/shop/layout.php

use Zero\Core\App;
use Zero\Support\Security;

// Calculate total cart items count
$cartCount = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += $item['quantity'];
    }
}
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
        if (empty($metaDescription) && isset($product) && is_object($product)) {
            $metaDescription = $product->description ?? '';
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
    <title>Luxe Emporium - Modern Design & Style</title>
    <link rel="icon" type="image/svg+xml" href="/assets/favicons/shop.svg">
    
    <!-- External Luxe E-Commerce & Account CSS Stylesheets -->
    <link rel="stylesheet" href="/assets/css/main-shop.css?v=1.0">
    <link rel="stylesheet" href="/assets/css/auth.css?v=1.3">
</head>
<body>

    <!-- Header Navigation -->
    <header class="main-header">
        <h1 class="header-logo">
            <a href="/" class="header-logo-link">
                <span class="header-logo-svg" style="width: 24px; height: 24px; display: inline-flex;">
                    <style>.header-logo-svg svg { width: 24px; height: 24px; display: block; }</style>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
  <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
</svg>                </span>
                <span>LUXE EMPORIUM</span>
            </a>
        </h1>
        <nav class="header-nav">
            <a href="/shop/catalog">Catalog</a>
            <a href="/shop/cart" class="cart-btn">
                Cart <span class="cart-badge-container">(<span class="cart-count-badge"><?php echo $cartCount; ?></span>)</span>
            </a>
            <?php if (!empty($_SESSION['user_id'])): ?>
                <a href="/shop/account" class="account-btn">My Account</a>
                <form method="post" action="/admin/logout" class="logout-form" style="display: inline; margin: 0;">
                    <?php echo Security::csrfInput(); ?>
                    <button type="submit" class="logout-btn" style="background: none; border: none; padding: 0; font-family: inherit; font-size: inherit; color: var(--text-color, #ffffff); cursor: pointer; text-decoration: underline; display: inline;">Logout</button>
                </form>
            <?php else: ?>
                <a href="/admin/login" class="login-btn">Login</a>
            <?php endif; ?>
        </nav>
    </header>

    <!-- Main Content Container -->
    <div class="container">
        <?php echo $content; ?>
    </div>

    <!-- Luxury Footer Columns -->
    <footer class="main-footer" style="padding: 60px 0 40px; background-color: #050505; border-top: 1px solid #111; color: #64748b; font-family: monospace;">
        <div class="footer-columns" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 40px; max-width: 1200px; margin: 0 auto 50px; padding: 0 20px; text-align: left;">
            <style>
                .footer-col ul li a:hover {
                    color: #d4af37 !important;
                }
            </style>
            <!-- Column 1: Explore -->
            <div class="footer-col">
                <h4 style="color: #ffffff; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.15em; margin: 0 0 20px 0; border-bottom: 1px solid #1e293b; padding-bottom: 10px;">Explore</h4>
                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px;">
                    <li><a href="/shop/catalog" style="color: #64748b; text-decoration: none; font-size: 0.8rem; letter-spacing: 0.05em; transition: color 0.15s ease;">Shop Catalog</a></li>
                    <li><a href="/blog" style="color: #64748b; text-decoration: none; font-size: 0.8rem; letter-spacing: 0.05em; transition: color 0.15s ease;">Studio Journal (Blog)</a></li>
                    <li><a href="/bespoke-commissions" style="color: #64748b; text-decoration: none; font-size: 0.8rem; letter-spacing: 0.05em; transition: color 0.15s ease;">Bespoke Commissions</a></li>
                </ul>
            </div>

            <!-- Column 2: The Brand -->
            <div class="footer-col">
                <h4 style="color: #ffffff; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.15em; margin: 0 0 20px 0; border-bottom: 1px solid #1e293b; padding-bottom: 10px;">The Brand</h4>
                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px;">
                    <li><a href="/about-us" style="color: #64748b; text-decoration: none; font-size: 0.8rem; letter-spacing: 0.05em; transition: color 0.15s ease;">About Luxe Emporium</a></li>
                    <li><a href="/studio-team" style="color: #64748b; text-decoration: none; font-size: 0.8rem; letter-spacing: 0.05em; transition: color 0.15s ease;">Our Studio Team</a></li>
                    <li><a href="/studio-interior" style="color: #64748b; text-decoration: none; font-size: 0.8rem; letter-spacing: 0.05em; transition: color 0.15s ease;">Studio Interior</a></li>
                    <li><a href="/sustainability" style="color: #64748b; text-decoration: none; font-size: 0.8rem; letter-spacing: 0.05em; transition: color 0.15s ease;">Sustainability Sourcing</a></li>
                </ul>
            </div>

            <!-- Column 3: Customer Care -->
            <div class="footer-col">
                <h4 style="color: #ffffff; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.15em; margin: 0 0 20px 0; border-bottom: 1px solid #1e293b; padding-bottom: 10px;">Care & Support</h4>
                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px;">
                    <li><a href="/faq" style="color: #64748b; text-decoration: none; font-size: 0.8rem; letter-spacing: 0.05em; transition: color 0.15s ease;">FAQ &amp; Inquiries</a></li>
                    <li><a href="/luxe-care" style="color: #64748b; text-decoration: none; font-size: 0.8rem; letter-spacing: 0.05em; transition: color 0.15s ease;">Luxe Care Instructions</a></li>
                    <li><a href="/size-guide" style="color: #64748b; text-decoration: none; font-size: 0.8rem; letter-spacing: 0.05em; transition: color 0.15s ease;">Size Guide</a></li>
                    <li><a href="/shipping" style="color: #64748b; text-decoration: none; font-size: 0.8rem; letter-spacing: 0.05em; transition: color 0.15s ease;">Shipping &amp; Logistics</a></li>
                    <li><a href="/terms" style="color: #64748b; text-decoration: none; font-size: 0.8rem; letter-spacing: 0.05em; transition: color 0.15s ease;">Terms &amp; Conditions</a></li>
                </ul>
            </div>
        </div>

        <div style="border-top: 1px solid #111; padding-top: 25px; text-align: center; max-width: 1200px; margin: 0 auto; padding-left: 20px; padding-right: 20px; font-size: 0.75rem;">
            <p style="margin: 0 0 10px 0;">&copy; 2026 Luxe Emporium. Dynamic Multi-Tenant E-Commerce Showcase. Co-composed dynamically.</p>
            <p style="margin: 0;"><a href="/admin/login" style="color: #475569; text-decoration: none; transition: color 0.15s ease;">Staff Administrator Portal</a></p>
        </div>
    </footer>

    <script src="/assets/js/blocks/testimonials.js"></script>
    <script src="/assets/js/blocks/accordion.js"></script>
    <script src="/assets/js/blocks/gallery.js"></script>
    <script src="/assets/js/blocks/masonry.js"></script>
    <script src="/assets/js/blocks/sub_pages.js"></script>
    <script src="/assets/js/blocks/form_builder.js"></script>
</body>
</html>
