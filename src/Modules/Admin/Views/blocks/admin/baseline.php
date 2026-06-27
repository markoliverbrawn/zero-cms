<?php
// src/Modules/Admin/Views/blocks/admin/baseline.php
$mediaId = $block['media_id'] ?? '';
$fullWidth = !empty($block['full_width']) && $block['full_width'] === '1';
?>
<div class="field-group">
    <label>Hero Title / Main Heading (Rich Text, Max 3 Lines)</label>
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
    <label>Hero Description</label>
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
        <label>Background Hero Image</label>
        <div class="block-flex-align-center">
            <input type="text" class="block-media_id-input flex-1" value="<?php echo htmlspecialchars($mediaId, ENT_QUOTES, 'UTF-8'); ?>" placeholder="No background image selected" readonly>
            <button type="button" class="btn-select-block-image width-auto">Select Image</button>
        </div>
    </div>
    <div class="field-group block-flex-col-1" style="display: flex; align-items: center; justify-content: flex-start; padding-top: 24px;">
        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; font-size: 14px;">
            <input type="checkbox" class="block-full_width-input" value="1" <?php echo $fullWidth ? 'checked' : ''; ?> style="width: 18px; height: 18px; cursor: pointer;">
            <span>Make Full Screen Width</span>
        </label>
    </div>
</div>
