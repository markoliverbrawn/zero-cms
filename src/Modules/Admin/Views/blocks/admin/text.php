<?php
use Zero\Support\Str;
// src/Modules/Admin/Views/blocks/text.php
?>
<div class="field-group">
    <label>Block Title</label>
    <input type="text" class="block-title-input" value="<?php echo Str::escape($blockTitle); ?>" placeholder="Enter block title (optional)...">
</div>
<div class="field-group">
    <label>Content Description</label>
    <div class="editor">
        <div class="toolbar">
            <button type="button" data-cmd="bold"><strong>B</strong></button>
            <button type="button" data-cmd="italic"><em>I</em></button>
            <button type="button" data-cmd="underline"><u>U</u></button>
            <button type="button" data-cmd="insertUnorderedList">UL</button>
            <button type="button" data-cmd="insertOrderedList">OL</button>
            <button type="button" data-cmd="createLink">A</button>
            <button type="button" data-cmd="removeFormat">Clear</button>
        </div>
        <div class="editor-area block-editor-area" contenteditable="true"><?php echo $blockContent; ?></div>
    </div>
</div>
