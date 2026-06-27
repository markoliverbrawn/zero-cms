<?php
// src/Views/themes/shop/product.php

use Zero\Support\Security;
?>
<div class="product-detail-layout">
    
    <!-- Gallery Column -->
    <div>
        <!-- Main Image -->
        <div class="main-img-box">
            <img id="main-product-img" src="<?php echo htmlspecialchars($product->main_image); ?>?v=1.2" class="main-img">
        </div>

        <!-- Thumbnails Gallery -->
        <?php if (!empty($gallery)): ?>
            <div class="thumb-gallery">
                <!-- Main image thumbnail -->
                <div class="thumb-box active" data-src="<?php echo htmlspecialchars($product->main_image); ?>?v=1.2">
                    <img src="<?php echo htmlspecialchars($product->main_image); ?>?v=1.2" class="thumb-img">
                </div>
                <?php foreach ($gallery as $img): ?>
                    <div class="thumb-box" data-src="<?php echo htmlspecialchars($img['path']); ?>?v=1.2">
                        <img src="<?php echo htmlspecialchars($img['path']); ?>?v=1.2" class="thumb-img">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Product Details Information Column -->
    <div class="prod-detail-info">
        <span class="prod-studio-tag">Premium Studio Line</span>
        <h2 class="prod-detail-title"><?php echo htmlspecialchars($product->title); ?></h2>
        
        <!-- Interactive Price Indicator -->
        <div class="prod-price-box">
            <span id="active-product-price" class="prod-active-price">$<?php echo number_format($product->price, 2); ?></span>
            <?php if ($product->compare_at_price > 0): ?>
                <span id="active-product-compare" class="prod-compare-price">$<?php echo number_format($product->compare_at_price, 2); ?></span>
            <?php endif; ?>
        </div>

        <div class="prod-desc">
            <?php echo $product->description; ?>
        </div>

        <!-- Add to Cart Form -->
        <form id="add-to-cart-form" method="post" action="/shop/cart">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product->id); ?>">
            
            <!-- Variants selection -->
            <?php if (!empty($variants)): ?>
                <div class="variant-selection">
                    <label class="variant-label">Selected Option Variant</label>
                    <div class="variant-chips-box">
                        <?php foreach ($variants as $idx => $v): ?>
                            <label class="variant-chip-label" style="cursor: pointer;">
                                <input type="radio" name="variant_id" value="<?php echo htmlspecialchars($v->id); ?>" <?php echo $idx === 0 ? 'checked' : ''; ?> class="variant-chip-radio">
                                <span class="variant-chip <?php echo $idx === 0 ? 'selected' : ''; ?>" data-variant-price="<?php echo $v->price; ?>" data-variant-sku="<?php echo htmlspecialchars($v->sku); ?>" data-variant-stock="<?php echo $v->stock; ?>">
                                    <?php echo htmlspecialchars($v->title); ?>
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
                    <span id="meta-variant-sku" class="prod-meta-val"><?php echo htmlspecialchars($variants ? $variants[0]->sku : $product->sku); ?></span>
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

<!-- Client-side Interactive Gallery & Variants Controller -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Gallery Thumbnail Switcher
    const mainImg = document.getElementById('main-product-img');
    const thumbBoxes = document.querySelectorAll('.thumb-box');

    thumbBoxes.forEach(thumb => {
        thumb.addEventListener('click', () => {
            // Prune active class from other items
            thumbBoxes.forEach(item => {
                item.classList.remove('active');
                item.style.borderColor = 'var(--border-color)';
            });
            // Highlight selected
            thumb.classList.add('active');
            thumb.style.borderColor = 'var(--accent-color)';
            
            // Swap main source
            mainImg.src = thumb.getAttribute('data-src');
        });
    });

    // 2. Quantity buttons
    const qtyInput = document.getElementById('qty-input');
    const qtyMinus = document.getElementById('qty-minus');
    const qtyPlus = document.getElementById('qty-plus');

    qtyMinus.addEventListener('click', () => {
        const val = parseInt(qtyInput.value);
        if (val > 1) qtyInput.value = val - 1;
    });

    qtyPlus.addEventListener('click', () => {
        const val = parseInt(qtyInput.value);
        qtyInput.value = val + 1;
    });

    // 3. Variant selection updates (SKU, Price, Stock warning!)
    const variantRadios = document.querySelectorAll('.variant-chip-radio');
    const variantChips = document.querySelectorAll('.variant-chip');
    const activePriceEl = document.getElementById('active-product-price');
    const activeSkuEl = document.getElementById('meta-variant-sku');
    const activeStockEl = document.getElementById('meta-variant-stock');
    const submitBtn = document.getElementById('add-to-cart-submit');

    variantChips.forEach((chip, idx) => {
        const radio = variantRadios[idx];
        chip.addEventListener('click', () => {
            // Clear selections
            variantChips.forEach(v => {
                v.classList.remove('selected');
                v.style.backgroundColor = 'transparent';
                v.style.color = 'var(--text-color)';
                v.style.borderColor = 'var(--border-color)';
            });
            // Mark selected
            chip.classList.add('selected');
            chip.style.backgroundColor = 'var(--accent-color)';
            chip.style.color = '#000000';
            chip.style.borderColor = 'var(--accent-color)';
            radio.checked = true;

            // Update interactive indicators
            const price = parseFloat(chip.getAttribute('data-variant-price'));
            const sku = chip.getAttribute('data-variant-sku');
            const stock = parseInt(chip.getAttribute('data-variant-stock'));

            activePriceEl.innerHTML = '$' + price.toFixed(2);
            activeSkuEl.innerHTML = sku;

            if (stock === 0) {
                activeStockEl.innerHTML = 'OUT OF STOCK';
                activeStockEl.style.color = '#ef4444';
                submitBtn.disabled = true;
                submitBtn.innerHTML = 'OUT OF STOCK';
                submitBtn.style.opacity = '0.5';
            } else {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.innerHTML = 'Add To Shop Cart';
                if (stock < 5) {
                    activeStockEl.innerHTML = 'ONLY ' + stock + ' LEFT IN STOCK';
                    activeStockEl.style.color = '#f59e0b';
                } else {
                    activeStockEl.innerHTML = 'IN STOCK (' + stock + ' units)';
                    activeStockEl.style.color = 'var(--accent-color)';
                }
            }
        });
    });
});
</script>
