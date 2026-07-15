<?php
// src/Modules/Admin/Views/blocks/frontend/gallery.php

use Zero\Models\Media;

$mediaIds = $block['media_ids'] ?? [];
?>
<div class="block block-gallery">
  <div class="gallery-grid">
    <?php if (!empty($mediaIds)): ?>
      <?php foreach ($mediaIds as $img): 
        $mediaUrl = $resolveMedia($img);
        $mediaRec = Media::find($img);
        $titleText = $mediaRec ? ($mediaRec->title ?: $mediaRec->filename) : '';
        ?>
        <div class="gallery-item">
          <img src="<?php echo htmlspecialchars($mediaUrl, ENT_QUOTES, "UTF-8"); ?>" class="gallery-img gallery-lightbox-trigger" data-src="<?php echo htmlspecialchars($mediaUrl, ENT_QUOTES, "UTF-8"); ?>" data-title="<?php echo htmlspecialchars($titleText, ENT_QUOTES, "UTF-8"); ?>" />
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
        <img id="gallery-lightbox-img" src="">
        <h4 id="gallery-lightbox-title"></h4>
    </div>
</div>
