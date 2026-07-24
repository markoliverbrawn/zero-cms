<?php
// src/Modules/Search/Views/search_widget.php

use Zero\Core\App;
use Zero\Core\Env;
use Zero\Database\DB;
use Zero\Support\I18n;
use Zero\Support\Str;

$renderWidgetKey = $renderWidgetKey ?? '';
$activeSiteId = App::getCurrentSiteId();

if ($renderWidgetKey === 'site_search_summary' && in_array('site_search_summary', $enabledWidgets ?? [])):
    // 1. Fetch total record count in index
    $totalIndexed = 0;
    try {
        $stmt = DB::query("SELECT COUNT(*) AS count FROM search_index WHERE site_id = ?", [$activeSiteId]);
        $totalIndexed = (int)($stmt->fetch()['count'] ?? 0);
    } catch (\Exception $e) {
        // Fallback if table doesn't exist yet
    }

    // 2. Fetch active driver
    $activeDriver = Env::get('SEARCH_DRIVER', 'database');
?>
  <!-- WIDGET: SITE SEARCH INDEX -->
  <div class="dashboard-card draggable-widget" draggable="true" data-widget="site_search_summary" id="site-search-widget-card">
    <h3>
      <span class="icon-svg">
        <?php echo App::svg('zap'); ?>
      </span>
      <span>Search Index Registry</span>
    </h3>

    <div class="search-widget">
      <!-- Metrics Grid -->
      <div class="search-metrics-grid">
        <div class="search-stat-card">
          <div class="search-stat-value" id="search-widget-count">
            <?php echo $totalIndexed; ?>
          </div>
          <div class="search-stat-label">Indexed Items</div>
        </div>

        <div class="search-stat-card">
          <div class="search-stat-value" style="font-size: 1.15rem; font-family: inherit; line-height: 1.75rem; text-transform: uppercase;">
            <?php echo Str::escape($activeDriver); ?>
          </div>
          <div class="search-stat-label">Active Driver</div>
        </div>
      </div>

      <!-- Action & Progress zone -->
      <div class="search-action-zone">
        <button class="btn-reindex-trigger" id="btn-trigger-reindex">
          <span class="icon-svg">
            <?php echo App::svg('settings'); ?>
          </span>
          <span id="btn-reindex-label">Run Full Re-index</span>
        </button>

        <!-- Progress Tracker Bar -->
        <div class="search-progress-container" id="reindex-progress-container">
          <div class="progress-header-info">
            <span id="reindex-progress-status">Indexing...</span>
            <span id="reindex-progress-percent">0%</span>
          </div>
          <div class="progress-bar-track">
            <div class="progress-bar-fill" id="reindex-progress-fill"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="dashboard-card-footer">
      <a href="/search?q=Zero" target="_blank">Test Public Search &rarr;</a>
    </div>
  </div>
<?php endif; ?>
