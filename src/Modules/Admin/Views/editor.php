<div class="editor">
  <div class="toolbar">
    <button type="button" data-cmd="bold"><strong>B</strong></button>
    <button type="button" data-cmd="italic"><em>I</em></button>
    <button type="button" data-cmd="underline"><u>U</u></button>
    <button type="button" data-cmd="insertUnorderedList">UL</button>
    <button type="button" data-cmd="insertOrderedList">OL</button>
    <button type="button" data-cmd="createLink">A</button>
    <button type="button" data-cmd="insertTable" title="Insert Table">Table</button>
    <button type="button" data-cmd="removeFormat">Clear</button>
  </div>
  <div class="editor-area" contenteditable="true"><?php
use Zero\Support\Str; echo $record->{$field} ?? ''; ?></div>
  <input type="hidden" name="<?php echo Str::escape($field ?? 'content'); ?>" class="content-input" value="<?php echo Str::escape($record->{$field} ?? ''); ?>">
</div>
