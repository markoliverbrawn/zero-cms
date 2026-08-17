<?php
use Zero\Core\App;
use Zero\Support\Str;
// src/Modules/Admin/Views/reset.php
?>
<div class="auth-card">
  <h2>Reset Password</h2>
  
  <?php if (!empty($error)): ?>
      <div class="auth-status-banner-error">
          <?php echo Str::escape($error ?? ''); ?>
      </div>
  <?php endif; ?>
  
  <?php if (!empty($success)): ?>
      <div class="auth-status-banner-success">
          Password updated successfully. You may now <a href="/admin/login">login</a>.
      </div>
  <?php endif; ?>
  
  <?php if (empty($success)): ?>
      <form method="post" class="reset-form">
          <input type="hidden" name="token" value="<?php echo Str::escape($token ?? ''); ?>">
          <?php echo \Zero\Support\Security::csrfInput(); ?>
          
          <div class="auth-form-group">
              <label for="password">New Password</label>
              <?php echo App::makeFormField('password', 'password', [
                  'required' => true,
                  'attributes' => ['id' => 'password', 'placeholder' => 'Enter new password'],
                  'showLabel' => false,
                  'guessHelperTextKey' => false,
              ])->render(); ?>
          </div>
          
          <button type="submit" class="auth-btn-primary">Reset Password</button>
      </form>
  <?php endif; ?>
</div>
