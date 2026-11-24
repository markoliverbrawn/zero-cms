<?php
// src/Modules/Security/Views/health_check_email.php

use Zero\Support\Str;

$statusLabels = ['pass' => 'Pass', 'warn' => 'Warning', 'fail' => 'Fail'];
$statusColours = ['pass' => '#047857', 'warn' => '#b45309', 'fail' => '#b91c1c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Health check</title>
</head>
<body style="background-color: #f8fafc; color: #0f172a; font-family: Arial, Helvetica, sans-serif; margin: 0; padding: 24px;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border: 1px solid #e2e8f0; border-collapse: collapse;">
    <tr>
      <td style="padding: 20px;">
        <h1 style="font-size: 20px; margin: 0 0 8px;">Health check: <?php echo Str::escape($siteName !== '' ? $siteName : $siteDomain); ?></h1>
        <p style="color: #475569; margin: 0 0 16px;">
          <?php echo Str::escape($siteDomain); ?>.
          <?php if ($status === 'fail'): ?>Something needs fixing.<?php elseif ($changed): ?>A check's result changed since the last run.<?php else: ?>All checks pass.<?php endif; ?>
        </p>
        <table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="border-collapse: collapse;">
          <?php foreach ($results as $result): ?>
            <tr>
              <td style="border-top: 1px solid #e2e8f0; font-weight: bold; vertical-align: top; width: 30%;"><?php echo Str::escape((string) $result['label']); ?></td>
              <td style="border-top: 1px solid #e2e8f0; color: <?php echo Str::escape($statusColours[$result['status']] ?? '#0f172a'); ?>; font-weight: bold; vertical-align: top; width: 15%;"><?php echo Str::escape($statusLabels[$result['status']] ?? (string) $result['status']); ?></td>
              <td style="border-top: 1px solid #e2e8f0; vertical-align: top;"><?php echo Str::escape((string) $result['message']); ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
        <p style="margin: 16px 0 0;"><a href="<?php echo Str::escape($reportUrl); ?>">Open the health check</a></p>
      </td>
    </tr>
  </table>
</body>
</html>
