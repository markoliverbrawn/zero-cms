<?php
// src/Views/components/forms/password_input.php

use Zero\Support\Str;
?>
<?php if ($showLabel): ?><label><?= Str::escape($label) ?></label><?php endif; ?>
<input
    type="password"
    name="<?= Str::escape($name) ?>"
    <?= $required ? 'required' : '' ?>
    <?= $disabled ? 'disabled' : '' ?>
    <?= $attributesHtml ?>
/>
<?php if (!empty($helperText)): ?>
    <small class="field-help-text"><?= Str::escape($helperText) ?></small>
<?php endif; ?>
