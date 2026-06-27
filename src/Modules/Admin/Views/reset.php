<?php
// src/Modules/Admin/Views/reset.php
?>
<div class="auth-card">
  <h2>Reset Password</h2>
  
  <?php if (!empty($error)): ?>
      <div class="auth-status-banner-error">
          <?php echo htmlspecialchars($error ?? '', ENT_QUOTES, "UTF-8"); ?>
      </div>
  <?php endif; ?>
  
  <?php if (!empty($success)): ?>
      <div class="auth-status-banner-success">
          Password updated successfully. You may now <a href="/admin/login">login</a>.
      </div>
  <?php endif; ?>
  
  <?php if (empty($success)): ?>
      <form method="post" class="reset-form">
          <input type="hidden" name="token" value="<?php echo htmlspecialchars($token ?? '', ENT_QUOTES, "UTF-8"); ?>">
          
          <div class="auth-form-group">
              <label for="password">New Password</label>
              <input type="password" id="password" name="password" required placeholder="Enter new password">
          </div>
          
          <button type="submit" class="auth-btn-primary">Reset Password</button>
      </form>
  <?php endif; ?>
</div>
