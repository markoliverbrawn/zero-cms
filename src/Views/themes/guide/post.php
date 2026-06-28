<?php
// src/Views/themes/guide/post.php

use Zero\Core\App;
use Zero\Core\Storage\Storage;
use Zero\Core\Template;
use Zero\Database\DB;
use Zero\Modules\Blog\Models\Post;
use Zero\Support\BlockHelper;

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
<div class="article-container">
  <?php if (!$shouldOmitTitle): ?>
    <h1 class="article-title">
      <?php echo htmlspecialchars($post->title ?? '', ENT_QUOTES, "UTF-8"); ?>
    </h1>
  <?php endif; ?>

  <?php if ($isBlogPost): ?>
    <div class="pub-date-badge">
      <span class="material-symbols-outlined" style="font-size: 14px;">schedule</span>
      <span>Published: <?php echo date('F j, Y', strtotime($post->created_at)); ?></span>
    </div>
  <?php endif; ?>

  <?php if (!empty($post->featured_image)): ?>
    <div class="featured-image-wrapper">
      <img src="<?php echo htmlspecialchars($post->featured_image); ?>" alt="" />
    </div>
  <?php endif; ?>

  <div class="article-content">
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
            $isBreakout = ($type === 'baseline' && !empty($block['full_width']) && $block['full_width'] === '1');
            $rowClass = BlockHelper::getRowClasses($block, $type, $isBreakout);
            echo '<section class="' . $rowClass . '">';
            echo '  <div class="' . ($isBreakout ? 'block-container-fluid' : 'block-container') . '">';
            echo Template::renderFile($blockPath, [
                'block' => $block,
                'resolveMedia' => $resolveMedia
            ]);
            echo '  </div>';
            echo '</section>';
        }
        ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php if (!$isBlogPost && !empty($post->slug)): ?>
    <div class="footer-date-tag">
      <span class="material-symbols-outlined" style="font-size: 14px;">schedule</span>
      <span>Published: <?php echo date('F j, Y', strtotime($post->created_at)); ?></span>
    </div>
  <?php endif; ?>
</div>
