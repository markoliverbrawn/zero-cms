<?php
use Zero\Support\Str;
// src/Modules/Admin/Views/blocks/admin/text_image.php
$mediaId = $block['media_id'] ?? '';
$imagePosition = $block['image_position'] ?? 'right';
?>
<div class="field-group">
    <label>Block Title (Rich Text)</label>
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
        <div class="editor-area block-editor-area block-editor-area-120" contenteditable="true"><?php echo $blockContent; ?></div>
    </div>
</div>
<div class="block-flex-row">
    <div class="field-group block-flex-col-1-5">
        <label>Section Image</label>
        <div class="block-flex-align-center">
            <input type="text" class="block-media_id-input flex-1" value="<?php echo Str::escape($mediaId); ?>" placeholder="No image selected" readonly>
            <button type="button" class="btn-select-block-image width-auto">Select Image</button>
        </div>
    </div>
    <div class="field-group block-flex-col-1">
        <label>Image Alignment</label>
        <select class="block-image_position-select">
            <option value="right" <?php echo $imagePosition === 'right' ? 'selected' : ''; ?>>Align Right</option>
            <option value="left" <?php echo $imagePosition === 'left' ? 'selected' : ''; ?>>Align Left</option>
        </select>
    </div>
</div>
