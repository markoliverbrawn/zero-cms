<?php
/**
 * src/Views/emails/demo_credentials.php
 * Beautiful demo sandbox login credentials email template.
 */
?>
<div style="max-width: 550px; margin: 40px auto; padding: 40px; background-color: #051424; border: 1px solid rgba(0, 240, 255, 0.15); border-radius: 8px; font-family: sans-serif; color: #d4e4fa;">
    <h2 style="margin-top: 0; font-weight: 800; font-size: 1.5rem; border-bottom: 2px solid #00f0ff; padding-bottom: 12px; color: #00f0ff; text-shadow: 0 0 8px rgba(0, 240, 255, 0.3);">Your Zero CMS Sandbox Demo is Ready!</h2>
    <p style="font-size: 1rem; line-height: 1.6; color: #b9cacb;">We have successfully spun up a fully isolated, high-performance Zero CMS multi-tenant sandbox for you.</p>
    <p style="font-size: 1rem; line-height: 1.6; color: #b9cacb; margin-bottom: 24px;">Below are your primary access credentials and sandbox coordinates:</p>
    
    <div style="background-color: #0b0f19; border: 1px solid rgba(0, 240, 255, 0.08); border-radius: 4px; padding: 20px; color: #d4e4fa; font-family: monospace; font-size: 0.9rem; margin-bottom: 30px; word-break: break-all;">
        <strong style="color: #b9cacb;">Sandbox Domain:</strong> <a href="http://<?= htmlspecialchars($domain) ?>" style="color: #00f0ff; text-decoration: none;">http://<?= htmlspecialchars($domain) ?></a><br>
        <strong style="color: #b9cacb;">Admin Panel:</strong> <a href="http://<?= htmlspecialchars($domain) ?>/admin/dashboard" style="color: #00f0ff; text-decoration: none;">http://<?= htmlspecialchars($domain) ?>/admin/dashboard</a><br>
        <strong style="color: #b9cacb;">Username:</strong> <span style="color: #00f0ff;"><?= htmlspecialchars($email) ?></span><br>
        <strong style="color: #b9cacb;">Password:</strong> <span style="color: #00f0ff; font-weight: bold;"><?= htmlspecialchars($password) ?></span>
    </div>
    
    <div style="text-align: center; margin-bottom: 35px;">
        <a href="http://<?= htmlspecialchars($domain) ?>/admin/dashboard" style="display: inline-block; padding: 12px 30px; background-color: #00f0ff; color: #002022; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; transition: opacity 0.2s ease;">Enter Admin Panel</a>
    </div>
    
    <p style="font-size: 0.85rem; color: #b9cacb; line-height: 1.6; font-style: italic;">Note: This sandbox is temporary and will be permanently deleted automatically in 24 hours.</p>
</div>
