<?php
// src/Views/themes/shop/product.php

use Zero\Support\Security;
use Zero\Support\Str;
?>
<div class="product-detail-layout">
    
    <!-- Gallery Column -->
    <div>
        <!-- Main Image -->
        <div class="main-img-box">
            <img id="main-product-img" src="<?php echo Str::escape($product->main_image); ?>?v=1.2" class="main-img">
        </div>

        <!-- Thumbnails Gallery -->
        <?php if (!empty($gallery)): ?>
            <div class="thumb-gallery">
                <!-- Main image thumbnail -->
                <div class="thumb-box active" data-src="<?php echo Str::escape($product->main_image); ?>?v=1.2">
                    <img src="<?php echo Str::escape($product->main_image); ?>?v=1.2" class="thumb-img">
                </div>
                <?php foreach ($gallery as $img): ?>
                    <div class="thumb-box" data-src="<?php echo Str::escape($img['path']); ?>?v=1.2">
                        <img src="<?php echo Str::escape($img['path']); ?>?v=1.2" class="thumb-img">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Product Details Information Column -->
    <div class="prod-detail-info">
        <span class="prod-studio-tag">Premium Studio Line</span>
        <h2 class="prod-detail-title"><?php echo Str::escape($product->title); ?></h2>
        
        <!-- Interactive Price Indicator -->
        <div class="prod-price-box">
            <span id="active-product-price" class="prod-active-price">$<?php echo number_format($product->price, 2); ?></span>
            <?php if ($product->compare_at_price > 0): ?>
                <span id="active-product-compare" class="prod-compare-price">$<?php echo number_format($product->compare_at_price, 2); ?></span>
            <?php endif; ?>
        </div>

        <div class="prod-desc">
            <?php echo Security::sanitizeHtml($product->description); ?>
        </div>

        <!-- Add to Cart Form -->
        <form id="add-to-cart-form" method="post" action="/shop/cart">
            <input type="hidden" name="csrf" value="<?php echo Str::escape($csrf ?? ''); ?>">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?php echo Str::escape($product->id); ?>">
            
            <!-- Variants selection -->
            <?php if (!empty($variants)): ?>
                <div class="variant-selection">
                    <label class="variant-label">Selected Option Variant</label>
                    <div class="variant-chips-box">
                        <?php foreach ($variants as $idx => $v): ?>
                            <label class="variant-chip-label" style="cursor: pointer;">
                                <input type="radio" name="variant_id" value="<?php echo Str::escape($v->id); ?>" <?php echo $idx === 0 ? 'checked' : ''; ?> class="variant-chip-radio">
                                <span class="variant-chip <?php echo $idx === 0 ? 'selected' : ''; ?>" data-variant-price="<?php echo $v->price; ?>" data-variant-sku="<?php echo Str::escape($v->sku); ?>" data-variant-stock="<?php echo $v->stock; ?>">
                                    <?php echo Str::escape($v->title); ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Interactive Meta Details (SKU, Stock Status) -->
            <div class="prod-meta-box">
                <div class="prod-meta-row">
                    <span>SKU Product Variant:</span>
                    <span id="meta-variant-sku" class="prod-meta-val"><?php echo Str::escape($variants ? $variants[0]->sku : $product->sku); ?></span>
                </div>
                <div class="prod-meta-row">
                    <span>Inventory Status:</span>
                    <span id="meta-variant-stock" style="font-weight: 800; color: var(--accent-color);">
                        <?php 
                        if ($variants) {
                            $stock = $variants[0]->stock;
                            if ($stock === 0) echo "OUT OF STOCK";
                            elseif ($stock < 5) echo "ONLY {$stock} LEFT IN STOCK";
                            else echo "IN STOCK ({$stock} units)";
                        } else {
                            echo "IN STOCK";
                        }
                        ?>
                    </span>
                </div>
            </div>

            <!-- Quantity & Add Button -->
            <div class="qty-add-row">
                <div class="qty-picker">
                    <button type="button" id="qty-minus" class="qty-btn">-</button>
                    <input id="qty-input" name="quantity" value="1" readonly class="qty-val">
                    <button type="button" id="qty-plus" class="qty-btn">+</button>
                </div>

                <button id="add-to-cart-submit" type="submit" class="btn-luxe" style="flex-grow: 1; height: 50px; font-size: 0.85rem; letter-spacing: 0.15em;">Add To Shop Cart</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Active styling of thumbnail gallery items */
.thumb-box.active {
    border-color: var(--accent-color) !important;
}
/* Active variant chip indicator styling */
.variant-chip.selected {
    border-color: var(--accent-color) !important;
    background-color: var(--accent-color) !important;
    color: #000000 !important;
}
</style>
