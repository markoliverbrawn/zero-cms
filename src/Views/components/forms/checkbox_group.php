<?php
// src/Views/components/forms/checkbox_group.php

use Zero\Support\Str;

$isSequential = (\array_keys($options) === \range(0, \count($options) - 1));
?>
<?php if ($showLabel): ?><label><?= Str::escape($label) ?></label><?php endif; ?>
<div class="checkbox-group">
    <?php foreach ($options as $key => $optionLabel): ?>
        <?php $optionVal = $isSequential ? $optionLabel : $key; ?>
        <label class="checkbox-group-item">
            <input
                type="checkbox"
                name="<?= Str::escape($name) ?>[]"
                value="<?= Str::escape((string)$optionVal) ?>"
                <?= \in_array($optionVal, $selectedVals) ? 'checked' : '' ?>
                <?= $disabled ? 'disabled' : '' ?>
            />
            <?= Str::escape((string)$optionLabel) ?>
        </label>
    <?php endforeach; ?>
</div>
<?php if (!empty($helperText)): ?>
    <small class="field-help-text"><?= Str::escape($helperText) ?></small>
<?php endif; ?>
