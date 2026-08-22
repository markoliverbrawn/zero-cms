<?php
use Zero\Core\App;
use Zero\Support\AssetVersion;
use Zero\Support\Str;
// src/Views/themes/default/login.php
?>
<link rel="stylesheet" href="<?php echo Str::escape(AssetVersion::url('/assets/css/auth.css')); ?>">
<div class="auth-container">
    <h2 class="auth-title">Sign In</h2>
    
    <?php if (!empty($error)): ?>
        <div class="auth-error-box">
            <?php echo Str::escape($error); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/login">
        <input type="hidden" name="csrf" value="<?php echo Str::escape($csrf ?? ''); ?>">
        
        <div class="auth-form-group">
            <label class="auth-label">Username</label>
            <?php echo App::makeFormField('text', 'username', [
                'required' => true,
                'attributes' => ['class' => 'auth-input', 'placeholder' => 'minimalist_stylist'],
                'showLabel' => false,
                'guessHelperTextKey' => false,
            ])->render(); ?>
        </div>

        <div class="auth-form-group-password">
            <div class="auth-form-group-password-header">
                <label class="auth-label-inline">Password</label>
                <a href="/forgot" style="font-size: 0.8rem; color: var(--accent-color, #2563eb); text-decoration: none; font-weight: bold;">Forgot Password?</a>
            </div>
            <?php echo App::makeFormField('password', 'password', [
                'required' => true,
                'attributes' => ['class' => 'auth-input', 'placeholder' => '••••••••'],
                'showLabel' => false,
                'guessHelperTextKey' => false,
            ])->render(); ?>
        </div>
        
        <button type="submit" class="auth-btn">Log In</button>
        
        <div class="auth-footer-link">
            Don't have an account? <a href="/register">Sign Up</a>
        </div>
    </form>
</div>
