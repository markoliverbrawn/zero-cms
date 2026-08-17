<?php
// views/admin/preferences.php
use Zero\Core\App;
use Zero\Support\I18n;
use Zero\Support\Str;
?>
<div class="preferences-container">
  
  <!-- Page Header -->
  <div class="preferences-header">
    <span class="icon-svg preferences-header-icon">
      <?php echo \Zero\Core\App::svg('settings'); ?>
    </span>
    <h2 class="preferences-header-title"><?php echo I18n::t('user_preferences'); ?></h2>
  </div>

  <?php if (!empty($success)): ?>
    <div class="success-message" style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 12px; border-radius: 4px; margin-bottom: 25px; font-weight: bold;">
      <?php echo Str::escape($success); ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="error-message" style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 12px; border-radius: 4px; margin-bottom: 25px; font-weight: bold;">
      <?php echo Str::escape($error); ?>
    </div>
  <?php endif; ?>

  <form method="post" action="/admin/preferences" id="preferences-form" class="preferences-form">
    <?php include APPLICATION_ROOT . '/src/Modules/Admin/Views/csrf-input.php'; ?>

    <!-- 2-Column Grid Layout for Settings Cards -->
    <div class="preferences-grid">
      
      <!-- CARD 1: Interface & Appearance -->
      <div class="preferences-card">
        <h3 class="preferences-card-title">
          <span class="icon-svg preferences-card-title-icon">
            <?php echo \Zero\Core\App::svg('image'); ?>
          </span>
          <?php echo I18n::t('interface_appearance'); ?>
        </h3>

        <!-- Theme Preset -->
        <div class="preferences-form-group">
          <label for="theme_preset" class="preferences-label"><?php echo I18n::t('color_preset'); ?></label>
          <?php echo App::makeFormField('select', 'theme_preset', [
              'value' => $prefs['theme_preset'] ?? 'default',
              'options' => ['default' => 'Default Theme', 'vintage-greenscreen' => 'Vintage Greenscreen'],
              'attributes' => ['id' => 'theme_preset', 'class' => 'preferences-select'],
              'showLabel' => false,
              'guessHelperTextKey' => false,
          ])->render(); ?>
          <small class="preferences-help-text"><?php echo I18n::t('theme_preset_desc'); ?></small>
        </div>

        <!-- Theme Mode -->
        <div class="preferences-form-group compact">
          <label class="preferences-label"><?php echo I18n::t('theme_mode'); ?></label>
          <div class="preferences-radio-group">
            <label class="preferences-radio-label">
              <input type="radio" name="theme" value="light" <?php echo ($prefs['theme'] ?? 'light') === 'light' ? 'checked' : ''; ?> class="preferences-radio-input">
              <?php echo I18n::t('light_mode'); ?>
            </label>
            <label class="preferences-radio-label">
              <input type="radio" name="theme" value="dark" <?php echo ($prefs['theme'] ?? 'light') === 'dark' ? 'checked' : ''; ?> class="preferences-radio-input">
              <?php echo I18n::t('dark_mode'); ?>
            </label>
          </div>
          <small class="preferences-help-text"><?php echo I18n::t('theme_mode_desc'); ?></small>
        </div>
      </div>

      <!-- CARD 2: Language & Localization -->
      <div class="preferences-card">
        <h3 class="preferences-card-title">
          <span class="icon-svg preferences-card-title-icon">
            <?php echo \Zero\Core\App::svg('clock'); ?>
          </span>
          <?php echo I18n::t('language_localization'); ?>
        </h3>

        <!-- Language Preference -->
        <div class="preferences-form-group">
          <label for="language" class="preferences-label"><?php echo I18n::t('language'); ?></label>
          <?php echo App::makeFormField('select', 'language', [
              'value' => $prefs['language'] ?? 'en',
              'options' => ['en' => 'English', 'es' => 'Español', 'mi' => 'Māori', 'hr' => 'Hrvatski'],
              'attributes' => ['id' => 'language', 'class' => 'preferences-select'],
              'showLabel' => false,
              'guessHelperTextKey' => false,
          ])->render(); ?>
          <small class="preferences-help-text"><?php echo I18n::t('language_desc'); ?></small>
        </div>

        <!-- Timezone Selection -->
        <div class="preferences-form-group compact">
          <label for="timezone" class="preferences-label"><?php echo I18n::t('user_timezone'); ?></label>
          <?php echo App::makeFormField('select', 'timezone', [
              'value' => $prefs['timezone'] ?? 'UTC',
              'options' => $timezones,
              'attributes' => ['id' => 'timezone', 'class' => 'preferences-select'],
              'showLabel' => false,
              'guessHelperTextKey' => false,
          ])->render(); ?>
          <small class="preferences-help-text"><?php echo I18n::t('timezone_desc'); ?></small>
        </div>
      </div>

    </div>

    <!-- CARD 3: Workspace & Dashboard -->
    <div class="preferences-card full-width">
      <h3 class="preferences-card-title">
        <span class="icon-svg preferences-card-title-icon">
          <?php echo \Zero\Core\App::svg('zap'); ?>
        </span>
        <?php echo I18n::t('workspace_dashboard'); ?>
      </h3>
      
      <!-- Default Pagination Limit -->
      <div class="preferences-form-group" style="margin-bottom: 25px;">
        <label for="per_page" class="preferences-label"><?php echo I18n::t('default_pagination_limit'); ?></label>
        <?php
        $perPageLabelSuffix = I18n::t('id') === 'ID' ? 'items' : 'artículos';
        $perPageOptions = [];
        foreach ([10, 20, 50, 100] as $num) {
            $perPageOptions[$num] = $num . ' ' . $perPageLabelSuffix;
        }
        ?>
        <?php echo App::makeFormField('select', 'per_page', [
            'value' => (int)($prefs['per_page'] ?? 20),
            'options' => $perPageOptions,
            'attributes' => ['id' => 'per_page', 'class' => 'preferences-select', 'style' => 'max-width: 350px;'],
            'showLabel' => false,
            'guessHelperTextKey' => false,
        ])->render(); ?>
        <small class="preferences-help-text" style="margin-top: 5px;"><?php echo I18n::t('pagination_limit_desc'); ?></small>
      </div>

      <div style="border-top: 1px dashed color-mix(in srgb, var(--bg-color-inverse) 10%, var(--bg-color) 90%); margin-bottom: 20px; padding-top: 20px;">
        <strong style="display: block; font-size: 0.95rem; margin-bottom: 8px;"><?php echo I18n::t('dashboard_configurations'); ?></strong>
        <p class="preferences-help-text spaced" style="font-size: 0.85rem; margin-bottom: 20px;"><?php echo I18n::t('dashboard_configurations_desc'); ?></p>

        <!-- Grid layout for checkable blocks -->
        <div class="preferences-widgets-grid">
          <?php
            $site = \Zero\Core\App::getCurrentSite();
            $layout = $prefs['dashboard_layout'] ?? ['quick_links', 'recent_pages', 'recent_media', 'shop_orders_chart', 'shop_category_pie', 'recent_orders', 'security_state', 'security_logs', 'queue_summary', 'scheduler_summary'];
            
            $widgets = [];
            // Core Widgets
            $widgets['recent_pages'] = ['title' => I18n::t('recent_pages'), 'desc' => I18n::t('recent_pages')];
            $widgets['recent_media'] = ['title' => I18n::t('recent_media'), 'desc' => I18n::t('recent_media')];
            $widgets['quick_links'] = ['title' => I18n::t('quick_links'), 'desc' => I18n::t('quick_links')];
            
            // Blog Module Widget (Only if enabled!)
            if ($site && $site->isModuleEnabled('blog')) {
                $widgets['recent_posts'] = ['title' => I18n::t('recent_posts'), 'desc' => I18n::t('recent_posts')];
            }
            
            // Shop Module Widgets (Only if enabled!)
            if ($site && $site->isModuleEnabled('shop')) {
                $widgets['shop_orders_chart'] = ['title' => 'Shop Orders Chart', 'desc' => 'Interactive Sales Volume Over Time'];
                $widgets['shop_category_pie'] = ['title' => 'Category Sales Pie', 'desc' => 'Solid Pie Chart of Sales By Category'];
                $widgets['recent_orders'] = ['title' => 'Recent Shop Orders', 'desc' => 'List of Recent Transactions'];
            }

            // Security Module Widgets (Only if enabled!)
            if ($site && $site->isModuleEnabled('security')) {
                $widgets['security_state'] = ['title' => 'Platform Security State', 'desc' => 'Shows the latest security score and parameters matrix'];
                $widgets['security_logs'] = ['title' => 'Recent Security Events', 'desc' => 'Displays the latest administrative audit trail logs'];
            }

            // Queue Module Widgets (Only if enabled!)
            if ($site && $site->isModuleEnabled('queue')) {
                $widgets['queue_summary'] = ['title' => 'Job Queue Status', 'desc' => 'Displays the pending, active, and failed queue metrics'];
                $widgets['scheduler_summary'] = ['title' => 'Task Scheduler', 'desc' => 'Displays registered cron/scheduled tasks metrics and heartbeats'];
            }
          ?>
          <?php foreach ($widgets as $key => $meta):
              $isChecked = in_array($key, $layout);
              $checkedClass = $isChecked ? ' is-checked' : '';
          ?>
            <label class="dashboard-widget-toggle<?php echo $checkedClass; ?>">
              <div class="dashboard-widget-toggle-inner">
                <input type="checkbox" name="dashboard_layout[]" value="<?php echo $key; ?>" <?php echo $isChecked ? 'checked' : ''; ?> class="dashboard-widget-toggle-checkbox">
                <div>
                  <strong class="dashboard-widget-toggle-title"><?php echo Str::escape($meta['title']); ?></strong>
                  <span class="dashboard-widget-toggle-desc"><?php echo Str::escape($meta['desc']); ?></span>
                </div>
              </div>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Action Buttons Block -->
    <div class="preferences-actions">
      <a href="/admin/dashboard" class="preferences-btn-secondary">
        <?php echo I18n::t('back_to_dashboard'); ?>
      </a>
      <button type="submit" class="preferences-btn-primary">
        <?php echo I18n::t('save_preferences'); ?>
      </button>
    </div>

  </form>
</div>

<!-- Extra link for live preview load of preset CSS if selected -->
<link id="vintage-greenscreen-stylesheet" rel="stylesheet" href="/assets/css/admin-themes/admin-vintage-greenscreen.css" style="display:none">

<script src="/assets/js/admin/preferences.js"></script>
