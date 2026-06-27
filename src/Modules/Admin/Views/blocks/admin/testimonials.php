<?php
// src/Modules/Admin/Views/blocks/testimonials.php
$items = $block['items'] ?? [];
$duration = $block['duration'] ?? 5000;
?>
<div class="field-group">
    <label>Block Title</label>
    <input type="text" class="block-title-input" value="<?php echo htmlspecialchars($blockTitle, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter testimonials section title...">
</div>
<div class="field-group">
    <label>Carousel Slide Duration (ms)</label>
    <input type="number" class="testimonials-duration-input" value="<?php echo htmlspecialchars($duration, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. 5000" min="1000" step="500">
</div>
<div class="field-group">
    <label>Testimonials List</label>
    <div class="testimonials-items-list">
        <?php foreach ($items as $item): ?>
            <?php 
            $itemContent = $item['content'] ?? '';
            $itemPerson = $item['person'] ?? '';
            ?>
            <div class="testimonial-item-row">
                <button type="button" class="btn-delete-testimonial-item">Remove</button>
                <div class="block-child-fields-col">
                    <div class="field-group block-child-field-group-8">
                        <label class="block-child-label-desc">Quote / Content</label>
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
                            <div class="editor-area block-editor-area testimonial-item-content-input" contenteditable="true" style="min-height: 100px;"><?php echo $itemContent; ?></div>
                        </div>
                    </div>
                    <div class="field-group block-child-field-group-0">
                        <label class="block-child-label-desc">Author / Person</label>
                        <input type="text" class="testimonial-item-person-input" value="<?php echo htmlspecialchars($itemPerson, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Jane Doe, CEO at Studio">
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn-add-testimonial-item">+ Add Testimonial</button>
</div>
