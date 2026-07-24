<?php
// src/Views/themes/shop/catalog.php

use Zero\Core\App;
use Zero\Support\Str;
?>
<div class="catalog-layout">
    
    <!-- Filter Sidebar Column -->
    <aside class="sidebar-filter">
        
        <!-- Categories Block -->
        <div>
            <h3 class="sidebar-section-title">Categories</h3>
            <ul class="sidebar-list">
                <li>
                    <a href="/shop/catalog?search=<?php echo urlencode($search); ?>&min_price=<?php echo $minPrice ?: ''; ?>&max_price=<?php echo $maxPrice ?: ''; ?>&sort=<?php echo $sort; ?>" class="sidebar-link" style="color: <?php echo empty($categorySlug) ? 'var(--accent-color)' : 'var(--text-muted)'; ?>; font-weight: <?php echo empty($categorySlug) ? 'bold' : '600'; ?>; text-transform: uppercase; letter-spacing: 0.05em;">
                        ✦ All Collections
                    </a>
                </li>
                <?php foreach ($categories as $cat): ?>
                    <li>
                        <a href="/shop/catalog?category=<?php echo Str::escape($cat->slug); ?>&search=<?php echo urlencode($search); ?>&min_price=<?php echo $minPrice ?: ''; ?>&max_price=<?php echo $maxPrice ?: ''; ?>&sort=<?php echo $sort; ?>" class="sidebar-link" style="color: <?php echo $categorySlug === $cat->slug ? 'var(--accent-color)' : 'var(--text-muted)'; ?>; font-weight: <?php echo $categorySlug === $cat->slug ? 'bold' : '500'; ?>;">
                            <?php echo Str::escape($cat->title); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Filter Studio Forms -->
        <div>
            <h3 class="sidebar-section-title">Filter Studio</h3>
            
            <form method="get" action="/shop/catalog">
                <input type="hidden" name="category" value="<?php echo Str::escape($categorySlug); ?>">
                
                <!-- Search -->
                <div class="filter-form-group">
                    <label class="checkout-label">Search</label>
                    <input name="search" value="<?php echo Str::escape($search); ?>" placeholder="Keywords..." class="filter-input-text">
                </div>

                <!-- Price bounds -->
                <div class="filter-form-group">
                    <label class="checkout-label">Price Range</label>
                    <div class="filter-price-range">
                        <input name="min_price" value="<?php echo $minPrice > 0 ? $minPrice : ''; ?>" type="number" placeholder="Min" class="filter-input-text">
                        <span style="color: var(--border-color);">—</span>
                        <input name="max_price" value="<?php echo $maxPrice > 0 ? $maxPrice : ''; ?>" type="number" placeholder="Max" class="filter-input-text">
                    </div>
                </div>

                <!-- Sorting -->
                <div class="filter-form-group" style="margin-bottom: 30px;">
                    <label class="checkout-label">Sort By</label>
                    <select name="sort" class="filter-select">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest Arrivals</option>
                        <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                    </select>
                </div>

                <button type="submit" class="btn-luxe" style="width: 100%; margin-bottom: 12px;">Apply Filter</button>
                <a href="/shop/catalog" class="btn-luxe-outline" style="width: 100%; display: block;">Reset Filters</a>
            </form>
        </div>
    </aside>

    <!-- Catalog Results Column -->
    <div>
        <div class="results-meta">
            <p>
                Showing <strong style="color: #fff;"><?php echo $totalItems; ?></strong> dynamic high-contrast items
            </p>
            <?php if ($activeCategory): ?>
                <span class="results-badge">Category: <?php echo Str::escape($activeCategory->title); ?></span>
            <?php endif; ?>
        </div>

        <?php if (empty($products)): ?>
            <div class="results-empty">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="results-empty-icon">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="8" y1="12" x2="16" y2="12"></line>
                </svg>
                <p class="results-empty-title">No items found matching filter criteria.</p>
                <p class="results-empty-desc">Try adjusting price limits or clearing keyword parameters.</p>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <a href="/shop/product/<?php echo Str::escape($product->slug); ?>" class="product-card">
                        <div class="product-card-image">
                            <img src="<?php echo Str::escape($product->main_image); ?>?v=1.2" alt="<?php echo Str::escape($product->title); ?>">
                        </div>
                        <div class="product-card-content">
                            <h4 class="product-card-title"><?php echo Str::escape($product->title); ?></h4>
                            <div class="product-card-price-container">
                                <span class="product-card-price">$<?php echo number_format($product->price, 2); ?></span>
                                <?php if ($product->compare_at_price > 0): ?>
                                    <span class="product-card-compare">$<?php echo number_format($product->compare_at_price, 2); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Catalog Pagination Block -->
            <?php echo App::renderPagination([
                'currentPage' => $currentPage,
                'totalPages' => $totalPages
            ], '/shop/catalog', $_GET); ?>
        <?php endif; ?>
    </div>
</div>
