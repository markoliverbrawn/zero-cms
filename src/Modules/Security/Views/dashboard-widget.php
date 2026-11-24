<?php
// src/Modules/Security/Views/dashboard-widget.php

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Support\I18n;
use Zero\Support\Str;

$renderWidgetKey = $renderWidgetKey ?? '';
$activeSiteId = App::getCurrentSiteId();

// ==========================================
// 1. WIDGET: PLATFORM SECURITY STATE (latest health check run)
// ==========================================
if ($renderWidgetKey === 'security_state' && in_array('security_state', $enabledWidgets ?? [])):
    $latestRun = DB::query(
        "SELECT id, status, results, created_at
         FROM security_audits
         WHERE site_id = ? AND results IS NOT NULL AND deleted_at IS NULL
         ORDER BY created_at DESC LIMIT 1",
        [$activeSiteId]
    )->fetch();
    $runResults = $latestRun ? json_decode((string) $latestRun['results'], true) : null;
    $runResults = is_array($runResults) ? $runResults : [];
    $statusLabels = ['pass' => 'Pass', 'warn' => 'Warning', 'fail' => 'Fail'];
    $badgeClasses = ['pass' => 'badge-ok', 'warn' => 'badge-warn', 'fail' => 'badge-danger'];
?>
  <!-- WIDGET: PLATFORM SECURITY STATE -->
  <div class="dashboard-card security-widget-card draggable-widget" draggable="true" data-widget="security_state">
    <h3>
      <span class="icon-svg">
        <?php echo App::svg('shield'); ?>
      </span>
      <span>Health Check</span>
    </h3>

    <?php if (!$latestRun): ?>
      <div class="dashboard-empty-state-widget">
        <p class="text-muted">No health checks have run yet.</p>
        <p><a href="/admin/security/audit" class="btn">Open Health Check</a></p>
      </div>
    <?php else: ?>
      <div class="security-widget-score-container">
        <div>
          <div class="security-widget-score-label">Status</div>
          <div class="security-widget-score-value health-check-status-text-<?php echo Str::escape((string) $latestRun['status']); ?>">
            <?php echo Str::escape($statusLabels[$latestRun['status']] ?? (string) $latestRun['status']); ?>
          </div>
        </div>
        <div class="security-widget-audit-date">
          <div class="audit-date-label">Last Run</div>
          <div class="audit-date-value">
            <?php echo Str::escape(I18n::localizeDateTime((string) $latestRun['created_at'], 'M d, H:i')); ?>
          </div>
        </div>
      </div>

      <div class="security-widget-details">
        <?php foreach ($runResults as $result): ?>
          <div class="security-detail-row">
            <span class="detail-label"><?php echo Str::escape((string) ($result['label'] ?? '')); ?></span>
            <span class="detail-value">
              <span class="status-badge <?php echo Str::escape($badgeClasses[$result['status'] ?? ''] ?? 'badge-info'); ?>" title="<?php echo Str::escape((string) ($result['message'] ?? '')); ?>"><?php echo Str::escape($statusLabels[$result['status'] ?? ''] ?? ''); ?></span>
            </span>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="dashboard-card-footer">
        <a href="/admin/security/audit?id=<?php echo Str::escape(rawurlencode((string) $latestRun['id'])); ?>">View Health Check &rarr;</a>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php
// ==========================================
// 2. WIDGET: RECENT SECURITY EVENTS
// ==========================================
if ($renderWidgetKey === 'security_logs' && in_array('security_logs', $enabledWidgets ?? [])):
    $recentLogs = DB::query(
        "SELECT al.*, u.username 
         FROM audit_logs al
         LEFT JOIN users u ON al.user_id = u.id
         WHERE al.site_id = ? AND al.deleted_at IS NULL
         ORDER BY al.created_at DESC LIMIT 4",
         [$activeSiteId]
    )->fetchAll();
?>
  <!-- WIDGET: RECENT SECURITY EVENTS -->
  <div class="dashboard-card security-widget-card draggable-widget" draggable="true" data-widget="security_logs">
    <h3>
      <span class="icon-svg">
        <?php echo App::svg('shield'); ?>
      </span>
      <span>Recent Security Events</span>
    </h3>

    <?php if (empty($recentLogs)): ?>
      <p class="security-widget-logs-empty">No security logs captured yet.</p>
    <?php else: ?>
      <ul class="dashboard-list-items security-widget-logs-list">
        <?php foreach ($recentLogs as $log): ?>
          <?php 
            $actionKey = strtolower($log['action'] ?? '');
            $logClass = 'log-action-' . preg_replace('/[^a-z0-9_-]/', '_', $actionKey);
          ?>
          <li class="<?php echo Str::escape($logClass); ?>">
            <div>
              <strong><?php echo Str::escape($log['action'] ?? ''); ?></strong>
              <span class="text-muted">by <?php echo Str::escape($log['username'] ?? 'System'); ?></span>
            </div>
            <span class="text-muted"><?php echo Str::escape(I18n::localizeDateTime($log['created_at'], 'M d, H:i')); ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <div class="dashboard-card-footer">
      <a href="/admin/list/audit_logs">View All Audit Logs &rarr;</a>
    </div>
  </div>
<?php endif; ?>
