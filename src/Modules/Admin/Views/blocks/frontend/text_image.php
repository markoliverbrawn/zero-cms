<?php
// src/Modules/Admin/Views/blocks/frontend/text_image.php

use Zero\Support\Assets;
use Zero\Support\Security;
use Zero\Support\Str;

$imagePos = $block['image_position'] ?? 'right';
$mediaId = $block['media_id'] ?? '';

// Scaled, not cropped: this block's image column has no fixed aspect ratio, so cropping to one
// would arbitrarily trim whatever the editor uploaded.
$imageWidth = 900;
?>
<div class="block block-text-image <?php echo $imagePos === 'left' ? 'image-left' : ''; ?>">
  <div class="block-text-col">
    <div class="block-content">
      <?php echo Security::sanitizeHtml($block['content'] ?? ''); ?>
    </div>
  </div>
  <div class="block-image-col">
    <?php if (!empty($mediaId)): ?>
      <?php $mediaUrl = $resolveMedia($mediaId); ?>
      <img src="<?php echo Assets::url($mediaUrl, width: $imageWidth, fit: Assets::FIT_CONTAIN); ?>"
           srcset="<?php echo Str::escape(Assets::srcset($mediaUrl, [450, 700, 900])); ?>"
           sizes="(max-width: 700px) 100vw, 50vw"
           alt="<?php echo Str::escape($block['title'] ?? ''); ?>"
           loading="lazy"
           decoding="async" />
    <?php endif; ?>
  </div>
</div>
