<?php
// src/Modules/Shop/Views/catalog.php

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
                    <a href="/shop/catalog?search=<?php echo urlencode($search); ?>&min_price=<?php echo $minPrice ?: ''; ?>&max_price=<?php echo $maxPrice ?: ''; ?>&sort=<?php echo $sort; ?>" class="sidebar-link sidebar-link-all<?php echo empty($categorySlug) ? ' active' : ''; ?>">
                        All Collections
                    </a>
                </li>
                <?php foreach ($categories as $cat): ?>
                    <li>
                        <a href="/shop/catalog?category=<?php echo Str::escape($cat->slug); ?>&search=<?php echo urlencode($search); ?>&min_price=<?php echo $minPrice ?: ''; ?>&max_price=<?php echo $maxPrice ?: ''; ?>&sort=<?php echo $sort; ?>" class="sidebar-link<?php echo $categorySlug === $cat->slug ? ' active' : ''; ?>">
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
                <div class="form-field">
                    <label class="form-label">Search</label>
                    <?php echo App::makeFormField('text', 'search', [
                        'value' => $search,
                        'attributes' => ['class' => 'form-input', 'placeholder' => 'Keywords...'],
                        'showLabel' => false,
                        'guessHelperTextKey' => false,
                    ])->render(); ?>
                </div>

                <!-- Price bounds -->
                <div class="filter-form-group">
                    <label class="form-label">Price Range</label>
                    <div class="filter-price-range">
                        <?php echo App::makeFormField('number', 'min_price', [
                            'value' => $minPrice > 0 ? $minPrice : '',
                            'attributes' => ['class' => 'filter-input-text', 'placeholder' => 'Min'],
                            'showLabel' => false,
                            'guessHelperTextKey' => false,
                        ])->render(); ?>
                        <span class="price-range-sep">&mdash;</span>
                        <?php echo App::makeFormField('number', 'max_price', [
                            'value' => $maxPrice > 0 ? $maxPrice : '',
                            'attributes' => ['class' => 'filter-input-text', 'placeholder' => 'Max'],
                            'showLabel' => false,
                            'guessHelperTextKey' => false,
                        ])->render(); ?>
                    </div>
                </div>

                <!-- Sorting -->
                <div class="filter-form-group">
                    <label class="form-label">Sort By</label>
                    <?php echo App::makeFormField('select', 'sort', [
                        'value' => $sort,
                        'options' => ['newest' => 'Newest Arrivals', 'price_asc' => 'Price: Low to High', 'price_desc' => 'Price: High to Low'],
                        'attributes' => ['class' => 'filter-select'],
                        'showLabel' => false,
                        'guessHelperTextKey' => false,
                    ])->render(); ?>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-luxe">Apply Filter</button>
                    <a href="/shop/catalog" class="btn-luxe-outline">Reset Filters</a>
                </div>
            </form>
        </div>
    </aside>

    <!-- Catalog Results Column -->
    <div>
        <div class="results-meta">
            <p>
                Showing <strong class="results-count"><?php echo $totalItems; ?></strong> dynamic high-contrast items
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
