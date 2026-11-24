<?php
// src/Views/components/forms/checkbox.php

use Zero\Support\Str;
?>
<label class="settings-checkbox-label">
    <input
        type="checkbox"
        name="<?= Str::escape($name) ?>"
        value="1"
        <?= $checked ? 'checked' : '' ?>
        <?= $disabled ? 'disabled' : '' ?>
        <?= $attributesHtml ?><?php // security-scan-ignore: unescaped-output -- renderAttributes() escapes every attribute name and value ?>
    />
    <?= Str::escape($label) ?>
</label>
<?php if (!empty($helperText)): ?>
    <small class="field-help-text"><?= Str::escape($helperText) ?></small>
<?php endif; ?>
