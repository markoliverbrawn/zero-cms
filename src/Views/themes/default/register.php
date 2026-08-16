<?php
use Zero\Core\App;
use Zero\Support\Str;
// src/Views/themes/default/register.php
?>
<link rel="stylesheet" href="/assets/css/auth.css?v=1.3">
<div class="auth-container">
    <h2 class="auth-title">Create Account</h2>
    
    <?php if (!empty($error)): ?>
        <div class="auth-error-box">
            <?php echo Str::escape($error); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/register">
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

        <div class="auth-form-group">
            <label class="auth-label">Email Address</label>
            <?php echo App::makeFormField('email', 'email', [
                'required' => true,
                'attributes' => ['class' => 'auth-input', 'placeholder' => 'design@example.com'],
                'showLabel' => false,
                'guessHelperTextKey' => false,
            ])->render(); ?>
        </div>

        <div class="auth-form-group">
            <label class="auth-label">Password</label>
            <?php echo App::makeFormField('password', 'password', [
                'required' => true,
                'attributes' => ['class' => 'auth-input', 'placeholder' => '••••••••'],
                'showLabel' => false,
                'guessHelperTextKey' => false,
            ])->render(); ?>
        </div>

        <div class="auth-form-group-password">
            <label class="auth-label">Confirm Password</label>
            <?php echo App::makeFormField('password', 'confirm_password', [
                'required' => true,
                'attributes' => ['class' => 'auth-input', 'placeholder' => '••••••••'],
                'showLabel' => false,
                'guessHelperTextKey' => false,
            ])->render(); ?>
        </div>
        
        <button type="submit" class="auth-btn">Register Account</button>
        
        <div class="auth-footer-link">
            Already have an account? <a href="/login">Sign In</a>
        </div>
    </form>
</div>
