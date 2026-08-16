<?php
use Zero\Support\Str;
/**
 * src/Views/emails/forgot-password.php
 * Beautiful recovery email template.
 */
$expiryMinutes = $expiryMinutes ?? 60;
if ($expiryMinutes % 60 === 0) {
    $hours = (int)($expiryMinutes / 60);
    $expiryLabel = $hours . ' hour' . ($hours === 1 ? '' : 's');
} else {
    $expiryLabel = $expiryMinutes . ' minute' . ($expiryMinutes === 1 ? '' : 's');
}
?>
<div style="max-width: 550px; margin: 40px auto; padding: 40px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-family: sans-serif; color: #0f172a;">
    <h2 style="margin-top: 0; font-weight: 800; font-size: 1.5rem; border-bottom: 2px solid #d4af37; padding-bottom: 12px; color: #000;">Password Recovery Request</h2>
    <p style="font-size: 1rem; line-height: 1.6;">Hello <strong><?= Str::escape($username) ?></strong>,</p>
    <p style="font-size: 1rem; line-height: 1.6;">A request has been received to recover the password associated with your account on <strong><?= Str::escape($siteName) ?></strong>.</p>
    <p style="font-size: 1rem; line-height: 1.6; margin-bottom: 30px;">To reset your password, please click the secure link below. This link is only valid for the next <?= Str::escape($expiryLabel) ?>:</p>
    <div style="text-align: center; margin-bottom: 35px;">
        <a href="<?= Str::escape($link) ?>" style="display: inline-block; padding: 12px 30px; background-color: #d4af37; color: #000; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em;">Reset My Password</a>
    </div>
    <p style="font-size: 0.8rem; color: #64748b; line-height: 1.6;">If you did not request this recovery, please ignore this email or contact support. Your password will remain completely secure.</p>
</div>
