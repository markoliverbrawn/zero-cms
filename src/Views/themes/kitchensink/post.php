<?php
// src/Views/themes/kitchensink/post.php

use Zero\Core\App;
use Zero\Core\Storage\Storage;
use Zero\Core\Template;
use Zero\Database\DB;
use Zero\Models\Media;
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
<article class="post-detail-container" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 3rem; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
  <?php if (!$shouldOmitTitle): ?>
    <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem; background: linear-gradient(90deg, var(--text-color), var(--neon-cyan)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
      <?php echo Str::escape($post->title ?? ''); ?>
    </h1>
  <?php endif; ?>
  
  <?php if ($isBlogPost): ?>
    <div class="post-meta" style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--neon-pink); margin-bottom: 2rem;">
      Published: <?php echo date('F d, Y', strtotime($post->created_at)); ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($post->featured_image)): ?>
    <div class="post-featured-image-wrapper" style="margin-bottom: 2.5rem; border-radius: var(--border-radius); overflow: hidden; max-height: 400px; border: 1px solid var(--border-color); box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
      <img src="<?php echo Str::escape($post->featured_image); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover; display: block;" />
    </div>
  <?php endif; ?>

  <div class="post-blocks" style="display: flex; flex-direction: column; gap: 3rem;">
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
            } elseif ($type === 'gallery' && !empty($block['media_ids'])) {
                // Support 'media_ids' fallback key for Showcase Grid Galleries seeding compatibility!
                foreach ($block['media_ids'] as $imgId) {
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
    
    // 2. Fetch all media records (including path, title, and filename) in a single SQL query
    $mediaIdMap = [];
    if (!empty($mediaIds)) {
        $filteredIds = array_filter(array_unique($mediaIds), function($id) {
            return !empty($id) && strpos($id, '/') !== 0;
        });
        
        if (!empty($filteredIds)) {
            $placeholders = implode(',', array_fill(0, count($filteredIds), '?'));
            $sql = "SELECT id, path, title, filename FROM media WHERE id IN ($placeholders) AND deleted_at IS NULL";
            $stmt = DB::query($sql, array_values($filteredIds));
            while ($row = $stmt->fetch()) {
                $mediaIdMap[$row['id']] = [
                    'path' => $row['path'],
                    'title' => $row['title'] ?: $row['filename']
                ];
            }
        }
    }

    // 3. Ultra-fast media resolver helper (no DB hits!)
    $resolveMedia = function($idOrPath) use ($mediaIdMap) {
        if (empty($idOrPath)) {
            return '';
        }
        if (strpos($idOrPath, '/') === 0) {
            return Storage::getUrl($idOrPath);
        }
        $path = $mediaIdMap[$idOrPath]['path'] ?? '';
        if (empty($path)) {
            return '';
        }
        return Storage::getUrl($path);
    };

    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedBlocks)): ?>
      <?php foreach ($decodedBlocks as $block): ?>
        <?php 
        $blockType = $block['type'] ?? '';
        $rowClass = BlockHelper::getRowClasses($block, $blockType, false);
        echo '<div class="' . $rowClass . '">';
        
        switch ($blockType) {
            case 'baseline':
                echo '<div class="block-baseline">';
                echo '<h1>' . Security::sanitizeHtml($block['title'] ?? '') . '</h1>';
                echo '<p>' . Security::sanitizeHtml($block['content'] ?? '') . '</p>';
                echo '</div>';
                break;
            case 'text':
                echo '<div class="block-text">';
                if (!empty($block['title'])) {
                    echo '<h3 style="color: var(--neon-cyan); margin-bottom: 1.25rem;">' . Security::sanitizeHtml($block['title']) . '</h3>';
                }
                echo '<div>' . Security::sanitizeHtml($block['content'] ?? '') . '</div>';
                echo '</div>';
                break;
            case 'text_image':
                $img = $block['image_path'] ?? '';
                $pos = $block['image_position'] ?? 'right';
                $rowClass = $pos === 'left' ? 'style="display: flex; flex-wrap: wrap; gap: 2.5rem; flex-direction: row-reverse;"' : 'style="display: flex; flex-wrap: wrap; gap: 2.5rem;"';
                echo '<div class="block-text-image" ' . $rowClass . '>';
                echo '<div class="block-text-col" style="flex: 1 1 50%; min-width: 280px;">';
                if (!empty($block['title'])) {
                    echo '<h3 style="color: var(--neon-pink); margin-bottom: 1.25rem;">' . Security::sanitizeHtml($block['title']) . '</h3>';
                }
                echo '<div>' . Security::sanitizeHtml($block['content'] ?? '') . '</div>';
                echo '</div>';
                echo '<div class="block-image-col" style="flex: 1 1 35%; min-width: 250px; border-radius: var(--border-radius); overflow: hidden; border: 1px solid var(--border-color);">';
                if (!empty($img)) {
                    echo '<img src="' . Str::escape($resolveMedia($img)) . '" style="width: 100%; height: 100%; object-fit: cover; display: block;" alt="" />';
                }
                echo '</div>';
                echo '</div>';
                break;
            case 'accordion':
                echo '<div class="block-accordion">';
                if (!empty($block['title'])) {
                    echo '<h3 style="color: var(--neon-cyan); margin-bottom: 1.25rem;">' . Security::sanitizeHtml($block['title']) . '</h3>';
                }
                if (!empty($block['items'])) {
                    foreach ($block['items'] as $item) {
                        echo '<div class="accordion-item">';
                        echo '<button class="accordion-trigger">';
                        echo '<span class="accordion-title">' . Str::escape($item['title'] ?? '') . '</span>';
                        echo '</button>';
                        echo '<div class="accordion-content">' . Security::sanitizeHtml($item['content'] ?? '') . '</div>';
                        echo '</div>';
                    }
                }
                echo '</div>';
                break;
            case 'testimonials':
                echo '<div class="block-testimonials">';
                if (!empty($block['title'])) {
                    echo '<h3 style="color: var(--neon-pink); margin-bottom: 1.25rem;">' . Security::sanitizeHtml($block['title']) . '</h3>';
                }
                echo '<div class="testimonials-carousel-container">';
                echo '<div class="testimonials-slides-wrapper">';
                if (!empty($block['items'])) {
                    foreach ($block['items'] as $item) {
                        echo '<div class="testimonial-slide">';
                        echo '<div class="testimonial-quote">“' . Security::sanitizeHtml($item['content'] ?? '') . '”</div>';
                        echo '<div class="testimonial-author">— ' . Str::escape($item['person'] ?? '') . '</div>';
                        echo '</div>';
                    }
                }
                echo '</div>';
                echo '</div>';
                echo '</div>';
                break;
            case 'gallery':
                echo '<div class="block-gallery">';
                if (!empty($block['title'])) {
                    echo '<h3 style="color: var(--neon-pink); margin-bottom: 1.25rem;">' . Security::sanitizeHtml($block['title']) . '</h3>';
                }
                // Support both 'images' and 'media_ids' keys cleanly
                $galleryImages = $block['images'] ?? ($block['media_ids'] ?? []);
                if (!empty($galleryImages)) {
                    echo '<div class="gallery-grid">';
                    foreach ($galleryImages as $imgId) {
                        $mediaUrl = $resolveMedia($imgId);
                        // Access the pre-fetched title fully in-memory with 0 database queries!
                        $titleText = $mediaIdMap[$imgId]['title'] ?? '';
                        
                        echo '<div class="gallery-item">';
                        echo '<img src="' . Str::escape($mediaUrl) . '" class="gallery-lightbox-trigger" data-src="' . Str::escape($mediaUrl) . '" data-title="' . Str::escape($titleText) . '" style="cursor: pointer; transition: transform 0.2s ease;" alt="" />';
                        echo '</div>';
                    }
                    echo '</div>';
                }
                echo '</div>';
                ?>
                <!-- Beautiful, Zero-Dependency Fullscreen Lightbox Modal Overlay -->
                <div id="gallery-lightbox" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: rgba(5, 5, 5, 0.95); backdrop-filter: blur(10px); z-index: 99999999; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease;">
                    <button id="gallery-lightbox-close" style="position: absolute; top: 30px; right: 40px; background: none; border: none; color: #ffffff; font-size: 2.5rem; font-family: monospace; cursor: pointer; opacity: 0.7; transition: opacity 0.2s, transform 0.2s; outline: none;">&times;</button>
                    <div style="max-width: 90%; max-height: 85%; display: flex; flex-direction: column; align-items: center; justify-content: center; transform: scale(0.9); transition: transform 0.3s ease;" id="gallery-lightbox-content">
                        <img id="gallery-lightbox-img" src="" style="width: auto; height: auto; max-width: 90vw; max-height: 70vh; border: 1px solid rgba(255,255,255,0.1); border-radius: 4px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); object-fit: contain;">
                        <h4 id="gallery-lightbox-title" style="color: #ffffff; margin-top: 20px; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase; font-family: monospace; font-size: 0.9rem;"></h4>
                    </div>
                </div>
                <?php
                break;
            default:
                // Dynamic Cascading Block View Resolution for custom/modular blocks
                $theme = App::getCurrentSite()->theme ?? 'default';
                $blockPath = APPLICATION_ROOT . '/src/Views/themes/' . $theme . '/blocks/' . $blockType . '.php';
                if (!file_exists($blockPath)) {
                    $registeredBlock = App::getRegisteredBlocks()[$blockType] ?? [];
                    if (!empty($registeredBlock['frontend_view']) && file_exists($registeredBlock['frontend_view'])) {
                        $blockPath = $registeredBlock['frontend_view'];
                    } else {
                        $blockPath = APPLICATION_ROOT . '/src/Views/themes/default/blocks/' . $blockType . '.php';
                    }
                }
                if (file_exists($blockPath)) {
                    $hideTitle = $block['hide_title'] ?? '0';
                    if ($hideTitle !== '1' && !empty($block['title'])) {
                        echo '<h3 style="color: var(--neon-cyan); margin-bottom: 1.25rem;">' . Security::sanitizeHtml($block['title']) . '</h3>';
                    }
                    echo Template::renderFile($blockPath, [
                        'block' => $block,
                        'resolveMedia' => $resolveMedia
                    ]);
                }
                break;
        }
        echo '</div>';
        ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php if (!$isBlogPost): ?>
    <div class="post-meta meta-bottom" style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--neon-pink); margin-top: 3rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); margin-bottom: 0;">
      Published: <?php echo date('F d, Y', strtotime($post->created_at)); ?>
    </div>
  <?php endif; ?>
</article>
