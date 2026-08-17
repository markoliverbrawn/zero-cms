<?php
// src/Views/components/forms/gallery_picker_field.php

use Zero\Support\Str;
?>
<?php if ($showLabel): ?><label><?= Str::escape($label) ?></label><?php endif; ?>
<div class="gallery-picker-wrapper">
    <div class="gallery-picker-controls">
        <input type="hidden" id="product-media-ids-input" name="<?= Str::escape($name) ?>" value="<?= Str::escape($value) ?>" />
        <button type="button" id="product-gallery-picker-btn" class="btn-luxe-outline">Choose Gallery Images</button>
    </div>
    <div id="product-gallery-preview-grid" class="gallery-thumbnails-grid">
        <?php foreach ($images as $img): ?>
            <div class="gallery-thumb-card" data-id="<?= Str::escape($img['id']) ?>">
                <img src="<?= Str::escape($img['path']) ?>" />
                <button type="button" class="gallery-thumb-remove-btn" title="Remove image">&times;</button>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php if (!empty($helperText)): ?>
    <small class="field-help-text"><?= Str::escape($helperText) ?></small>
<?php endif; ?>
