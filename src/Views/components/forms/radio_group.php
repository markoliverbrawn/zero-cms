<?php
// src/Views/components/forms/radio_group.php

use Zero\Support\Str;

$isSequential = (\array_keys($options) === \range(0, \count($options) - 1));
$index = 0;
?>
<?php if ($showLabel): ?><label><?= Str::escape($label) ?></label><?php endif; ?>
<div class="radio-group">
    <?php foreach ($options as $key => $optionLabel): ?>
        <?php $optionVal = $isSequential ? $optionLabel : $key; ?>
        <label class="radio-group-item">
            <input
                type="radio"
                name="<?= Str::escape($name) ?>"
                value="<?= Str::escape((string)$optionVal) ?>"
                <?= ((string)$optionVal === (string)$selectedVal) ? 'checked' : '' ?>
                <?= ($index === 0 && $required) ? 'required' : '' ?>
                <?= $disabled ? 'disabled' : '' ?>
            />
            <?= Str::escape((string)$optionLabel) ?>
        </label>
        <?php $index++; ?>
    <?php endforeach; ?>
</div>
<?php if (!empty($helperText)): ?>
    <small class="field-help-text"><?= Str::escape($helperText) ?></small>
<?php endif; ?>
