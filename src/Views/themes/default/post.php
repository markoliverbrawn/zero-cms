<?php
// src/Views/themes/default/post.php

use Zero\Core\App;
use Zero\Core\Storage\Storage;
use Zero\Core\Template;
use Zero\Database\DB;
use Zero\Modules\Blog\Models\Post;
use Zero\Support\BlockHelper;
use Zero\Support\Security;
use Zero\Support\Str;

$isBlogPost = $post instanceof Post;

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
<article class="post-article">
  <?php if (!$shouldOmitTitle): ?>
    <h1 class="post-title">
      <?php echo Str::escape($post->title ?? ''); ?>
    </h1>
  <?php endif; ?>

  <?php if ($isBlogPost): ?>
    <div class="post-meta">
      <span class="icon-svg">
        <?php echo App::svg('clock'); ?>
      </span>
      <span class="post-date"><?php echo date('F j, Y', strtotime($post->created_at)); ?></span>
    </div>
  <?php endif; ?>

  <?php if (!empty($post->featured_image)): ?>
    <div class="post-featured-image-wrapper" style="margin-bottom: 2rem; border-radius: var(--border-radius, 8px); overflow: hidden; max-height: 400px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid var(--border-color, #e2e8f0);">
      <img src="<?php echo Str::escape($post->featured_image); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover; display: block;" />
    </div>
  <?php endif; ?>

  <div class="post-blocks">
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
        
        // Dynamic Cascading Block View Resolution:
        // 1. Check if the active theme overrides this block: src/Views/themes/{theme}/blocks/{type}.php
        // 2. Check if the block has a registered, module-owned 'frontend_view' path.
        // 3. Graceful legacy fallback.
        $blockPath = APPLICATION_ROOT . '/src/Views/themes/' . $theme . '/blocks/' . $type . '.php';
        if (!file_exists($blockPath)) {
            $registeredBlock = App::getRegisteredBlocks()[$type] ?? [];
            if (!empty($registeredBlock['frontend_view']) && file_exists($registeredBlock['frontend_view'])) {
                $blockPath = $registeredBlock['frontend_view'];
            } else {
                $blockPath = APPLICATION_ROOT . '/src/Views/themes/default/blocks/' . $type . '.php';
            }
        }
        if (file_exists($blockPath)) {
            $rowClass = BlockHelper::getRowClasses($block, $type, false);
            echo '<div class="' . $rowClass . '">';
            
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
            echo '</div>';
        }
        ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php if (!$isBlogPost): ?>
    <div class="post-meta meta-bottom">
      <span class="icon-svg">
        <?php echo App::svg('clock'); ?>
      </span>
      <span class="post-date">Published: <?php echo date('F j, Y', strtotime($post->created_at)); ?></span>
    </div>
  <?php endif; ?>
</article>
