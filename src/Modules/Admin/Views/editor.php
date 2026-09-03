<div class="editor">
  <div class="toolbar">
    <?php foreach ($toolbarButtonsHtml ?? [] as $buttonHtml): ?>
        <?php echo $buttonHtml; ?>
    <?php endforeach; ?>
  </div>
  <div class="editor-area" contenteditable="true"><?php
use Zero\Support\Str; echo $record->{$field} ?? ''; ?></div>
  <input type="hidden" name="<?php echo Str::escape($field ?? 'content'); ?>" class="content-input" value="<?php echo Str::escape($record->{$field} ?? ''); ?>">
</div>
