<?php
// src/Views/themes/default/blog.php

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
            if (($b['type'] ?? '') === 'hero') {
                $hasHeroBlock = true;
                break;
            }
        }
    }
}
$shouldOmitTitle = !empty($post->omit_title) || $hasHeroBlock;
?>
<article>
  <!-- Blog Parent Page Title & Body -->
  <?php if (!$shouldOmitTitle): ?>
    <h1 style="margin-top: 0; margin-bottom: 10px; font-size: 2.2rem; font-weight: 800; line-height: 1.2; color: #0f172a; letter-spacing: -0.02em;">
      <?php echo Str::escape($post->title ?? ''); ?>
    </h1>
  <?php endif; ?>
  
  <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 30px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 6px; font-weight: 500;">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle;">
      <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
      <line x1="16" y1="2" x2="16" y2="6"></line>
      <line x1="8" y1="2" x2="8" y2="6"></line>
      <line x1="3" y1="10" x2="21" y2="10"></line>
    </svg>
    <span style="vertical-align: middle; margin-left: 2px;">Tech Publications &amp; Insights</span>
  </div>

  <!-- Parent Page Intro Content Blocks -->
  <div class="blog-blocks" style="margin-bottom: 40px; display: flex; flex-direction: column; gap: 3rem;">
    <?php
    $content = $post->content ?? '';
    $decodedBlocks = json_decode($content, true);
    
    // 1. Eager load all media assets referenced across all blocks (resolves both media_id and media_ids recursively)
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
            $rowClass = BlockHelper::getRowClasses($block, $type, false);
            echo '<div class="' . $rowClass . '">';
            
            // If hide_title is not explicitly enabled, render the section title as a block-level H2 or H1
            $hideTitle = $block['hide_title'] ?? '0';
            if ($hideTitle !== '1' && !empty($block['title']) && $type !== 'hero') {
                $titleTag = $hideTitle === '2' ? 'h1' : 'h2';
                echo '<' . $titleTag . ' class="block-section-title">' . Security::sanitizeHtml($block['title']) . '</' . $titleTag . '>';
            }

            echo Template::renderFile($blockPath, [
                'block' => $block,
                'resolveMedia' => $resolveMedia
            ]);
            echo '</div>';
        }
        ?>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="color: #334155; font-size: 1.05rem; line-height: 1.6;">
        <?php echo Security::sanitizeHtml($content); ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Articles List -->
  <h2 style="font-size: 1.4rem; font-weight: 700; margin-bottom: 25px; border-bottom: 2px solid #cbd5e1; padding-bottom: 8px;">
    Latest Publications
  </h2>

  <?php if (!empty($posts)): ?>
    <div style="display: flex; flex-direction: column; gap: 25px;">
      <?php foreach ($posts as $p): ?>
        <div class="blog-card" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; background: #ffffff; box-shadow: 0 4px 6px rgba(0,0,0,0.01); display: flex; flex-direction: row; gap: 25px; transition: transform 0.15s ease, border-color 0.15s ease; cursor: pointer;" onclick="window.location.href='/post/<?php echo Str::escape($p->slug); ?>'">
          <?php if (!empty($p->featured_image)): ?>
            <div style="width: 150px; height: 150px; flex-shrink: 0; border-radius: 6px; overflow: hidden; background-color: #f1f5f9; display: flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0;">
              <img src="<?php echo Str::escape($p->featured_image); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;" />
            </div>
          <?php endif; ?>
          <div style="display: flex; flex-direction: column; justify-content: space-between; flex-grow: 1;">
            <div>
              <h3 style="margin-top: 0; margin-bottom: 8px; font-size: 1.25rem; font-weight: 800;">
                <a href="/post/<?php echo Str::escape($p->slug); ?>" style="color: #2563eb; text-decoration: none;">
                  <?php echo Str::escape($p->title); ?>
                </a>
              </h3>
              <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; font-weight: 500;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle;">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                  <line x1="16" y1="2" x2="16" y2="6"></line>
                  <line x1="8" y1="2" x2="8" y2="6"></line>
                  <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span style="vertical-align: middle; margin-left: 2px;"><?php echo date('F j, Y', strtotime($p->created_at)); ?></span>
              </div>
              <p style="margin: 0; font-size: 0.95rem; color: #334155; line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                <?php echo Str::escape($p->summary ?? ''); ?>
              </p>
            </div>
            <a href="/post/<?php echo Str::escape($p->slug); ?>" style="display: inline-block; margin-top: 15px; font-size: 0.9rem; font-weight: bold; color: #2563eb; text-decoration: none;">Read Publication ➔</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination Controls -->
    <?php echo App::renderPagination($pagination ?? [], '/blog', $_GET); ?>
    
    <style>
      .blog-card {
        transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275), border-color 0.2s ease, box-shadow 0.2s ease !important;
      }
      .blog-card:hover {
        transform: translateY(-3px) !important;
        box-shadow: 0 8px 20px rgba(0,0,0,0.04) !important;
        border-color: #2563eb !important;
      }
    </style>
  <?php else: ?>
    <p style="color: #64748b; font-style: italic;">No publications matching the criteria were found.</p>
  <?php endif; ?>

</article>
