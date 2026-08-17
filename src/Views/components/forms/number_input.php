<?php
// src/Views/components/forms/number_input.php

use Zero\Support\Str;
?>
<?php if ($showLabel): ?><label><?= Str::escape($label) ?></label><?php endif; ?>
<input
    type="number"
    step="<?= Str::escape($step) ?>"
    name="<?= Str::escape($name) ?>"
    value="<?= Str::escape((string)($value ?? '')) ?>"
    <?= $min !== null ? 'min="' . Str::escape((string)$min) . '"' : '' ?>
    <?= $max !== null ? 'max="' . Str::escape((string)$max) . '"' : '' ?>
    <?= $required ? 'required' : '' ?>
    <?= $disabled ? 'disabled' : '' ?>
    <?= $attributesHtml ?>
/>
<?php if (!empty($helperText)): ?>
    <small class="field-help-text"><?= Str::escape($helperText) ?></small>
<?php endif; ?>
