<?php
// src/Modules/Security/Views/health_check.php

use Zero\Core\App;
use Zero\Support\I18n;
use Zero\Support\Str;

$statusLabels = ['pass' => 'Pass', 'warn' => 'Warning', 'fail' => 'Fail', 'legacy' => 'Legacy audit'];
$selectedId = $selected !== null ? (string) $selected->id : '';
?>
<div class="listrecords health-check-page">
  <div class="health-check-header">
    <h2>Health Check</h2>
    <form method="post" action="/admin/security/audit">
      <?php include APPLICATION_ROOT . '/src/Modules/Admin/Views/csrf-input.php'; ?>
      <button type="submit" class="btn">Run checks now</button>
    </form>
  </div>

  <?php if (($flash['success'] ?? '') !== ''): ?>
    <div class="settings-flash-message settings-flash-success"><?php echo Str::escape($flash['success']); ?></div>
  <?php endif; ?>
  <?php if (($flash['error'] ?? '') !== ''): ?>
    <div class="settings-flash-message settings-flash-error"><?php echo Str::escape($flash['error']); ?></div>
  <?php endif; ?>

  <?php if ($selected === null): ?>
    <p class="text-muted">No health checks have run yet. Run them now, or wait for the scheduled run.</p>
  <?php else: ?>
    <h3 class="health-check-run-title">
      <span class="health-check-status health-check-status-<?php echo Str::escape((string) $selected->status); ?>"><?php echo Str::escape($statusLabels[$selected->status] ?? (string) $selected->status); ?></span>
      <span><?php echo Str::escape(I18n::localizeDateTime((string) $selected->created_at)); ?></span>
      <span class="text-muted"><?php echo Str::escape((string) $selected->environment); ?></span>
    </h3>

    <?php if ($results !== null): ?>
      <table>
        <thead>
          <tr><th>Check</th><th>Status</th><th>Details</th></tr>
        </thead>
        <tbody>
          <?php foreach ($results as $result): ?>
            <tr>
              <td data-label="Check"><?php echo Str::escape((string) ($result['label'] ?? '')); ?></td>
              <td data-label="Status"><span class="health-check-status health-check-status-<?php echo Str::escape((string) ($result['status'] ?? '')); ?>"><?php echo Str::escape($statusLabels[$result['status'] ?? ''] ?? (string) ($result['status'] ?? '')); ?></span></td>
              <td data-label="Details"><?php echo Str::escape((string) ($result['message'] ?? '')); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="text-muted">This run predates health checks. Its audit report is kept below for reference.</p>
      <pre class="health-check-legacy-report"><?php echo Str::escape((string) ($selected->report ?? '')); ?></pre>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (!empty($history)): ?>
    <h3>History</h3>
    <table>
      <thead>
        <tr><th>Run</th><th>Status</th><th>Environment</th><th>Run by</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($history as $row): ?>
          <tr class="<?php echo $row['id'] === $selectedId ? 'health-check-selected-row' : ''; ?>">
            <td data-label="Run"><?php echo Str::escape(I18n::localizeDateTime((string) $row['created_at'])); ?></td>
            <td data-label="Status"><span class="health-check-status health-check-status-<?php echo Str::escape((string) $row['status']); ?>"><?php echo Str::escape($statusLabels[$row['status']] ?? (string) $row['status']); ?></span></td>
            <td data-label="Environment"><?php echo Str::escape((string) $row['environment']); ?></td>
            <td data-label="Run by"><?php echo Str::escape((string) ($row['username'] ?? 'Scheduler')); ?></td>
            <td data-label=""><a href="/admin/security/audit?id=<?php echo Str::escape(rawurlencode((string) $row['id'])); ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
