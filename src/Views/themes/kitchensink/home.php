<?php
// src/Views/themes/kitchensink/home.php
// Renders the Luxe Cyber Shop featured products landing page.

use Zero\Core\App;
?>
<div class="shop-home-container">
  <h2 style="font-size: 2.2rem; margin-bottom: 1.5rem; background: linear-gradient(90deg, var(--neon-cyan), var(--neon-pink)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Luxe Cyber Shop</h2>

  <p class="text-muted" style="font-size: 1.15rem; margin-bottom: 3rem;">
    Welcome to our integrated multi-tenant Luxe Cyberware Emporium. Explore our high-contrast, premium-grade neon hardware and variable apparel lines.
  </p>

  <h3 style="font-size: 1.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem; color: var(--neon-cyan);">Featured Showcase</h3>

  <?php if (empty($featuredProducts)): ?>
    <p class="text-muted">No products have been registered yet on this showroom.</p>
  <?php else: ?>
    <div class="shop-grid">
      <?php foreach ($featuredProducts as $prod): ?>
        <div class="product-card">
          <div class="product-image-box">
            <img src="<?php echo htmlspecialchars($prod->main_image ?? '/assets/svgs/image.svg', ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($prod->title); ?>" />
            <span class="product-badge">New</span>
          </div>
          <div class="product-info">
            <h4>
              <a href="/shop/product/<?php echo htmlspecialchars($prod->slug, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($prod->title, ENT_QUOTES, 'UTF-8'); ?>
              </a>
            </h4>
            <div class="product-sku">SKU: <?php echo htmlspecialchars($prod->sku ?? 'N/A'); ?></div>
            <div class="product-pricing">
              <span class="price-value">$<?php echo number_format($prod->price, 2); ?></span>
              <?php if ($prod->compare_at_price > 0): ?>
                <span class="price-compare">$<?php echo number_format($prod->compare_at_price, 2); ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div style="margin-top: 4rem; text-align: center; margin-bottom: 4rem;">
    <a href="/shop/catalog" class="admin-btn">
      Browse Entire Catalog &rarr;
    </a>
  </div>

  <!-- Dynamic Page Builder Content Showcase (including Showcase Grid Galleries) -->
  <?php if (!empty($post) && !empty($post->content)): ?>
    <div style="margin-top: 5rem; border-top: 2px dashed var(--border-color); padding-top: 4rem;">
        <?php 
        // Re-use the master block-renderer template cleanly!
        include APPLICATION_ROOT . '/src/Views/themes/kitchensink/post.php'; 
        ?>
    </div>
  <?php endif; ?>
</div>
