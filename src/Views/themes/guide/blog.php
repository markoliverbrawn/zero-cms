<?php
// src/Views/themes/guide/blog.php

use Zero\Core\App;
use Zero\Support\Security;

$hasHeroBlock = false;
if (!empty($post->content)) {
    $blocks = json_decode($post->content, true);
    if (is_array($blocks)) {
        foreach ($blocks as $b) {
            if (($b['type'] ?? '') === 'baseline') {
                $hasHeroBlock = true;
                break;
            }
        }
    }
}
$shouldOmitTitle = !empty($post->omit_title) || $hasHeroBlock;
?>
<div class="blog-landing-container">
  <!-- Blog Parent Page Title & Body -->
  <?php if (!$shouldOmitTitle): ?>
    <h1 class="blog-header-title">
      <?php echo htmlspecialchars($post->title ?? '', ENT_QUOTES, "UTF-8"); ?>
    </h1>
  <?php endif; ?>
  
  <div class="blog-header-meta">
    <span class="icon-svg">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
        <line x1="16" y1="2" x2="16" y2="6"></line>
        <line x1="8" y1="2" x2="8" y2="6"></line>
        <line x1="3" y1="10" x2="21" y2="10"></line>
      </svg>
    </span>
    <span>Technical Articles &amp; Insights</span>
  </div>

  <!-- Parent Page Intro Content -->
  <div class="blog-intro-content">
    <?php
    $content = $post->content ?? '';
    $decodedBlocks = json_decode($content, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedBlocks)) {
        foreach ($decodedBlocks as $block) {
            echo Security::sanitizeHtml($block['content'] ?? '');
        }
    } else {
        echo Security::sanitizeHtml($content);
    }
    ?>
  </div>

  <!-- Articles List -->
  <h2 class="blog-section-title">
    Latest Publications
  </h2>

  <?php if (!empty($posts)): ?>
    <div class="blog-cards-list">
      <?php foreach ($posts as $p): ?>
        <div class="blog-card" onclick="window.location.href='/post/<?php echo htmlspecialchars($p->slug, ENT_QUOTES, 'UTF-8'); ?>'">
          <div>
            <h3 class="blog-card-title">
              <a href="/post/<?php echo htmlspecialchars($p->slug, ENT_QUOTES, 'UTF-8'); ?>" style="color: inherit; text-decoration: none;">
                <?php echo htmlspecialchars($p->title, ENT_QUOTES, 'UTF-8'); ?>
              </a>
            </h3>
            <div class="blog-card-meta">
              <span class="icon-svg">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                  <line x1="16" y1="2" x2="16" y2="6"></line>
                  <line x1="8" y1="2" x2="8" y2="6"></line>
                  <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
              </span>
              <span><?php echo date('F j, Y', strtotime($p->created_at ?? 'now')); ?></span>
            </div>
            <p class="blog-card-summary">
              <?php echo htmlspecialchars($p->summary ?? '', ENT_QUOTES, 'UTF-8'); ?>
            </p>
          </div>
          <a href="/post/<?php echo htmlspecialchars($p->slug, ENT_QUOTES, 'UTF-8'); ?>" class="blog-card-link">Read Publication ➔</a>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Dynamic Pagination Controls -->
    <?php echo App::renderPagination($pagination ?? [], '/blog', $_GET); ?>

  <?php else: ?>
    <p style="color: var(--text-muted); font-style: italic;">No publications matching the criteria were found.</p>
  <?php endif; ?>

</div>
