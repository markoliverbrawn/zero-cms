<?php
// src/Views/themes/shop/success.php
?>
<div class="success-layout">
    
    <!-- Animated success check icon -->
    <div class="success-check-circle">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="var(--accent-color)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="success-check-icon">
            <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
    </div>

    <span class="success-tag">Payment Received</span>
    <h2 class="success-title">Transaction Confirmed</h2>
    <p class="success-desc">Thank you for your business. Your premium minimalist items have been secured and scheduled for direct shipping. A full digital ledger receipt has been generated.</p>

    <!-- Receipt Details -->
    <div class="receipt-details-box">
        <h3 class="receipt-section-title">Digital Ledger Receipt</h3>
        
        <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 25px;">
            <div class="receipt-row">
                <span class="receipt-label">Receipt ID (UUIDv7):</span>
                <span class="receipt-val" style="color: var(--accent-color); font-family: monospace; font-size: 0.8rem;"><?php echo htmlspecialchars($order->id); ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Customer Name:</span>
                <span class="receipt-val"><?php echo htmlspecialchars($order->customer_name); ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Email Address:</span>
                <span class="receipt-val"><?php echo htmlspecialchars($order->customer_email); ?></span>
            </div>
            <div class="receipt-row">
                <span class="receipt-label">Transaction Time:</span>
                <span class="receipt-val"><?php echo htmlspecialchars($order->created_at); ?></span>
            </div>
        </div>

        <h3 class="receipt-section-title" style="font-size: 0.85rem; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">Secured Cargo</h3>
        <div class="receipt-cargo-box">
            <?php foreach ($items as $item): ?>
                <div class="receipt-cargo-item">
                    <span class="receipt-cargo-title"><?php echo htmlspecialchars($item->title); ?> (x<?php echo $item->quantity; ?>)</span>
                    <span class="receipt-cargo-price">$<?php echo number_format($item->price * $item->quantity, 2); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="receipt-total-row">
            <span>Total Debited:</span>
            <span class="receipt-total-val">$<?php echo number_format($order->total_price, 2); ?></span>
        </div>
    </div>

    <a href="/shop/catalog" class="btn-luxe">Back To Catalog</a>
</div>
