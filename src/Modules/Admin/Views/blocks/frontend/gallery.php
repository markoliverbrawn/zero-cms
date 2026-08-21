<?php
// src/Modules/Admin/Views/blocks/frontend/gallery.php

use Zero\Support\Assets;
use Zero\Support\Str;

$mediaIds = $block['media_ids'] ?? [];

// The grid cells are a fixed 4:3 aspect ratio (see assets/css/blocks/gallery.css), so the
// thumbnail is cropped to match rather than shipping a full-size image the browser then
// downscales. The lightbox is a separate, much larger rendition that is only ever fetched if
// the visitor actually opens one -- which is what data-src is for: gallery.js reads it to
// populate the modal, so src and data-src being the same file (as they used to be) meant every
// visitor paid full-resolution bytes for a thumbnail nobody had clicked yet.
$thumbnailWidth = 600;
$thumbnailHeight = 450;
$lightboxWidth = 1800;
?>
<div class="block block-gallery">
  <div class="gallery-grid">
    <?php if (!empty($mediaIds)): ?>
      <?php foreach ($mediaIds as $img):
        $mediaUrl = $resolveMedia($img);
        if (empty($mediaUrl)) {
            continue;
        }
        $titleText = Assets::title($img);
        $thumbnailSize = Assets::size($mediaUrl, $thumbnailWidth, $thumbnailHeight);
        ?>
        <div class="gallery-item">
          <img src="<?php echo Assets::url($mediaUrl, width: $thumbnailWidth, height: $thumbnailHeight); ?>"
               srcset="<?php echo Str::escape(Assets::srcset($mediaUrl, [400, 600, 900], 4 / 3)); ?>"
               sizes="(max-width: 600px) 100vw, 300px"
               <?php if ($thumbnailSize !== null): ?>width="<?php echo $thumbnailSize['width']; ?>" height="<?php echo $thumbnailSize['height']; ?>"<?php endif; ?>
               class="gallery-img gallery-lightbox-trigger"
               data-src="<?php echo Assets::url($mediaUrl, width: $lightboxWidth, fit: Assets::FIT_CONTAIN); ?>"
               data-title="<?php echo Str::escape($titleText); ?>"
               alt="<?php echo Str::escape($titleText); ?>"
               loading="lazy"
               decoding="async" />
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Beautiful, Zero-Dependency Fullscreen Lightbox Modal Overlay -->
<div id="gallery-lightbox" class="gallery-lightbox">
    <!-- Large Close Button -->
    <button id="gallery-lightbox-close" class="gallery-lightbox-close">&times;</button>

    <!-- Lightbox Image Container -->
    <div id="gallery-lightbox-content" class="gallery-lightbox-content">
        <img id="gallery-lightbox-img" src="" alt="">
        <h4 id="gallery-lightbox-title"></h4>
    </div>
</div>
