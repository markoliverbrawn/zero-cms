<?php
// src/Views/themes/shop/checkout.php
?>
<h2 style="font-size: 1.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 2px solid var(--border-color); padding-bottom: 12px; margin-top: 0; margin-bottom: 35px;">Secure Checkout</h2>

<?php if (!empty($error)): ?>
    <div style="background-color: #2d1818; border: 1px solid #452222; color: #f87171; padding: 12px; border-radius: var(--border-radius); margin-bottom: 25px; font-size: 0.9rem; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; text-align: center;">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="checkout-layout">
    
    <!-- Billing & Shipping Information Form -->
    <form method="post" action="/shop/checkout" class="checkout-form-box">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        
        <h3 class="checkout-section-title">Shipping & Delivery Details</h3>

        <!-- Full Name -->
        <div class="checkout-field">
            <label class="checkout-label">Full Name</label>
            <input name="name" required placeholder="John Doe" class="checkout-input">
        </div>

        <!-- Email Address -->
        <div class="checkout-field">
            <label class="checkout-label">Email Address</label>
            <input name="email" type="email" required placeholder="john.doe@example.com" class="checkout-input">
        </div>

        <!-- Shipping Address -->
        <div class="checkout-field">
            <label class="checkout-label">Shipping Address</label>
            <textarea name="address" required rows="4" placeholder="123 Luxury Ave, Manhattan, NY 10001" class="checkout-textarea"></textarea>
        </div>

        <h3 class="checkout-section-title">Simulated Payment Info</h3>
        <p class="checkout-desc">Payment processing is fully simulated. No real payment details are required or transmitted.</p>

        <!-- Submit order button -->
        <button type="submit" class="btn-luxe" style="width: 100%; padding: 15px; font-size: 0.9rem; letter-spacing: 0.15em;">Submit Transaction</button>
    </form>

    <!-- Cart Summary Column -->
    <aside class="checkout-summary-box">
        <h3 class="sidebar-section-title">Cart Summary</h3>
        
        <div style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 25px; border-bottom: 1px solid var(--border-color); padding-bottom: 20px; max-height: 250px; overflow-y: auto;">
            <?php foreach ($cart as $item): ?>
                <div class="checkout-summary-item">
                    <div style="display: flex; flex-direction: column;">
                        <span class="checkout-summary-title"><?php echo htmlspecialchars($item['title']); ?></span>
                        <?php if (!empty($item['variant_title'])): ?>
                            <span style="color: var(--accent-color); font-size: 0.7rem; font-weight: bold;"><?php echo htmlspecialchars($item['variant_title']); ?></span>
                        <?php endif; ?>
                        <span class="checkout-summary-qty">Qty: <?php echo $item['quantity']; ?></span>
                    </div>
                    <span class="checkout-summary-price">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="cart-summary-box">
            <div class="cart-summary-row">
                <span class="cart-summary-label">Cart Subtotal:</span>
                <span class="cart-summary-val">$<?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="cart-summary-row">
                <span class="cart-summary-label">Shipping Delivery:</span>
                <span class="cart-summary-val" style="color: var(--accent-color);"><?php echo $subtotal >= 150 ? 'FREE' : '$15.00'; ?></span>
            </div>
        </div>

        <div class="cart-summary-total">
            <span>Total Charge:</span>
            <span class="receipt-total-val" style="font-size: 1.25rem;">$<?php echo number_format($subtotal + ($subtotal >= 150 ? 0 : 15), 2); ?></span>
        </div>
    </aside>

</div>
