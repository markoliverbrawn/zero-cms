<?php
/**
 * src/Views/emails/welcome.php
 * Beautiful welcome email template.
 */
?>
<div style="max-width: 550px; margin: 40px auto; padding: 40px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-family: sans-serif; color: #0f172a;">
    <h2 style="margin-top: 0; font-weight: 800; font-size: 1.5rem; border-bottom: 2px solid #06b6d4; padding-bottom: 12px; color: #000;">Welcome to Zero CMS!</h2>
    <p style="font-size: 1rem; line-height: 1.6;">Hello <strong><?= htmlspecialchars($username) ?></strong>,</p>
    <p style="font-size: 1rem; line-height: 1.6;">Your administrative account has been successfully initialized on <strong><?= htmlspecialchars($siteName) ?></strong>.</p>
    <p style="font-size: 1rem; line-height: 1.6; margin-bottom: 24px;">Below are your primary access credentials and coordinates:</p>
    <div style="background-color: #0b0f19; border: 1px solid #222636; border-radius: 4px; padding: 20px; color: #00ffcc; font-family: monospace; font-size: 0.9rem; margin-bottom: 30px; word-break: break-all;">
        <strong>Host Domain:</strong> <?= htmlspecialchars($siteDomain) ?><br>
        <strong>Username:</strong> <?= htmlspecialchars($username) ?><br>
        <strong>Email:</strong> <?= htmlspecialchars($email) ?><br>
        <strong>System Role:</strong> <?= htmlspecialchars($role) ?>
    </div>
    <div style="text-align: center; margin-bottom: 35px;">
        <a href="<?= htmlspecialchars($link) ?>" style="display: inline-block; padding: 12px 30px; background-color: #06b6d4; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em;">Log In to Back-Office</a>
    </div>
    <p style="font-size: 0.85rem; color: #64748b; line-height: 1.6;">If you have any questions or require architectural assistance, please check our developer guides or contact support.</p>
</div>
