<?php
use Zero\Support\Str;
// src/Modules/Shop/Views/blocks/categories.php
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
<div class="block-categories-guidelines-banner">
    <p class="block-categories-guidelines-title">✦ Categories Grid Block</p>
    <p class="block-categories-guidelines-desc">This block automatically fetches and lists all active product categories belonging to this site tenant, using their representative images and descriptions.</p>
</div>
