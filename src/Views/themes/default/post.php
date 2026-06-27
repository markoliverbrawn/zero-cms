<?php
// src/Views/themes/default/post.php

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
<article class="post-article">
  <?php if (!$shouldOmitTitle): ?>
    <h1 class="post-title">
      <?php echo htmlspecialchars($post->title ?? '', ENT_QUOTES, "UTF-8"); ?>
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
      <img src="<?php echo htmlspecialchars($post->featured_image); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover; display: block;" />
    </div>
  <?php endif; ?>

  <div class="post-blocks">
    <?php
    $content = $post->content ?? '';
    $decodedBlocks = json_decode($content, true);
    
    // 1. Collect all media IDs referenced across all blocks
    $mediaIds = [];
    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedBlocks)) {
        foreach ($decodedBlocks as $block) {
            $type = $block['type'] ?? '';
            if ($type === 'text_image' && !empty($block['image_path'])) {
                $mediaIds[] = $block['image_path'];
            } elseif ($type === 'gallery' && !empty($block['images'])) {
                foreach ($block['images'] as $imgId) {
                    $mediaIds[] = $imgId;
                }
            } elseif ($type === 'masonry' && !empty($block['items'])) {
                foreach ($block['items'] as $item) {
                    if (!empty($item['image_path'])) {
                        $mediaIds[] = $item['image_path'];
                    }
                }
            }
        }
    }
    
    // 2. Fetch all media records in a single database query
    $mediaIdMap = [];
    if (!empty($mediaIds)) {
        $filteredIds = array_filter(array_unique($mediaIds), function($id) {
            return !empty($id) && strpos($id, '/') !== 0;
        });
        
        if (!empty($filteredIds)) {
            $placeholders = implode(',', array_fill(0, count($filteredIds), '?'));
            $sql = "SELECT id, path FROM media WHERE id IN ($placeholders) AND deleted_at IS NULL";
            $stmt = DB::query($sql, array_values($filteredIds));
            while ($row = $stmt->fetch()) {
                $mediaIdMap[$row['id']] = $row['path'];
            }
        }
    }

    // 3. Ultra-fast media resolver helper (no DB hits!)
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
