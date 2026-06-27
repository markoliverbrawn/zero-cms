<?php
// src/Views/themes/default/register.php
?>
<link rel="stylesheet" href="/assets/css/auth.css?v=1.3">
<div class="auth-container">
    <h2 class="auth-title">Create Account</h2>
    
    <?php if (!empty($error)): ?>
        <div class="auth-error-box">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/register">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        
        <div class="auth-form-group">
            <label class="auth-label">Username</label>
            <input name="username" required placeholder="minimalist_stylist" class="auth-input">
        </div>
        
        <div class="auth-form-group">
            <label class="auth-label">Email Address</label>
            <input name="email" type="email" required placeholder="design@example.com" class="auth-input">
        </div>
        
        <div class="auth-form-group">
            <label class="auth-label">Password</label>
            <input name="password" type="password" required placeholder="••••••••" class="auth-input">
        </div>
        
        <div class="auth-form-group-password">
            <label class="auth-label">Confirm Password</label>
            <input name="confirm_password" type="password" required placeholder="••••••••" class="auth-input">
        </div>
        
        <button type="submit" class="auth-btn">Register Account</button>
        
        <div class="auth-footer-link">
            Already have an account? <a href="/login">Sign In</a>
        </div>
    </form>
</div>
