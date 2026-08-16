<?php
// src/Views/components/forms/textarea.php

use Zero\Support\Str;
?>
<?php if ($showLabel): ?><label><?= Str::escape($label) ?></label><?php endif; ?>
<textarea
    name="<?= Str::escape($name) ?>"
    <?= $required ? 'required' : '' ?>
    <?= $disabled ? 'disabled' : '' ?>
    <?= $attributesHtml ?>
><?= Str::escape((string)($value ?? '')) ?></textarea>
<?php if (!empty($helperText)): ?>
    <small class="field-help-text"><?= Str::escape($helperText) ?></small>
<?php endif; ?>
