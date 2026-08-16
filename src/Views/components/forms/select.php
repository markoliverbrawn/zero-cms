<?php
// src/Views/components/forms/select.php

use Zero\Support\Str;

$isSequential = (\array_keys($options) === \range(0, \count($options) - 1));
?>
<?php if ($showLabel): ?><label><?= Str::escape($label) ?></label><?php endif; ?>
<select
    name="<?= Str::escape($name) ?><?= $isMultiple ? '[]' : '' ?>"
    class="<?= Str::escape($classAttr) ?>"
    <?= $isMultiple ? 'multiple' : '' ?>
    <?= $required ? 'required' : '' ?>
    <?= $disabled ? 'disabled' : '' ?>
    <?= $attributesHtml ?>
>
    <?php foreach ($options as $key => $optionLabel): ?>
        <?php $optionVal = $isSequential ? $optionLabel : $key; ?>
        <option value="<?= Str::escape((string)$optionVal) ?>" <?= \in_array($optionVal, $selectedVals) ? 'selected' : '' ?>>
            <?= Str::escape((string)$optionLabel) ?>
        </option>
    <?php endforeach; ?>
</select>
<?php if ($isMultiple): ?>
    <small class="field-help-text">Tip: Hold Ctrl (Windows) or Cmd (Mac) to select multiple.</small>
<?php endif; ?>
<?php if (!empty($helperText)): ?>
    <small class="field-help-text"><?= Str::escape($helperText) ?></small>
<?php endif; ?>
