<?php
// src/Modules/Admin/Views/blocks/frontend/masonry.php

use Zero\Support\Assets;
use Zero\Support\Str;

// Masonry tiles are a single column wide and keep their natural aspect ratio, so these are
// scaled rather than cropped. The lightbox rendition is fetched only on click.
$tileWidth = 800;
$lightboxWidth = 1800;
?>
<div class="block-masonry-wrapper">

  <div class="block-masonry">
    <?php if (!empty($block['items'])): ?>
      <?php foreach ($block['items'] as $item): ?>
        <div class="masonry-item">
          <?php if (!empty($item['media_id'])): ?>
            <?php $mediaUrl = $resolveMedia($item['media_id']); ?>
            <img src="<?php echo Assets::url($mediaUrl, width: $tileWidth, fit: Assets::FIT_CONTAIN); ?>"
                 srcset="<?php echo Str::escape(Assets::srcset($mediaUrl, [400, 600, 800])); ?>"
                 sizes="(max-width: 700px) 100vw, 33vw"
                 class="masonry-trigger-img"
                 data-src="<?php echo Assets::url($mediaUrl, width: $lightboxWidth, fit: Assets::FIT_CONTAIN); ?>"
                 alt="<?php echo Str::escape($item['title'] ?? ''); ?>"
                 loading="lazy"
                 decoding="async" />
          <?php endif; ?>
          <h4><?php echo Str::escape($item['title'] ?? ''); ?></h4>
          <p><?php echo Str::escape($item['desc'] ?? ''); ?></p>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Beautiful, Zero-Dependency Fullscreen Lightbox Modal Overlay -->
<div id="masonry-lightbox" class="masonry-lightbox">
    <!-- Close button -->
    <button id="masonry-lightbox-close" class="masonry-lightbox-close">&times;</button>
    
    <!-- Lightbox Image Container -->
    <div id="masonry-lightbox-content" class="masonry-lightbox-content">
        <img id="masonry-lightbox-img" src="">
        <h4 id="masonry-lightbox-title"></h4>
    </div>
</div>
