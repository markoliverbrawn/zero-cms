<?php
// src/Views/themes/kitchensink/blocks.php

use Zero\Core\App;
use Zero\Core\Storage\Storage;
use Zero\Core\Template;
use Zero\Database\DB;
use Zero\Support\BlockHelper;
use Zero\Support\Security;
use Zero\Support\Str;

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
  <div class="post-blocks" style="display: flex; flex-direction: column; gap: 3rem;">
    <?php foreach ($decodedBlocks as $block): ?>
      <?php 
      $blockType = $block['type'] ?? '';
      $rowClass = BlockHelper::getRowClasses($block, $blockType, false);
      echo '<div class="' . $rowClass . '">';
      
      $hideTitle = $block['hide_title'] ?? '0';
      $renderKitchenSinkTitle = function($title, $colorVar = '--neon-cyan') use ($hideTitle) {
          if ($hideTitle === '1' || empty($title)) {
              return '';
          }
          $tag = $hideTitle === '2' ? 'h1' : 'h3';
          return '<' . $tag . ' style="color: var(' . $colorVar . '); margin-bottom: 1.25rem;">' . Security::sanitizeHtml($title) . '</' . $tag . '>';
      };

      switch ($blockType) {
          case 'baseline':
              echo '<div class="block-baseline">';
              echo '<h1>' . Security::sanitizeHtml($block['title'] ?? '') . '</h1>';
              echo '<p>' . Security::sanitizeHtml($block['content'] ?? '') . '</p>';
              echo '</div>';
              break;
          case 'text':
              echo '<div class="block-text">';
              echo $renderKitchenSinkTitle($block['title'] ?? '', '--neon-cyan');
              echo '<div>' . Security::sanitizeHtml($block['content'] ?? '') . '</div>';
              echo '</div>';
              break;
          case 'text_image':
              $img = $block['image_path'] ?? '';
              $pos = $block['image_position'] ?? 'right';
              $rowClass = $pos === 'left' ? 'style="display: flex; flex-wrap: wrap; gap: 2.5rem; flex-direction: row-reverse;"' : 'style="display: flex; flex-wrap: wrap; gap: 2.5rem;"';
              echo '<div class="block-text-image" ' . $rowClass . '>';
              echo '<div class="block-text-col" style="flex: 1 1 50%; min-width: 280px;">';
              echo $renderKitchenSinkTitle($block['title'] ?? '', '--neon-pink');
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
              echo $renderKitchenSinkTitle($block['title'] ?? '', '--neon-cyan');
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
              echo $renderKitchenSinkTitle($block['title'] ?? '', '--neon-pink');
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
              echo $renderKitchenSinkTitle($block['title'] ?? '', '--neon-pink');
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
                  echo $renderKitchenSinkTitle($block['title'] ?? '', '--neon-cyan');
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
  </div>
<?php endif; ?>
