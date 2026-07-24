<?php
// src/Modules/Admin/Views/login.php

use Zero\Core\Env;
use Zero\Support\Security;
use Zero\Support\Str;

$errorMsg = $error ?? '';
if (empty($errorMsg) && !empty($_GET['error'])) {
    $errCode = $_GET['error'];
    switch ($errCode) {
        case 'csrf_verification_failed':
            $errorMsg = 'Security verification failed. Please try again.';
            break;
        case 'google_returned_error':
            $errorMsg = 'Google authentication was declined or cancelled.';
            break;
        case 'google_oauth_unconfigured':
            $errorMsg = 'Login with Google is currently unconfigured.';
            break;
        case 'google_account_not_found':
            $errorMsg = 'No local account matches this Google email address.';
            break;
        case 'google_site_isolation_mismatch':
            $errorMsg = 'Access Denied: This account belongs to another site.';
            break;
        case 'google_unauthorized_role':
            $errorMsg = 'Access Denied: Your account role is not authorized.';
            break;
        default:
            $errorMsg = 'Google Authentication failed. Please try again.';
            break;
    }
}

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$host = explode(':', $host)[0];

$isHttps = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1 || $_SERVER['HTTPS'] === '1')) || 
           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

$isHttpsOrLocalhost = $isHttps || ($host === 'localhost');

$googleClientId = Env::get('GOOGLE_CLIENT_ID');
$googleRedirectUri = Env::get('GOOGLE_REDIRECT_URI');
$googleEnabled = !empty($googleClientId) && !empty($googleRedirectUri) && $isHttpsOrLocalhost;

$googleAuthUrl = '';
if ($googleEnabled) {
    $googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'     => $googleClientId,
        'redirect_uri'  => $googleRedirectUri,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => Security::csrfToken()
    ]);
}
?>
<div class="auth-card">
  <h2>Admin Login</h2>
  
  <?php if (!empty($errorMsg)): ?>
    <div class="auth-error-box">
      <?php echo Str::escape($errorMsg); ?>
    </div>
  <?php endif; ?>

  <form method="post" class="login-form">
    <input type="hidden" name="csrf" value="<?php echo Str::escape($csrf ?? ''); ?>">
    
    <div class="auth-form-group">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" placeholder="Enter username" required autocomplete="username">
    </div>
    
    <div class="auth-form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" placeholder="Enter password" required autocomplete="current-password">
    </div>
    
    <button class="auth-btn-primary" type="submit">Login</button>
  </form>
  
  <?php if ($googleEnabled): ?>
    <div class="oauth-divider">
      <span>or</span>
    </div>
    <a href="<?php echo Str::escape($googleAuthUrl); ?>" class="btn-google-login">
      <svg class="google-icon" viewBox="0 0 24 24" width="18" height="18" xmlns="http://www.w3.org/2000/svg">
        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill="#FBBC05"/>
        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill="#EA4335"/>
      </svg>
      <span>Sign in with Google</span>
    </a>
  <?php endif; ?>
  
  <div class="auth-card-footer">
    <a href="/admin/forgot">Forgot password?</a>
  </div>
</div>
