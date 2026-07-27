<?php
use Zero\Support\Str;
// src/Modules/Admin/Views/blocks/accordion.php
$items = $block['items'] ?? [];
?>
<div class="field-group">
    <label>Block Title</label>
    <input type="text" class="block-title-input" value="<?php echo Str::escape($blockTitle); ?>" placeholder="Enter accordion section title...">
</div>
<div class="field-group">
    <label>Accordion Items</label>
    <div class="accordion-items-list">
        <?php foreach ($items as $item): ?>
            <?php 
            $itemHeader = $item['title'] ?? '';
            $itemContent = $item['content'] ?? '';
            ?>
            <div class="accordion-item-row">
                <button type="button" class="btn-delete-accordion-item">Remove</button>
                <div class="block-child-fields-col">
                    <div class="field-group block-child-field-group-8">
                        <label class="block-child-label-desc">Header / Question</label>
                        <input type="text" class="accordion-item-title-input" value="<?php echo Str::escape($itemHeader); ?>" placeholder="Enter heading question...">
                    </div>
                    <div class="field-group block-child-field-group-0">
                        <label class="block-child-label-desc">Panel Content / Answer</label>
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
                            <div class="editor-area block-editor-area accordion-item-content-input" contenteditable="true" style="min-height: 100px;"><?php echo $itemContent; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn-add-accordion-item">+ Add Accordion Item</button>
</div>
