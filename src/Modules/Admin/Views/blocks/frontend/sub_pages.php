<?php
// src/Modules/Admin/Views/blocks/frontend/sub_pages.php
// Dynamic Sub-Pages Grid block frontend view template with clientside list filters

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Support\Security;

$siteId = App::getCurrentSiteId();

// Resolve active page slug dynamically from requested URI
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$parentSlug = trim($requestPath, '/');
if (strpos($parentSlug, 'post/') === 0) {
    $parentSlug = substr($parentSlug, 5);
}

$subPages = [];
if (!empty($parentSlug)) {
    try {
        $subPages = DB::query("
            SELECT title, slug, summary FROM pages 
            WHERE slug LIKE ? AND slug NOT LIKE ? AND status = 'published' AND site_id = ? AND deleted_at IS NULL
            ORDER BY precedence ASC, title ASC
        ", [$parentSlug . '/%', $parentSlug . '/%/%', $siteId])->fetchAll();
    } catch (\Exception $e) {}
}

$showSearch = count($subPages) > 6;
?>
<div class="block block-sub-pages">
  <?php if (!empty($block['content'])): ?>
    <div class="block-content sub-pages-block-desc">
      <?php echo Security::sanitizeHtml($block['content'] ?? ''); ?>
    </div>
  <?php endif; ?>

  <div class="sub-pages-wrapper">
    <?php if (empty($subPages)): ?>
      <p class="sub-pages-empty">No sub-topics are currently published under this section.</p>
    <?php else: ?>
      
      <?php if ($showSearch): ?>
        <!-- Modern, High-Contrast Clientside Search Box (renders only when sub-pages count > 6) -->
        <div class="sub-pages-search-wrapper">
          <svg class="sub-pages-search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input type="text" class="sub-pages-search-input" placeholder="Search sub-topics..." autocomplete="off">
        </div>
      <?php endif; ?>

      <div class="sub-pages-grid">
        <?php foreach ($subPages as $sp): ?>
          <?php
          $preview = htmlspecialchars($sp['summary'] ?? '', ENT_QUOTES, 'UTF-8');
          if (empty($preview)) {
              $preview = 'Explore our detailed guidelines, developer tutorials, and native code samples...';
          }
          ?>
          <div class="sub-pages-card" onclick="window.location.href='/<?php echo htmlspecialchars($sp['slug'], ENT_QUOTES, 'UTF-8'); ?>'">
            <h3 class="sub-pages-card-title">
              <a href="/<?php echo htmlspecialchars($sp['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($sp['title'], ENT_QUOTES, 'UTF-8'); ?>
              </a>
            </h3>
            <p class="sub-pages-card-excerpt"><?php echo $preview; ?></p>
            <div class="sub-pages-card-btn-container">
              <span class="sub-pages-card-link">View Documentation ➔</span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
