<?php
// src/Views/themes/default/forgot.php
?>
<link rel="stylesheet" href="/assets/css/auth.css?v=1.3">
<div class="auth-container">
    <h2 class="auth-title">Recover Password</h2>
    
    <?php if (!empty($error)): ?>
        <div class="auth-error-box">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="auth-success-box">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php else: ?>
        <form method="post" action="/forgot">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            
            <div class="auth-form-group-password">
                <label class="auth-label">Account Username</label>
                <input name="username" required placeholder="minimalist_stylist" class="auth-input">
            </div>
            
            <button type="submit" class="auth-btn">Request Recovery Link</button>
        </form>
    <?php endif; ?>
    
    <div class="auth-footer-link">
        <a href="/login">Back to Sign In</a>
    </div>
</div>
