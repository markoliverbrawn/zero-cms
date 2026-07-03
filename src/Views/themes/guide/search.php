<?php
// src/Views/themes/guide/search.php

use Zero\Core\App;
use Zero\Support\I18n;
?>

<h2 style="margin-top: 0; margin-bottom: 25px; border-bottom: 2px solid #e9ecef; padding-bottom: 10px; font-weight: 700; font-size: 1.5rem;">
  Search Results
</h2>

<p style="color: var(--text-muted); margin-bottom: 30px;">
  <?php if ($q !== ''): ?>
    Showing matches for "<strong><?php echo htmlspecialchars($q); ?></strong>"
  <?php else: ?>
    Please enter a search query in the search bar above.
  <?php endif; ?>
</p>

<?php if (!empty($results)): ?>
  <div style="display: flex; flex-direction: column; gap: 25px;">
    <?php foreach ($results as $res): ?>
      <div class="search-result-card" style="border-bottom: 1px solid #e9ecef; padding-bottom: 25px;">
        <h3 style="margin-top: 0; margin-bottom: 8px; font-size: 1.25rem;">
          <a href="<?php echo htmlspecialchars($res['url'] ?? '', ENT_QUOTES, "UTF-8"); ?>" style="color: var(--accent-color); text-decoration: none; font-weight: bold;">
            <?php echo htmlspecialchars($res['title'] ?? '', ENT_QUOTES, "UTF-8"); ?>
          </a>
        </h3>
        
        <p style="margin: 0; font-size: 0.95rem; color: #495057; line-height: 1.5;">
          <?php
            $preview = '';
            $content = $res['content'] ?? '';
            $cleanText = strip_tags($content);
            if ($q !== '' && ($pos = stripos($cleanText, $q)) !== false) {
                $start = max(0, $pos - 80);
                $length = 180;
                $snippet = substr($cleanText, $start, $length);
                $preview = ($start > 0 ? '...' : '') . $snippet . (strlen($cleanText) > ($start + $length) ? '...' : '');
            } else {
                $preview = substr($cleanText, 0, 180) . (strlen($cleanText) > 180 ? '...' : '');
            }
            if (empty($preview)) {
                $preview = 'Click to view this documentation page and read the detailed technical specification...';
            }
            echo htmlspecialchars($preview, ENT_QUOTES, 'UTF-8');
          ?>
        </p>
        <a href="<?php echo htmlspecialchars($res['url'] ?? '', ENT_QUOTES, "UTF-8"); ?>" style="display: inline-block; margin-top: 10px; font-size: 0.85rem; font-weight: 600; color: var(--accent-color); text-decoration: none;">View Page ➔</a>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Pagination Controls -->
  <?php echo App::renderPagination($pagination ?? [], '/search', $_GET); ?>
<?php else: ?>
  <?php if ($q !== ''): ?>
    <div style="text-align: center; padding: 40px 0; color: var(--text-muted);">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: #dee2e6; margin-bottom: 10px; display: block; margin: 0 auto 10px auto;">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
      </svg>
      <p style="margin: 0;">No matching documentation pages found.</p>
    </div>
  <?php endif; ?>
<?php endif; ?>
