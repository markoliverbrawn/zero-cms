<?php
// src/Modules/Queue/Views/dashboard-widget.php

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Support\I18n;

$renderWidgetKey = $renderWidgetKey ?? '';
$activeSiteId = App::getCurrentSiteId();

if ($renderWidgetKey === 'queue_summary' && in_array('queue_summary', $enabledWidgets ?? [])):
    // Fetch counts grouped by status
    $counts = [
        'pending' => 0,
        'reserved' => 0,
        'failed' => 0,
        'completed' => 0
    ];
    
    $stmt = DB::query("
        SELECT status, COUNT(*) as count 
        FROM queue_jobs 
        WHERE site_id = ? AND deleted_at IS NULL 
        GROUP BY status
    ", [$activeSiteId]);
    
    while ($row = $stmt->fetch()) {
        $counts[$row['status']] = intval($row['count']);
    }

    // Determine if worker is active using the 10-second file modification heartbeat
    $heartbeatPath = APPLICATION_ROOT . '/storage/queue-worker-heartbeat.txt';
    $workerStatus = 'Offline';
    $workerStatusClass = 'offline';
    if (file_exists($heartbeatPath)) {
        $lastHeartbeat = filemtime($heartbeatPath);
        if (time() - $lastHeartbeat < 30) { // Polled within the last 30 seconds
            $workerStatus = 'Active';
            $workerStatusClass = 'active';
        }
    }
?>
  <!-- WIDGET: JOB QUEUE SUMMARY -->
  <div class="dashboard-card draggable-widget" draggable="true" data-widget="queue_summary">
    <h3>
      <span class="icon-svg">
        <?php echo App::svg('clock'); ?>
      </span>
      <span>Job Queue Status</span>
      <div class="queue-status-indicator header-indicator">
        <span class="status-pulse <?php echo $workerStatusClass; ?>"></span>
        <span class="status-label">Runner: <?php echo $workerStatus; ?></span>
      </div>
    </h3>

    <div class="queue-widget">
      
      <!-- Metrics Grid Cards -->
      <div class="queue-metrics-grid">
        <div class="queue-stat-card">
          <div class="queue-stat-value state-pending">
            <?php echo $counts['pending']; ?>
          </div>
          <div class="queue-stat-label">Pending Tasks</div>
        </div>

        <div class="queue-stat-card">
          <div class="queue-stat-value state-failed <?php echo ($counts['failed'] > 0 ? 'has-errors' : ''); ?>">
            <?php echo $counts['failed']; ?>
          </div>
          <div class="queue-stat-label">Failed Tasks</div>
        </div>
      </div>

      <!-- Telemetry Parameters Matrix -->
      <div class="queue-telemetry-list">
        <div class="queue-telemetry-row">
          <span class="telemetry-label">Active Processing</span>
          <span class="telemetry-value" style="color: var(--warning-color, #eab308);">
            <?php echo $counts['reserved']; ?>
          </span>
        </div>
        <div class="queue-telemetry-row">
          <span class="telemetry-label">Completed Tasks</span>
          <span class="telemetry-value" style="color: var(--success-color, #22c55e);">
            <?php echo $counts['completed']; ?>
          </span>
        </div>
      </div>

    </div>

    <div class="dashboard-card-footer">
      <a href="/admin/list/queue_jobs">Manage Job Queue &rarr;</a>
    </div>
  </div>
<?php endif; ?>

<?php
if ($renderWidgetKey === 'scheduler_summary' && in_array('scheduler_summary', $enabledWidgets ?? [])):
    // Fetch tracked scheduled tasks
    $tasks = DB::query("
        SELECT task_key, expression, last_run_at 
        FROM queue_scheduled_tasks 
        WHERE site_id = ? AND deleted_at IS NULL 
        ORDER BY task_key ASC
    ", [$activeSiteId])->fetchAll();

    // Determine if scheduler daemon is active using the 10-second file modification heartbeat
    $schedulerHeartbeatPath = APPLICATION_ROOT . '/storage/scheduler-heartbeat.txt';
    $schedulerStatus = 'Offline';
    $schedulerStatusClass = 'offline';
    if (file_exists($schedulerHeartbeatPath)) {
        $lastHeartbeat = filemtime($schedulerHeartbeatPath);
        if (time() - $lastHeartbeat < 90) { // Polled within the last 90 seconds (loop is 60 seconds)
            $schedulerStatus = 'Active';
            $schedulerStatusClass = 'active';
        }
    }
?>
  <!-- WIDGET: JOB SCHEDULER SUMMARY -->
  <div class="dashboard-card draggable-widget" draggable="true" data-widget="scheduler_summary">
    <h3>
      <span class="icon-svg">
        <?php echo App::svg('book-open'); ?>
      </span>
      <span>Task Scheduler</span>
      <div class="queue-status-indicator header-indicator">
        <span class="status-pulse <?php echo $schedulerStatusClass; ?>"></span>
        <span class="status-label">Scheduler: <?php echo $schedulerStatus; ?></span>
      </div>
    </h3>

    <div class="queue-widget">
      <!-- Telemetry Parameters Matrix -->
      <div class="queue-telemetry-list">
        <?php if (empty($tasks)): ?>
          <div class="queue-telemetry-row" style="justify-content: center; border-bottom: none;">
            <span class="detail-label" style="color: var(--text-muted, #64748b); font-size: 0.85rem;">No scheduled tasks registered.</span>
          </div>
        <?php else: ?>
          <?php foreach ($tasks as $task): 
              $friendlyName = basename(str_replace('\\', '/', $task['task_key']));
              $lastRun = $task['last_run_at'] ? I18n::localizeDateTime($task['last_run_at'], 'H:i:s') : 'Never';
          ?>
            <div class="queue-telemetry-row">
              <span class="telemetry-label">
                <strong><?php echo htmlspecialchars($friendlyName); ?></strong>
                <span style="font-size: 0.75rem; color: var(--text-muted, #64748b); display: block;">Interval: <?php echo htmlspecialchars($task['expression']); ?></span>
              </span>
              <span class="telemetry-value">
                <span class="status-badge <?php echo ($task['last_run_at'] ? 'badge-ok' : 'badge-info'); ?>">
                  Last Run: <?php echo $lastRun; ?>
                </span>
              </span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="dashboard-card-footer">
      <a href="/admin/list/queue_jobs">Manage Queue &rarr;</a>
    </div>
  </div>
<?php endif; ?>
