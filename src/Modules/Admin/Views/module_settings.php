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
      <div class="form-field-wrapper field-width-<?php echo Str::escape($fieldConfig['width'] ?? 'full'); ?>">
        <?php echo App::makeFormField($fieldConfig['type'] ?? 'text', $key, $fieldConfig + ['value' => $values[$key] ?? ($fieldConfig['default'] ?? '')])->render(); ?>
      </div>
    <?php endforeach; ?>

    <div class="form-actions">
      <button type="submit" class="btn-save">Save Settings</button>
    </div>
  </form>

  <p><a href="/admin/dashboard">Back to Dashboard</a></p>
</div>
