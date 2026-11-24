<?php
// src/Views/components/forms/datetime_input.php

use Zero\Support\Str;
?>
<?php if ($showLabel): ?><label><?= Str::escape($label) ?></label><?php endif; ?>
<input
    type="datetime-local"
    name="<?= Str::escape($name) ?>"
    value="<?= Str::escape((string)($value ?? '')) ?>"
    <?= $required ? 'required' : '' ?>
    <?= $disabled ? 'disabled' : '' ?>
    <?= $attributesHtml ?><?php // security-scan-ignore: unescaped-output -- renderAttributes() escapes every attribute name and value ?>
/>
<?php if (!empty($helperText)): ?>
    <small class="field-help-text"><?= Str::escape($helperText) ?></small>
<?php endif; ?>
