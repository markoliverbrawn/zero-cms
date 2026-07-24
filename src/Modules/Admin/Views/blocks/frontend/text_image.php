<?php
// src/Modules/Admin/Views/blocks/frontend/text_image.php

use Zero\Support\Security;
use Zero\Support\Str;

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
      <img src="<?php echo Str::escape($resolveMedia($mediaId)); ?>?v=1.2" alt="<?php echo Str::escape($block['title'] ?? ''); ?>" />
    <?php endif; ?>
  </div>
</div>
