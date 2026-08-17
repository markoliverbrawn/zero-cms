<?php
// src/Modules/Shop/Views/account.php

use Zero\Core\App;
use Zero\Support\Str;
?>
<h2 class="shop-page-title">My Account Portal</h2>

<!-- Notification Messages -->
<?php if (!empty($success)): ?>
    <div class="alert-box alert-success">
        <?php echo Str::escape($success); ?>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert-box alert-error">
        <?php echo Str::escape($error); ?>
    </div>
<?php endif; ?>

<div class="account-grid">

    <!-- Left Hand Column: Profile & Addresses -->
    <div class="account-column">

        <!-- Profile Block -->
        <div class="account-card">
            <h3 class="account-card-title">My Profile</h3>

            <form method="post" action="/shop/account">
                <input type="hidden" name="csrf" value="<?php echo Str::escape($csrf ?? ''); ?>">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-field">
                    <label class="form-label">Username</label>
                    <?php echo App::makeFormField('text', 'username', [
                        'value' => $user->username,
                        'required' => true,
                        'attributes' => ['class' => 'form-input'],
                        'showLabel' => false,
                        'guessHelperTextKey' => false,
                    ])->render(); ?>
                </div>
                <div class="form-field">
                    <label class="form-label">Email Address</label>
                    <?php echo App::makeFormField('email', 'email', [
                        'value' => $user->email,
                        'required' => true,
                        'attributes' => ['class' => 'form-input'],
                        'showLabel' => false,
                        'guessHelperTextKey' => false,
                    ])->render(); ?>
                </div>

                <button type="submit" class="btn-luxe btn-wide">Save Changes</button>
            </form>
        </div>

        <!-- Address Book Block -->
        <div class="account-card">
            <h3 class="account-card-title">Address Book</h3>

            <!-- Address List -->
            <?php if (empty($addresses)): ?>
                <p class="text-muted-italic">No addresses registered yet.</p>
            <?php else: ?>
                <div class="address-list">
                    <?php foreach ($addresses as $addr): ?>
                        <div class="address-card">
                            <div>
                                <span class="badge"><?php echo Str::escape($addr['label']); ?></span>
                                <h4 class="address-name"><?php echo Str::escape($addr['name']); ?></h4>
                                <p class="address-text"><?php echo Str::escape($addr['address']); ?></p>
                            </div>

                            <!-- Delete form -->
                            <form method="post" action="/shop/account">
                                <input type="hidden" name="csrf" value="<?php echo Str::escape($csrf ?? ''); ?>">
                                <input type="hidden" name="action" value="delete_address">
                                <input type="hidden" name="address_id" value="<?php echo Str::escape($addr['id']); ?>">
                                <button type="submit" class="icon-btn-danger">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Add New Address Dropdown Form -->
            <details class="address-add-toggle">
                <summary>
                    <span>+</span> Register New Address
                </summary>

                <div class="address-add-panel">
                    <form method="post" action="/shop/account">
                        <input type="hidden" name="csrf" value="<?php echo Str::escape($csrf ?? ''); ?>">
                        <input type="hidden" name="action" value="add_address">

                        <div class="form-field">
                            <label class="form-label">Address Label</label>
                            <?php echo App::makeFormField('text', 'label', [
                                'required' => true,
                                'attributes' => ['class' => 'form-input', 'placeholder' => 'Default Home / Studio Office'],
                                'showLabel' => false,
                                'guessHelperTextKey' => false,
                            ])->render(); ?>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Receiver Full Name</label>
                            <?php echo App::makeFormField('text', 'name', [
                                'required' => true,
                                'attributes' => ['class' => 'form-input', 'placeholder' => 'John Doe'],
                                'showLabel' => false,
                                'guessHelperTextKey' => false,
                            ])->render(); ?>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Shipping Address</label>
                            <?php echo App::makeFormField('textarea', 'address', [
                                'required' => true,
                                'attributes' => ['rows' => 3, 'class' => 'form-textarea', 'placeholder' => '123 Luxury Ave, Manhattan, NY 10001'],
                                'showLabel' => false,
                                'guessHelperTextKey' => false,
                            ])->render(); ?>
                        </div>

                        <button type="submit" class="btn-luxe btn-wide">Register Address</button>
                    </form>
                </div>
            </details>
        </div>

    </div>

    <!-- Right Hand Column: Order History list -->
    <div class="account-card">
        <h3 class="account-card-title">My Order History</h3>

        <?php if (empty($orders)): ?>
            <div class="empty-state-box">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="empty-state-icon">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <p class="empty-state-title">No purchase transactions found.</p>
                <p class="empty-state-desc">Orders made under your email will list here.</p>
                <a href="/shop/catalog" class="btn-luxe">Start Shopping</a>
            </div>
        <?php else: ?>
            <div class="order-history-list">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-card-header">
                            <div>
                                <span class="badge"><?php echo Str::escape($order->status === 'paid' ? 'Completed Paid' : $order->status); ?></span>
                                <h4 class="order-id">#<?php echo substr($order->id, 0, 8); ?>...</h4>
                                <span class="order-date"><?php echo Str::escape($order->created_at); ?></span>
                            </div>
                            <div class="order-total">
                                <span class="order-total-val">$<?php echo number_format($order->total_price, 2); ?></span>
                                <a href="/shop/success?order_id=<?php echo Str::escape($order->id); ?>" class="btn-luxe-outline">Receipt Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>
