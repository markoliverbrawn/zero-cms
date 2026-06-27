<?php
// src/Views/themes/default/reset.php
?>
<link rel="stylesheet" href="/assets/css/auth.css?v=1.3">
<div class="auth-container">
    <h2 class="auth-title">Set New Password</h2>
    
    <?php if (!empty($error)): ?>
        <div class="auth-error-box">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php else: ?>
        <form method="post" action="/reset">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token ?? ''); ?>">
            
            <div class="auth-form-group">
                <label class="auth-label">New Password</label>
                <input name="password" type="password" required placeholder="••••••••" class="auth-input">
            </div>
            
            <div class="auth-form-group-password">
                <label class="auth-label">Confirm New Password</label>
                <input name="confirm_password" type="password" required placeholder="••••••••" class="auth-input">
            </div>
            
            <button type="submit" class="auth-btn">Save &amp; Sign In</button>
        </form>
    <?php endif; ?>
    
    <div class="auth-footer-link">
        <a href="/login">Back to Sign In</a>
    </div>
</div>
