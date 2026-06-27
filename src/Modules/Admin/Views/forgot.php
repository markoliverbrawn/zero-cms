<?php
// src/Modules/Admin/Views/forgot.php
?>
<div class="auth-card">
  <h2>Forgot Password</h2>

  <?php if (!empty($error)): ?>
      <div class="auth-status-banner-error">
          <?php echo htmlspecialchars($error ?? '', ENT_QUOTES, "UTF-8"); ?>
      </div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
      <div class="auth-status-banner-success">
          <?php echo htmlspecialchars($success ?? '', ENT_QUOTES, "UTF-8"); ?>
      </div>
  <?php else: ?>
      <form method="post" class="forgot-form">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          
          <div class="auth-form-group">
              <label for="username">Account Username</label>
              <input type="text" id="username" name="username" required placeholder="Enter username">
          </div>
          
          <button type="submit" class="auth-btn-primary">Request Recovery Link</button>
      </form>
  <?php endif; ?>

  <div class="auth-card-footer">
      <a href="/admin/login">Back to Login</a>
  </div>
</div>
