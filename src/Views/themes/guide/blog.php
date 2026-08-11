<?php
// src/Views/themes/guide/blog.php

use Zero\Core\App;
use Zero\Core\Storage\Storage;
use Zero\Core\Template;
use Zero\Support\BlockHelper;
use Zero\Support\Security;
use Zero\Support\Str;

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
      <?php echo Str::escape($post->title ?? ''); ?>
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
    
    // 1. Eager load all referenced media IDs across blocks generically using App::eagerLoadBlockMedia
    $mediaIdMap = [];
    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedBlocks)) {
        $mediaIdMap = App::eagerLoadBlockMedia($decodedBlocks);
    }

    // 2. Ultra-fast media resolver helper (no DB hits!)
    $resolveMedia = function($idOrPath) use ($mediaIdMap) {
        if (empty($idOrPath)) {
            return '';
        }
        $path = strpos($idOrPath, '/') === 0 ? $idOrPath : ($mediaIdMap[$idOrPath] ?? '');
        if (empty($path)) {
            return '';
        }
        return Storage::getUrl($path);
    };

    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedBlocks)): ?>
      <?php foreach ($decodedBlocks as $block): ?>
        <?php 
        $type = $block['type'] ?? '';
        $theme = App::getCurrentSite()->theme ?? 'default';
        
        $themeBlocksDir = App::resolveThemeDir($theme);
        $blockPath = $themeBlocksDir !== null ? $themeBlocksDir . '/blocks/' . $type . '.php' : '';
        if (!file_exists($blockPath)) {
            $registeredBlock = App::getRegisteredBlocks()[$type] ?? [];
            if (!empty($registeredBlock['frontend_view']) && file_exists($registeredBlock['frontend_view'])) {
                $blockPath = $registeredBlock['frontend_view'];
            } else {
                $blockPath = App::resolveThemeFile('default', 'blocks/' . $type . '.php') ?? '';
            }
        }
        if (file_exists($blockPath)) {
            $isBreakout = ($type === 'baseline' && !empty($block['full_width']) && $block['full_width'] === '1');
            $rowClass = BlockHelper::getRowClasses($block, $type, $isBreakout);
            echo '<section class="' . $rowClass . '">';
            echo '  <div class="' . ($isBreakout ? 'block-container-fluid' : 'block-container') . '">';
            
            // If hide_title is not explicitly enabled, render the section title as a block-level H2 or H1
            $hideTitle = $block['hide_title'] ?? '0';
            if ($hideTitle !== '1' && !empty($block['title']) && $type !== 'baseline') {
                $titleTag = $hideTitle === '2' ? 'h1' : 'h2';
                echo '<' . $titleTag . ' class="block-section-title">' . Security::sanitizeHtml($block['title']) . '</' . $titleTag . '>';
            }

            echo Template::renderFile($blockPath, [
                'block' => $block,
                'resolveMedia' => $resolveMedia
            ]);
            echo '  </div>';
            echo '</section>';
        }
        ?>
      <?php endforeach; ?>
    <?php else: ?>
      <?php echo Security::sanitizeHtml($content); ?>
    <?php endif; ?>
  </div>

  <!-- Articles List -->
  <h2 class="blog-section-title">
    Latest Publications
  </h2>

  <?php if (!empty($posts)): ?>
    <div class="blog-cards-list">
      <?php foreach ($posts as $p): ?>
        <div class="blog-card" onclick="window.location.href='/post/<?php echo Str::escape($p->slug); ?>'">
          <div>
            <h3 class="blog-card-title">
              <a href="/post/<?php echo Str::escape($p->slug); ?>" style="color: inherit; text-decoration: none;">
                <?php echo Str::escape($p->title); ?>
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
              <?php echo Str::escape($p->summary ?? ''); ?>
            </p>
          </div>
          <a href="/post/<?php echo Str::escape($p->slug); ?>" class="blog-card-link">Read Publication ➔</a>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Dynamic Pagination Controls -->
    <?php echo App::renderPagination($pagination ?? [], '/blog', $_GET); ?>

  <?php else: ?>
    <p style="color: var(--text-muted); font-style: italic;">No publications matching the criteria were found.</p>
  <?php endif; ?>

</div>
