<?php
// src/Views/themes/shop/account.php
?>
<h2 style="font-size: 1.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 2px solid var(--border-color); padding-bottom: 12px; margin-top: 0; margin-bottom: 35px;">My Account Portal</h2>

<!-- Notification Messages -->
<?php if (!empty($success)): ?>
    <div style="background-color: #1b2c1f; border: 1px solid #223c26; color: #4ade80; padding: 12px; border-radius: var(--border-radius); margin-bottom: 25px; font-size: 0.9rem; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; text-align: center;">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div style="background-color: #2d1818; border: 1px solid #452222; color: #f87171; padding: 12px; border-radius: var(--border-radius); margin-bottom: 25px; font-size: 0.9rem; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; text-align: center;">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 50px;">
    
    <!-- Left Hand Column: Profile & Addresses -->
    <div style="display: flex; flex-direction: column; gap: 40px;">
        
        <!-- Profile Block -->
        <div style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 30px;">
            <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 1.05rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; color: #fff;">My Profile</h3>
            
            <form method="post" action="/shop/account">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="update_profile">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 6px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted);">Username</label>
                    <input name="username" value="<?php echo htmlspecialchars($user->username); ?>" required style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: var(--border-radius); background: #000; color: #fff; font-size: 0.9rem;">
                </div>
                <div style="margin-bottom: 25px;">
                    <label style="display: block; margin-bottom: 6px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted);">Email Address</label>
                    <input name="email" type="email" value="<?php echo htmlspecialchars($user->email); ?>" required style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: var(--border-radius); background: #000; color: #fff; font-size: 0.9rem;">
                </div>
                
                <button type="submit" class="btn-luxe" style="width: 100%; font-size: 0.75rem;">Save Changes</button>
            </form>
        </div>

        <!-- Address Book Block -->
        <div style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 30px;">
            <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 1.05rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; color: #fff;">Address Book</h3>
            
            <!-- Address List -->
            <?php if (empty($addresses)): ?>
                <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 25px; font-style: italic;">No addresses registered yet.</p>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 30px;">
                    <?php foreach ($addresses as $addr): ?>
                        <div style="border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 15px; background-color: #000; display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <span style="font-size: 0.7rem; background-color: #222; border: 1px solid var(--border-color); color: var(--accent-color); padding: 2px 8px; border-radius: 4px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block; margin-bottom: 8px;"><?php echo htmlspecialchars($addr['label']); ?></span>
                                <h4 style="margin: 0 0 4px 0; font-size: 0.9rem; color: #fff; font-weight: bold;"><?php echo htmlspecialchars($addr['name']); ?></h4>
                                <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars($addr['address']); ?></p>
                            </div>
                            
                            <!-- Delete form -->
                            <form method="post" action="/shop/account">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="delete_address">
                                <input type="hidden" name="address_id" value="<?php echo htmlspecialchars($addr['id']); ?>">
                                <button type="submit" style="background: none; border: none; color: #ef4444; cursor: pointer; padding: 4px;">
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
            <details style="cursor: pointer; font-size: 0.85rem; font-weight: bold; color: var(--accent-color); outline: none;">
                <summary style="text-transform: uppercase; letter-spacing: 0.05em; list-style: none; display: flex; align-items: center; gap: 6px;">
                    <span style="font-size: 1.1rem; line-height: 1;">+</span> Register New Address
                </summary>
                
                <div style="margin-top: 20px; cursor: default; background: #000; border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 20px;">
                    <form method="post" action="/shop/account">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="add_address">
                        
                        <div style="margin-bottom: 12px;">
                            <label style="display: block; margin-bottom: 6px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted);">Address Label</label>
                            <input name="label" required placeholder="Default Home / Studio Office" style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: var(--border-radius); background: #111; color: #fff; font-size: 0.85rem;">
                        </div>
                        <div style="margin-bottom: 12px;">
                            <label style="display: block; margin-bottom: 6px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted);">Receiver Full Name</label>
                            <input name="name" required placeholder="John Doe" style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: var(--border-radius); background: #111; color: #fff; font-size: 0.85rem;">
                        </div>
                        <div style="margin-bottom: 18px;">
                            <label style="display: block; margin-bottom: 6px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; color: var(--text-muted);">Shipping Address</label>
                            <textarea name="address" required rows="3" placeholder="123 Luxury Ave, Manhattan, NY 10001" style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: var(--border-radius); background: #111; color: #fff; font-size: 0.85rem; font-family: inherit; resize: vertical;"></textarea>
                        </div>
                        
                        <button type="submit" class="btn-luxe" style="width: 100%; padding: 10px; font-size: 0.75rem;">Register Address</button>
                    </form>
                </div>
            </details>
        </div>

    </div>

    <!-- Right Hand Column: Order History list -->
    <div style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 30px; height: fit-content;">
        <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 1.05rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; color: #fff;">My Order History</h3>
        
        <?php if (empty($orders)): ?>
            <div style="border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 60px 20px; text-align: center; color: var(--text-muted); background-color: #000;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 15px; color: var(--accent-color);">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <p style="margin: 0; font-size: 0.95rem; font-weight: bold; color: #fff;">No purchase transactions found.</p>
                <p style="margin-top: 4px; font-size: 0.8rem; margin-bottom: 15px;">Orders made under your email will list here.</p>
                <a href="/shop/catalog" class="btn-luxe" style="font-size: 0.7rem; padding: 8px 18px;">Start Shopping</a>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <?php foreach ($orders as $order): ?>
                    <div style="border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 20px; background-color: #000; display: flex; flex-direction: column; gap: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <span style="font-size: 0.65rem; background-color: #222; border: 1px solid var(--border-color); color: var(--accent-color); padding: 2px 8px; border-radius: 4px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; display: inline-block; margin-bottom: 6px;">
                                    <?php echo htmlspecialchars($order->status === 'paid' ? 'Completed Paid' : $order->status); ?>
                                </span>
                                <h4 style="margin: 0; font-size: 0.8rem; font-family: monospace; color: var(--text-muted);">#<?php echo substr($order->id, 0, 8); ?>...</h4>
                                <span style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($order->created_at); ?></span>
                            </div>
                            <div style="text-align: right;">
                                <span style="font-size: 1.1rem; font-weight: 800; color: var(--accent-color); font-family: monospace; display: block; margin-bottom: 6px;">$<?php echo number_format($order->total_price, 2); ?></span>
                                <a href="/shop/success?order_id=<?php echo htmlspecialchars($order->id); ?>" class="btn-luxe-outline" style="font-size: 0.65rem; padding: 5px 12px;">Receipt Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>
