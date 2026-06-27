<?php
// src/Views/themes/default/login.php
?>
<link rel="stylesheet" href="/assets/css/auth.css?v=1.3">
<div class="auth-container">
    <h2 class="auth-title">Sign In</h2>
    
    <?php if (!empty($error)): ?>
        <div class="auth-error-box">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/login">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        
        <div class="auth-form-group">
            <label class="auth-label">Username</label>
            <input name="username" required placeholder="minimalist_stylist" class="auth-input">
        </div>
        
        <div class="auth-form-group-password">
            <div class="auth-form-group-password-header">
                <label class="auth-label-inline">Password</label>
                <a href="/forgot" style="font-size: 0.8rem; color: var(--accent-color, #2563eb); text-decoration: none; font-weight: bold;">Forgot Password?</a>
            </div>
            <input name="password" type="password" required placeholder="••••••••" class="auth-input">
        </div>
        
        <button type="submit" class="auth-btn">Log In</button>
        
        <div class="auth-footer-link">
            Don't have an account? <a href="/register">Sign Up</a>
        </div>
    </form>
</div>
