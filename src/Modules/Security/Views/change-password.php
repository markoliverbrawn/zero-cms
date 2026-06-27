<?php
// src/Modules/Security/Views/change-password.php

use Zero\Core\App;
use Zero\Support\Security;

?>
<div class="auth-wrapper" style="display: flex; align-items: center; justify-content: center; min-height: 70vh; padding: 2rem 1rem;">
    <div class="auth-card" style="width: 100%; max-width: 480px; background-color: var(--card-bg, #ffffff); border: 1px solid var(--border-color, #cbd5e1); border-radius: var(--border-radius, 8px); padding: 2.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
        
        <div class="auth-header" style="text-align: center; margin-bottom: 2rem;">
            <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 0.5rem;">
                <span class="icon-svg" style="color: var(--accent-color, #2563eb); width: 22px; height: 22px; display: inline-block;"><?php echo App::svg('shield'); ?></span>
                <h2 style="font-size: 1.6rem; font-weight: 800; margin: 0; color: var(--text-color, #0f172a); display: inline-block;">Hardening Protection</h2>
            </div>
            <p style="color: var(--text-muted, #64748b); font-size: 0.95rem; margin: 0;">Zero CMS Security Enforcement</p>
        </div>

        <!-- High-Visibility Vulnerability Alert Box -->
        <div class="auth-status-banner-error" style="background-color: #7f1d1d; border: 1px solid #b91c1c; border-radius: 6px; padding: 1.25rem; color: #fecaca; margin-bottom: 1.5rem; font-size: 0.9rem; line-height: 1.5;">
            <strong style="color: #ffffff; display: block; margin-bottom: 0.25rem; font-size: 0.95rem;">CRITICAL SECURITY WARNING:</strong>
            You are currently logged in using the default installation seed credentials. This poses an immediate exploit vector. You must update your password to a strong, secure value before you can access dashboard features.
        </div>

        <!-- System Errors/Warnings -->
        <?php if (!empty($error)): ?>
            <div class="auth-status-banner-error" style="background-color: #fef2f2; border: 1px solid #fca5a5; border-radius: 6px; padding: 1rem; color: #991b1b; margin-bottom: 1.5rem; font-size: 0.9rem; font-weight: 600;">
                <span style="color: #b91c1c; font-weight: bold; margin-right: 4px;">[ERROR]</span><?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="/admin/change-password" class="login-form">
            <?php echo Security::csrfInput(); ?>

            <div class="auth-form-group" style="margin-bottom: 1.25rem; display: flex; flex-direction: column; gap: 0.5rem;">
                <label for="password" style="font-weight: 600; font-size: 0.9rem; color: var(--text-color, #0f172a);">New Secure Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter strong password (min 8 chars)" style="padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); font-size: 0.95rem;">
            </div>

            <div class="auth-form-group" style="margin-bottom: 2rem; display: flex; flex-direction: column; gap: 0.5rem;">
                <label for="confirm_password" style="font-weight: 600; font-size: 0.9rem; color: var(--text-color, #0f172a);">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Re-type new password" style="padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: var(--border-radius); font-size: 0.95rem;">
            </div>

            <button class="auth-btn-primary" type="submit" style="width: 100%; background-color: var(--accent-color, #2563eb); color: #ffffff; border: none; padding: 0.8rem; border-radius: var(--border-radius); font-size: 1rem; font-weight: 700; cursor: pointer; transition: background-color 0.15s ease;">
                Update Password & Secure Platform
            </button>
        </form>

        <div style="text-align: center; margin-top: 1.5rem;">
            <form method="post" action="/admin/logout" style="display: inline-block; margin: 0;">
                <?php echo Security::csrfInput(); ?>
                <button type="submit" style="background: none; border: none; padding: 0; font-size: 0.9rem; color: var(--text-muted); font-weight: 600; text-decoration: none; cursor: pointer; font-family: inherit; &:hover { text-decoration: underline; }">
                    ➔ Log Out Safely
                </button>
            </form>
        </div>
    </div>
</div>
