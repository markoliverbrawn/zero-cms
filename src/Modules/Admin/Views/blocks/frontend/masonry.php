<?php
// src/Modules/Admin/Views/blocks/frontend/masonry.php

?>
<div class="block-masonry-wrapper">
  <?php if (!empty($block['title'])): ?>
    <h3><?php echo htmlspecialchars($block['title'], ENT_QUOTES, "UTF-8"); ?></h3>
  <?php endif; ?>

  <div class="block-masonry">
    <?php if (!empty($block['items'])): ?>
      <?php foreach ($block['items'] as $item): ?>
        <div class="masonry-item">
          <?php if (!empty($item['media_id'])): ?>
            <img src="<?php echo htmlspecialchars($resolveMedia($item['media_id']), ENT_QUOTES, 'UTF-8'); ?>?v=1.2" class="masonry-trigger-img" />
          <?php endif; ?>
          <h4><?php echo htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h4>
          <p><?php echo htmlspecialchars($item['desc'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
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
