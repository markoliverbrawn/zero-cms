<?php
use Zero\Core\App;
use Zero\Database\DB;
use Zero\Models\User;
use Zero\Support\I18n;
use Zero\Support\Security;

$userId = $_SESSION['user_id'] ?? null;
$userPrefs = [];
if ($userId) {
    $userPrefs = User::getPreferencesForUser($userId);
}

// Compile all theoretically available widgets based on enabled modules
$allPossibleWidgets = ['quick_links', 'recent_pages', 'recent_media'];

$activeSite = App::getCurrentSite();
$activeSiteId = App::getCurrentSiteId();

if ($activeSite) {
    if ($activeSite->isModuleEnabled('shop')) {
        $allPossibleWidgets[] = 'shop_orders_chart';
        $allPossibleWidgets[] = 'shop_category_pie';
        $allPossibleWidgets[] = 'recent_orders';
    }
    if ($activeSite->isModuleEnabled('security')) {
        $allPossibleWidgets[] = 'security_state';
        $allPossibleWidgets[] = 'security_logs';
    }
    if ($activeSite->isModuleEnabled('queue')) {
        $allPossibleWidgets[] = 'queue_summary';
        $allPossibleWidgets[] = 'scheduler_summary';
    }
    if ($activeSite->isModuleEnabled('site-search')) {
        $allPossibleWidgets[] = 'site_search_summary';
    }
}

$enabledWidgets = $userPrefs['dashboard_layout'] ?? $allPossibleWidgets;

// Super Admin widget auto-activation hook (strictly enforcing Guideline 24)
$currentUser = App::getCurrentUser();
if ($currentUser && $currentUser->role === 'super_admin') {
    if (!in_array('site_search_summary', $enabledWidgets)) {
        $enabledWidgets[] = 'site_search_summary';
    }
}

// Fetch recent items for core system widgets strictly filtered by active site/domain!
$recentPosts = DB::query("SELECT * FROM blog_posts WHERE site_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 5", [$activeSiteId])->fetchAll();
$recentPages = DB::query("SELECT * FROM pages WHERE site_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 5", [$activeSiteId])->fetchAll();
$recentMedia = DB::query("SELECT * FROM media WHERE site_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 5", [$activeSiteId])->fetchAll();
?>

<div class="dashboard-container">
  <div class="dashboard-header">
    <h2><?php echo I18n::t('admin_dashboard'); ?></h2>
    <a href="/admin/preferences" class="dashboard-configure-btn">
      <span class="icon-svg">
        <?php echo App::svg('settings'); ?>
      </span>
      <?php echo I18n::t('configure_dashboard'); ?>
    </a>
  </div>

  <div class="dashboard-grid" id="dashboard-widgets-grid">
    <?php foreach ($enabledWidgets as $widgetKey): ?>
      
      <!-- CORE WIDGET: QUICK LINKS -->
      <?php if ($widgetKey === 'quick_links'): ?>
        <div class="dashboard-card draggable-widget" draggable="true" data-widget="quick_links">
          <h3>
            <span class="icon-svg">
              <?php echo App::svg('zap'); ?>
            </span>
            <?php echo I18n::t('quick_links'); ?>
          </h3>
          <ul class="dashboard-quick-links">
            <?php if ($activeSite && $activeSite->isModuleEnabled('blog')): ?>
              <li>
                <a href="/admin/list/posts">
                  <span><?php echo I18n::t('manage_posts'); ?></span> 
                  <span>&rarr;</span>
                </a>
              </li>
            <?php endif; ?>
            <li>
              <a href="/admin/list/pages">
                <span><?php echo I18n::t('manage_pages'); ?></span> 
                <span>&rarr;</span>
              </a>
            </li>
            <li>
              <a href="/admin/list/files">
                <span><?php echo I18n::t('media_library'); ?></span> 
                <span>&rarr;</span>
              </a>
            </li>
            <li>
              <a href="/admin/list/users">
                <span><?php echo I18n::t('manage_users'); ?></span> 
                <span>&rarr;</span>
              </a>
            </li>
          </ul>
        </div>
      <?php endif; ?>

      <!-- CORE WIDGET: RECENT PAGES -->
      <?php if ($widgetKey === 'recent_pages'): ?>
        <div class="dashboard-card draggable-widget" draggable="true" data-widget="recent_pages">
          <h3>
            <span class="icon-svg">
              <?php echo App::svg('book-open'); ?>
            </span>
            <?php echo I18n::t('recent_pages'); ?>
          </h3>
          <?php if (empty($recentPages)): ?>
            <p class="text-muted"><?php echo I18n::t('no_pages_found'); ?></p>
          <?php else: ?>
            <ul class="dashboard-list-items">
              <?php foreach ($recentPages as $page): ?>
                <li>
                  <div>
                    <a href="/admin/pages/edit?id=<?php echo $page['id']; ?>" title="<?php echo htmlspecialchars($page['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                      <?php echo htmlspecialchars($page['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                  </div>
                  <span class="text-muted">
                    <?php echo htmlspecialchars(I18n::localizeDateTime($page['created_at'], 'Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- CORE WIDGET: RECENT MEDIA -->
      <?php if ($widgetKey === 'recent_media'): ?>
        <div class="dashboard-card draggable-widget" draggable="true" data-widget="recent_media">
          <h3>
            <span class="icon-svg">
              <?php echo App::svg('image'); ?>
            </span>
            <?php echo I18n::t('recent_media'); ?>
          </h3>
          <?php if (empty($recentMedia)): ?>
            <p class="text-muted"><?php echo I18n::t('no_media_found'); ?></p>
          <?php else: ?>
            <div class="dashboard-recent-media-grid">
              <?php foreach ($recentMedia as $media):
                $isImg = !empty($media['mime']) && str_starts_with($media['mime'], 'image/');
              ?>
                <div class="dashboard-recent-media-item" title="<?php echo htmlspecialchars($media['filename'], ENT_QUOTES, 'UTF-8'); ?>">
                  <?php if ($isImg): ?>
                    <img src="<?php echo htmlspecialchars($media['path'], ENT_QUOTES, 'UTF-8'); ?>" />
                  <?php else: ?>
                    <div class="file-placeholder">
                      <span class="icon-svg">
                        <?php echo App::svg('file'); ?>
                      </span>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="dashboard-card-footer">
              <a href="/admin/list/files"><?php echo I18n::t('view_media_library'); ?> &rarr;</a>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- DYNAMIC WIDGETS DELEGATION (shop, blog, etc.) -->
      <?php if ($widgetKey !== 'quick_links' && $widgetKey !== 'recent_pages' && $widgetKey !== 'recent_media'): ?>
        <?php 
        foreach (App::getModules() as $module) {
            if ($activeSite && $activeSite->isModuleEnabled($module->getId())) {
                $widgetView = $module->getDashboardWidgetView();
                if ($widgetView) {
                    $ref = new \ReflectionClass($module);
                    $moduleDir = dirname($ref->getFileName());
                    $widgetPath = $moduleDir . '/Views/' . basename($widgetView) . '.php';
                    
                    if (file_exists($widgetPath)) {
                        $renderWidgetKey = $widgetKey;
                        include $widgetPath;
                    }
                }
            }
        }
        ?>
      <?php endif; ?>

    <?php endforeach; ?>
  </div>

  <?php if (empty($enabledWidgets)): ?>
    <div class="dashboard-empty-state">
      <span class="icon-svg">
        <?php echo App::svg('inbox'); ?>
      </span>
      <h3><?php echo I18n::t('dashboard_empty_title'); ?></h3>
      <p><?php echo I18n::t('dashboard_empty_desc'); ?></p>
      <a href="/admin/preferences" class="dashboard-empty-state-btn"><?php echo I18n::t('configure_widgets'); ?></a>
    </div>
  <?php endif; ?>
</div>

<script>
window.ADMIN_CSRF_TOKEN = "<?php echo htmlspecialchars($csrf ?? Security::csrfToken(), ENT_QUOTES, 'UTF-8'); ?>";
</script>
<script src="/assets/js/admin/dashboard.js"></script>
