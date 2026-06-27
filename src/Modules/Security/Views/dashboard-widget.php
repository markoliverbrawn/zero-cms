<?php
// src/Modules/Security/Views/dashboard-widget.php

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Support\I18n;

$renderWidgetKey = $renderWidgetKey ?? '';
$activeSiteId = App::getCurrentSiteId();

// ==========================================
// 1. WIDGET: PLATFORM SECURITY STATE
// ==========================================
if ($renderWidgetKey === 'security_state' && in_array('security_state', $enabledWidgets ?? [])):
    $latestAudit = DB::query(
        "SELECT score, created_at, environment, telemetry
         FROM security_audits 
         WHERE site_id = ? AND deleted_at IS NULL 
         ORDER BY created_at DESC LIMIT 1",
        [$activeSiteId]
    )->fetch();

    $telemetry = [];
    if ($latestAudit && !empty($latestAudit['telemetry'])) {
        $telemetry = json_decode($latestAudit['telemetry'], true);
    }

    $score = $latestAudit ? intval($latestAudit['score']) : null;
    $auditDate = $latestAudit ? $latestAudit['created_at'] : null;

    $installFileWarning = $telemetry['install_file_exists'] ?? false;
    $benchmarkingWarning = $telemetry['benchmarking_enabled'] ?? false;
    $defaultPasswordWarning = $telemetry['default_admin_password_in_use'] ?? false;
    $storageAccessWarning = $telemetry['storage_directory_open_access'] ?? false;
    $environment = $telemetry['environment'] ?? 'production';
?>
  <!-- WIDGET: PLATFORM SECURITY STATE -->
  <div class="dashboard-card security-widget-card draggable-widget" draggable="true" data-widget="security_state">
    <h3>
      <span class="icon-svg">
        <?php echo App::svg('shield'); ?>
      </span>
      <span>Platform Security State</span>
    </h3>

    <?php if (!$latestAudit): ?>
      <div class="dashboard-empty-state-widget">
        <p class="text-muted">No security audits archived yet.</p>
        <p><a href="/admin/security/audit" class="btn">Run Initial Security Audit</a></p>
      </div>
    <?php else: ?>
      <!-- Score & Last Audited Details -->
      <div class="security-widget-score-container">
        <div>
          <div class="security-widget-score-label">Security Score</div>
          <div class="security-widget-score-value <?php echo ($score >= 85 ? 'score-safe' : ($score >= 60 ? 'score-warn' : 'score-danger')); ?>">
            <?php echo $score; ?> / 100
          </div>
        </div>
        <div class="security-widget-audit-date">
          <div class="audit-date-label">Last Audited</div>
          <div class="audit-date-value">
            <?php echo htmlspecialchars(I18n::localizeDateTime($auditDate, 'Y-m-d H:i'), ENT_QUOTES, 'UTF-8'); ?>
          </div>
        </div>
      </div>

      <!-- Native SVG XML progress bar (Zero Inline Styles!) -->
      <div class="security-widget-progress-container">
        <svg class="security-widget-progress-svg" width="100%" height="8">
          <rect class="progress-track" width="100%" height="8" rx="4"></rect>
          <rect class="progress-fill <?php echo ($score >= 85 ? 'fill-safe' : ($score >= 60 ? 'fill-warn' : 'fill-danger')); ?>" width="<?php echo $score; ?>%" height="8" rx="4"></rect>
        </svg>
      </div>

      <!-- Telemetry Parameters Matrix -->
      <div class="security-widget-details">
        <div class="security-detail-row">
          <span class="detail-label">Runtime Environment</span>
          <span class="detail-value">
            <span class="status-badge <?php echo ($environment === 'dev' ? 'badge-warn' : 'badge-info'); ?>">
              <?php echo htmlspecialchars(strtoupper($environment), ENT_QUOTES, 'UTF-8'); ?>
            </span>
          </span>
        </div>
        <div class="security-detail-row">
          <span class="detail-label">Install File Status</span>
          <span class="detail-value">
            <?php if ($installFileWarning): ?>
              <span class="status-badge badge-danger">Vulnerable</span>
            <?php else: ?>
              <span class="status-badge badge-ok">Clean</span>
            <?php endif; ?>
          </span>
        </div>
        <div class="security-detail-row">
          <span class="detail-label">Benchmarking Hooks</span>
          <span class="detail-value">
            <?php if ($benchmarkingWarning): ?>
              <span class="status-badge badge-warn">Active</span>
            <?php else: ?>
              <span class="status-badge badge-ok">Secured</span>
            <?php endif; ?>
          </span>
        </div>
        <div class="security-detail-row">
          <span class="detail-label">Default Admin Password</span>
          <span class="detail-value">
            <?php if ($defaultPasswordWarning): ?>
              <span class="status-badge badge-danger">Vulnerable</span>
            <?php else: ?>
              <span class="status-badge badge-ok">Secure</span>
            <?php endif; ?>
          </span>
        </div>
      </div>

      <div class="dashboard-card-footer">
        <a href="/admin/security/audit">Open Security Console &rarr;</a>
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
          <li class="<?php echo $logClass; ?>">
            <div>
              <strong><?php echo htmlspecialchars($log['action'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>
              <span class="text-muted">by <?php echo htmlspecialchars($log['username'] ?? 'System', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <span class="text-muted"><?php echo htmlspecialchars(I18n::localizeDateTime($log['created_at'], 'M d, H:i'), ENT_QUOTES, 'UTF-8'); ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <div class="dashboard-card-footer">
      <a href="/admin/list/audit_logs">View All Audit Logs &rarr;</a>
    </div>
  </div>
<?php endif; ?>
