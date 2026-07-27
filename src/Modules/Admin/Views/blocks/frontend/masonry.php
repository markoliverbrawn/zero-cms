<?php
use Zero\Support\Str;
// src/Modules/Admin/Views/blocks/frontend/masonry.php

?>
<div class="block-masonry-wrapper">

  <div class="block-masonry">
    <?php if (!empty($block['items'])): ?>
      <?php foreach ($block['items'] as $item): ?>
        <div class="masonry-item">
          <?php if (!empty($item['media_id'])): ?>
            <img src="<?php echo Str::escape($resolveMedia($item['media_id'])); ?>?v=1.2" class="masonry-trigger-img" />
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
