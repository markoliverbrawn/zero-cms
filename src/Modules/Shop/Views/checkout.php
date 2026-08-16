<?php
// src/Modules/Shop/Views/checkout.php

use Zero\Support\Str;

$qualifiesForFreeShipping = $subtotal >= $freeShippingThreshold;
$shippingCost = $qualifiesForFreeShipping ? 0 : $standardShippingCost;
?>
<h2 class="shop-page-title">Secure Checkout</h2>

<?php if (!empty($error)): ?>
    <div class="alert-box alert-error">
        <?php echo Str::escape($error); ?>
    </div>
<?php endif; ?>

<div class="checkout-layout">

    <!-- Billing & Shipping Information Form -->
    <form method="post" action="/shop/checkout" class="checkout-form-box">
        <input type="hidden" name="csrf" value="<?php echo Str::escape($csrf ?? ''); ?>">

        <h3 class="checkout-section-title">Shipping & Delivery Details</h3>

        <!-- Full Name -->
        <div class="form-field">
            <label class="form-label">Full Name</label>
            <input name="name" required placeholder="John Doe" class="form-input">
        </div>

        <!-- Email Address -->
        <div class="form-field">
            <label class="form-label">Email Address</label>
            <input name="email" type="email" required placeholder="john.doe@example.com" class="form-input">
        </div>

        <!-- Shipping Address -->
        <div class="form-field">
            <label class="form-label">Shipping Address</label>
            <textarea name="address" required rows="4" placeholder="123 Luxury Ave, Manhattan, NY 10001" class="form-textarea"></textarea>
        </div>

        <h3 class="checkout-section-title">Simulated Payment Info</h3>
        <p class="checkout-desc">Payment processing is fully simulated. No real payment details are required or transmitted.</p>

        <!-- Submit order button -->
        <button type="submit" class="btn-luxe btn-wide">Submit Transaction</button>
    </form>

    <!-- Cart Summary Column -->
    <aside class="checkout-summary-box">
        <h3 class="sidebar-section-title">Cart Summary</h3>

        <div class="checkout-summary-list">
            <?php foreach ($cart as $item): ?>
                <div class="checkout-summary-item">
                    <div class="checkout-summary-item-info">
                        <span class="checkout-summary-title"><?php echo Str::escape($item['title']); ?></span>
                        <?php if (!empty($item['variant_title'])): ?>
                            <span class="badge"><?php echo Str::escape($item['variant_title']); ?></span>
                        <?php endif; ?>
                        <span class="checkout-summary-qty">Qty: <?php echo $item['quantity']; ?></span>
                    </div>
                    <span class="checkout-summary-price"><?php echo Str::escape($currencySymbol); ?><?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="cart-summary-box">
            <div class="cart-summary-row">
                <span class="cart-summary-label">Cart Subtotal:</span>
                <span class="cart-summary-val"><?php echo Str::escape($currencySymbol); ?><?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="cart-summary-row">
                <span class="cart-summary-label">Shipping Delivery:</span>
                <span class="cart-summary-val text-accent"><?php echo $qualifiesForFreeShipping ? 'FREE' : Str::escape($currencySymbol) . number_format($standardShippingCost, 2); ?></span>
            </div>
        </div>

        <div class="cart-summary-total">
            <span>Total Charge:</span>
            <span class="receipt-total-val"><?php echo Str::escape($currencySymbol); ?><?php echo number_format($subtotal + $shippingCost, 2); ?></span>
        </div>
    </aside>

</div>
