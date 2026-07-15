<?php
// src/Modules/Admin/Views/blocks/frontend/text_image.php

use Zero\Support\Security;

$imagePos = $block['image_position'] ?? 'right';
$mediaId = $block['media_id'] ?? '';
?>
<div class="block block-text-image <?php echo $imagePos === 'left' ? 'image-left' : ''; ?>">
  <div class="block-text-col">
    <div class="block-content">
      <?php echo Security::sanitizeHtml($block['content'] ?? ''); ?>
    </div>
  </div>
  <div class="block-image-col">
    <?php if (!empty($mediaId)): ?>
      <img src="<?php echo htmlspecialchars($resolveMedia($mediaId), ENT_QUOTES, "UTF-8"); ?>?v=1.2" alt="<?php echo htmlspecialchars($block['title'] ?? '', ENT_QUOTES, "UTF-8"); ?>" />
    <?php endif; ?>
  </div>
</div>
