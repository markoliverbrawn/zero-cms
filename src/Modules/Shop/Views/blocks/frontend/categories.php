<?php
// src/Views/themes/default/blocks/categories.php

use Zero\Modules\Shop\Models\Category;

// Fetch all categories for this site
$categories = [];
if (class_exists(Category::class)) {
    $categories = Category::all();
}
?>
<div class="block-categories-wrapper" style="margin-bottom: 50px;">

  <div class="categories-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; margin-top: 25px;">
    <?php if (!empty($categories)): ?>
      <?php foreach ($categories as $cat): ?>
        <a href="/shop/catalog?category=<?php echo urlencode($cat->slug); ?>" class="category-card" style="text-decoration: none; border: 1px solid var(--border-color, #e2e8f0); border-radius: var(--border-radius, 8px); overflow: hidden; background: var(--card-bg, #0b0f19); transition: transform 0.3s ease, border-color 0.3s ease; display: flex; flex-direction: column;">
          <?php if (!empty($cat->image)): ?>
            <div style="width: 100%; height: 200px; overflow: hidden; background: #000; display: flex; align-items: center; justify-content: center;">
              <img src="<?php echo htmlspecialchars($cat->image); ?>" alt="<?php echo htmlspecialchars($cat->title); ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease;" class="category-card-img">
            </div>
          <?php endif; ?>
          <div style="padding: 20px; text-align: center; flex-grow: 1; display: flex; flex-direction: column; justify-content: center; gap: 8px;">
            <h4 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #ffffff; text-transform: uppercase; letter-spacing: 0.05em;"><?php echo htmlspecialchars($cat->title); ?></h4>
            <?php if (!empty($cat->description)): ?>
              <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted, #64748b); line-height: 1.5;"><?php echo htmlspecialchars($cat->description); ?></p>
            <?php endif; ?>
            <span style="color: var(--accent-color); font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; margin-top: 10px; display: inline-block;">✦ View Collection</span>
          </div>
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <p style="color: var(--text-muted); text-align: center; grid-column: 1 / -1; font-style: italic;">No categories found.</p>
    <?php endif; ?>
  </div>
</div>

<style>
.category-card:hover {
    transform: translateY(-5px);
    border-color: var(--accent-color) !important;
}
.category-card:hover .category-card-img {
    transform: scale(1.05);
}
</style>
