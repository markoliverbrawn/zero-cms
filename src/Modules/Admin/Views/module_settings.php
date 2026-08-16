<?php
// src/Modules/Admin/Views/module_settings.php

use Zero\Core\App;
use Zero\Support\Str;
?>
<div class="editrecord">
  <div class="model-edit-header">
    <h2><?php echo Str::escape($moduleLabel); ?> Settings</h2>
  </div>

  <?php if (!empty($success)): ?>
    <div class="settings-flash-message settings-flash-success"><?php echo Str::escape($success); ?></div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="settings-flash-message settings-flash-error"><?php echo Str::escape($error); ?></div>
  <?php endif; ?>

  <form method="post" id="module-settings-form" action="">
    <input type="hidden" name="csrf" value="<?php echo Str::escape($csrf ?? ''); ?>">

    <?php foreach ($schema as $key => $fieldConfig): ?>
      <?php
        $type = $fieldConfig['type'] ?? 'text';
        $label = $fieldConfig['label'] ?? \ucwords(\str_replace('_', ' ', $key));
        $value = $values[$key] ?? ($fieldConfig['default'] ?? '');
        $required = !empty($fieldConfig['required']);
        $helperText = $fieldConfig['helper_text'] ?? ($fieldConfig['description'] ?? '');
      ?>
      <div class="form-field-wrapper field-width-<?php echo Str::escape($fieldConfig['width'] ?? 'full'); ?>">
        <?php if ($type === 'checkbox'): ?>
          <label class="settings-checkbox-label">
            <input type="checkbox" name="<?php echo Str::escape($key); ?>" value="1" <?php echo !empty($value) ? 'checked' : ''; ?>>
            <?php echo Str::escape($label); ?>
          </label>
        <?php else: ?>
          <label><?php echo Str::escape($label); ?></label>
          <?php if ($type === 'select'): ?>
            <select name="<?php echo Str::escape($key); ?>" <?php echo $required ? 'required' : ''; ?>>
              <?php foreach (($fieldConfig['options'] ?? []) as $optionVal => $optionLabel): ?>
                <option value="<?php echo Str::escape((string)$optionVal); ?>" <?php echo (string)$optionVal === (string)$value ? 'selected' : ''; ?>>
                  <?php echo Str::escape($optionLabel); ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php elseif ($type === 'textarea'): ?>
            <textarea name="<?php echo Str::escape($key); ?>" <?php echo $required ? 'required' : ''; ?>><?php echo Str::escape((string)$value); ?></textarea>
          <?php elseif ($type === 'number'): ?>
            <input type="number" step="any" name="<?php echo Str::escape($key); ?>" value="<?php echo Str::escape((string)$value); ?>" <?php echo $required ? 'required' : ''; ?>>
          <?php else: ?>
            <input type="text" name="<?php echo Str::escape($key); ?>" value="<?php echo Str::escape((string)$value); ?>" <?php echo $required ? 'required' : ''; ?>>
          <?php endif; ?>
        <?php endif; ?>

        <?php if (!empty($helperText)): ?>
          <small class="field-help-text"><?php echo Str::escape($helperText); ?></small>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <div class="form-actions">
      <button type="submit" class="btn-save">Save Settings</button>
    </div>
  </form>

  <p><a href="/admin/dashboard">Back to Dashboard</a></p>
</div>
