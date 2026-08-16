<?php
// src/Views/components/forms/image_picker_field.php

use Zero\Support\Str;
?>
<?php if ($showLabel): ?><label><?= Str::escape($label) ?></label><?php endif; ?>
<div class="image-picker-container" data-field="<?= Str::escape($name) ?>">
    <div class="image-picker-row">
        <input class="image-picker-input" name="<?= Str::escape($name) ?>" value="<?= Str::escape((string)$mediaId) ?>" <?= $required ? 'required' : '' ?> />
        <button type="button" class="btn-luxe-outline media-picker-trigger-btn">Choose Image</button>
    </div>
    <div class="image-picker-preview-box <?= !empty($mediaPath) ? 'has-preview' : '' ?>">
        <img class="image-picker-preview" src="<?= Str::escape((string)$mediaPath) ?>" />
    </div>
</div>
<?php if (!empty($helperText)): ?>
    <small class="field-help-text"><?= Str::escape($helperText) ?></small>
<?php endif; ?>
