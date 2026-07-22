<?php
// src/Views/themes/shop/cart.php

// Apply promo code discount if entered in session
$coupon = $_GET['coupon'] ?? '';
$discount = 0;
$discountMsg = '';

if ($coupon === 'ZERO_LUXE') {
    $discount = $subtotal * 0.10;
    $discountMsg = 'Coupon "ZERO_LUXE" (10% Off) applied successfully!';
} elseif (!empty($coupon)) {
    $discountMsg = 'Invalid coupon code.';
}
$total = $subtotal - $discount;
?>
<h2 style="font-size: 1.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 2px solid var(--border-color); padding-bottom: 12px; margin-top: 0; margin-bottom: 35px;">Shopping Cart</h2>

<?php if (empty($cart)): ?>
    <div style="border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 80px 40px; text-align: center; color: var(--text-muted); background: var(--card-bg);">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="results-empty-icon">
            <circle cx="9" cy="21" r="1"></circle>
            <circle cx="20" cy="21" r="1"></circle>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
        </svg>
        <p class="results-empty-title" style="margin-bottom: 5px;">Your Shopping Cart is empty.</p>
        <p class="results-empty-desc" style="margin-bottom: 25px;">Explore our catalog to find exceptional minimalist masterpieces.</p>
        <a href="/shop/catalog" class="btn-luxe">Continue Shopping</a>
    </div>
<?php else: ?>
    <div class="cart-layout">
        
        <!-- Cart Items List -->
        <div class="cart-items-column">
            <?php foreach ($cart as $key => $item): ?>
                <div class="cart-item-card">
                    <!-- Image -->
                    <div class="cart-item-img-box">
                        <img src="<?php echo htmlspecialchars($item['main_image']); ?>?v=1.2" class="cart-item-img">
                    </div>
                    <!-- Details -->
                    <div class="cart-item-details">
                        <h4 class="cart-item-title"><a href="/shop/product/<?php echo htmlspecialchars($item['slug']); ?>"><?php echo htmlspecialchars($item['title']); ?></a></h4>
                        <?php if (!empty($item['variant_title'])): ?>
                            <span class="cart-item-variant"><?php echo htmlspecialchars($item['variant_title']); ?></span>
                        <?php endif; ?>
                        <span class="cart-item-sku">SKU: <?php echo htmlspecialchars($item['sku']); ?></span>
                    </div>
                    <!-- Quantity & Price Form -->
                    <div class="cart-item-actions">
                        <span class="cart-item-price">$<?php echo number_format($item['price'], 2); ?></span>
                        
                        <!-- Update Qty Form -->
                        <form method="post" action="/shop/cart" class="cart-qty-form">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="item_key" value="<?php echo htmlspecialchars($key); ?>">
                            <button type="submit" name="quantity" value="<?php echo $item['quantity'] - 1; ?>" class="qty-btn">-</button>
                            <span class="cart-qty-val"><?php echo $item['quantity']; ?></span>
                            <button type="submit" name="quantity" value="<?php echo $item['quantity'] + 1; ?>" class="qty-btn">+</button>
                        </form>

                        <!-- Remove Form -->
                        <form method="post" action="/shop/cart">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="item_key" value="<?php echo htmlspecialchars($key); ?>">
                            <button type="submit" class="cart-remove-btn">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Order Summary Box -->
        <aside class="cart-summary-column">
            <h3 class="sidebar-section-title">Order Summary</h3>
            
            <div class="cart-summary-box">
                <div class="cart-summary-row">
                    <span class="cart-summary-label">Cart Subtotal:</span>
                    <span class="cart-summary-val">$<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <?php if ($discount > 0): ?>
                    <div class="cart-summary-row" style="color: var(--accent-color);">
                        <span>Coupon Discount:</span>
                        <span class="cart-summary-val">-$<?php echo number_format($discount, 2); ?></span>
                    </div>
                <?php endif; ?>
                <div class="cart-summary-row">
                    <span class="cart-summary-label">Shipping Delivery:</span>
                    <span class="cart-summary-val" style="color: var(--accent-color);"><?php echo $subtotal >= 150 ? 'FREE' : '$15.00'; ?></span>
                </div>
            </div>

            <!-- Coupon Code Entry Form -->
            <form method="get" action="/shop/cart" class="coupon-entry-form">
                <input name="coupon" value="<?php echo htmlspecialchars($coupon); ?>" placeholder="Coupon Code" class="coupon-input">
                <button type="submit" class="btn-apply-coupon">Apply</button>
            </form>

            <?php if (!empty($discountMsg)): ?>
                <div class="coupon-msg <?php echo $discount > 0 ? 'coupon-success' : 'coupon-error'; ?>">
                    <?php echo htmlspecialchars($discountMsg); ?>
                </div>
            <?php endif; ?>

            <div class="cart-summary-total">
                <span>Total Amount:</span>
                <span class="receipt-total-val">$<?php echo number_format($total + ($subtotal >= 150 ? 0 : 15), 2); ?></span>
            </div>

            <a href="/shop/checkout" class="btn-luxe">Proceed to Checkout</a>
        </aside>

    </div>
<?php endif; ?>
