<?php
use Zero\Core\App;
use Zero\Support\AssetVersion;
use Zero\Support\Str;
// src/Views/themes/default/reset.php
?>
<link rel="stylesheet" href="<?php echo Str::escape(AssetVersion::url('/assets/css/auth.css')); ?>">
<div class="auth-container">
    <h2 class="auth-title">Set New Password</h2>
    
    <?php if (!empty($error)): ?>
        <div class="auth-error-box">
            <?php echo Str::escape($error); ?>
        </div>
    <?php else: ?>
        <form method="post" action="/reset">
            <input type="hidden" name="csrf" value="<?php echo Str::escape($csrf ?? ''); ?>">
            <input type="hidden" name="token" value="<?php echo Str::escape($token ?? ''); ?>">
            
            <div class="auth-form-group">
                <label class="auth-label">New Password</label>
                <?php echo App::makeFormField('password', 'password', [
                    'required' => true,
                    'attributes' => ['class' => 'auth-input', 'placeholder' => '••••••••'],
                    'showLabel' => false,
                    'guessHelperTextKey' => false,
                ])->render(); ?>
            </div>

            <div class="auth-form-group-password">
                <label class="auth-label">Confirm New Password</label>
                <?php echo App::makeFormField('password', 'confirm_password', [
                    'required' => true,
                    'attributes' => ['class' => 'auth-input', 'placeholder' => '••••••••'],
                    'showLabel' => false,
                    'guessHelperTextKey' => false,
                ])->render(); ?>
            </div>
            
            <button type="submit" class="auth-btn">Save &amp; Sign In</button>
        </form>
    <?php endif; ?>
    
    <div class="auth-footer-link">
        <a href="/login">Back to Sign In</a>
    </div>
</div>
