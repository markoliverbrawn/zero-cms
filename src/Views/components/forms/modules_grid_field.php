<?php
// src/Views/components/forms/modules_grid_field.php

use Zero\Support\Str;
?>
<?php if ($showLabel): ?><label><?= Str::escape($label) ?></label><?php endif; ?>
<div class="admin-modules-container">
    <?php foreach ($modules as $module): ?>
        <label class="admin-modules-label">
            <input type="checkbox" name="<?= Str::escape($name) ?>[]" value="<?= Str::escape($module['id']) ?>" <?= $module['checked'] ? 'checked' : '' ?> class="admin-modules-input">
            <span><strong><?= Str::escape($module['name']) ?></strong> (<?= Str::escape($module['desc']) ?>)</span>
        </label>
    <?php endforeach; ?>
</div>
<?php if (!empty($helperText)): ?>
    <small class="field-help-text"><?= Str::escape($helperText) ?></small>
<?php endif; ?>
