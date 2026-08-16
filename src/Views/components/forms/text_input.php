<?php
// src/Views/components/forms/text_input.php
// Shared template for TextInput, EmailInput, and PasswordInput (they differ only in $inputType).

use Zero\Support\Str;
?>
<?php if ($showLabel): ?><label><?= Str::escape($label) ?></label><?php endif; ?>
<input
    type="<?= Str::escape($inputType) ?>"
    name="<?= Str::escape($name) ?>"
    value="<?= Str::escape((string)($value ?? '')) ?>"
    <?= $required ? 'required' : '' ?>
    <?= $disabled ? 'disabled' : '' ?>
    <?= $attributesHtml ?>
/>
<?php if (!empty($helperText)): ?>
    <small class="field-help-text"><?= Str::escape($helperText) ?></small>
<?php endif; ?>
