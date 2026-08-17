<?php
use Zero\Core\App;
use Zero\Support\Str;
// src/Modules/Admin/Views/forgot.php
?>
<div class="auth-card">
  <h2>Forgot Password</h2>

  <?php if (!empty($error)): ?>
      <div class="auth-status-banner-error">
          <?php echo Str::escape($error ?? ''); ?>
      </div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
      <div class="auth-status-banner-success">
          <?php echo Str::escape($success ?? ''); ?>
      </div>
  <?php else: ?>
      <form method="post" class="forgot-form">
          <input type="hidden" name="csrf" value="<?php echo Str::escape($csrf ?? ''); ?>">
          
          <div class="auth-form-group">
              <label for="username">Account Username</label>
              <?php echo App::makeFormField('text', 'username', [
                  'required' => true,
                  'attributes' => ['id' => 'username', 'placeholder' => 'Enter username'],
                  'showLabel' => false,
                  'guessHelperTextKey' => false,
              ])->render(); ?>
          </div>
          
          <button type="submit" class="auth-btn-primary">Request Recovery Link</button>
      </form>
  <?php endif; ?>

  <div class="auth-card-footer">
      <a href="/admin/login">Back to Login</a>
  </div>
</div>
