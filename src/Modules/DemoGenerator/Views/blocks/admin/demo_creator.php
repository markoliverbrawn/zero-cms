<?php
// src/Modules/DemoGenerator/Views/blocks/admin/demo_creator.php

use Zero\Support\Str;

$blockId = $block['id'] ?? '';
if (empty($blockId)) {
    $blockId = 'dc_' . bin2hex(random_bytes(8));
}
?>
<input type="hidden" class="block-id-input" value="<?php echo Str::escape($blockId); ?>">

<div class="field-group">
    <label>Demo Form Title / Header (Rich Text)</label>
    <div class="editor">
        <div class="toolbar">
            <button type="button" data-cmd="bold"><strong>B</strong></button>
            <button type="button" data-cmd="italic"><em>I</em></button>
            <button type="button" data-cmd="insertSmall">Small</button>
            <button type="button" data-cmd="removeFormat">Clear</button>
        </div>
        <div class="editor-area block-editor-area block-title-rich-editor block-title-input" contenteditable="true"><?php echo $blockTitle; ?></div>
    </div>
</div>

<div class="field-group">
    <label>Explanatory Guide / Onboarding Text</label>
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
