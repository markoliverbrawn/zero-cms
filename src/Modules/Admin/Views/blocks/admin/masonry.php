<?php
// src/Modules/Admin/Views/blocks/admin/masonry.php

use Zero\Models\Media;

$items = $block['items'] ?? [];
?>
<div class="field-group">
    <label>Block Title</label>
    <input type="text" class="block-title-input" value="<?php echo htmlspecialchars($blockTitle, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter lookbook section title...">
</div>
<div class="field-group">
    <label>Lookbook Grid Cards List</label>
    <div class="masonry-items-list">
        <?php foreach ($items as $item): 
            $iTitle = $item['title'] ?? '';
            $iImgId = $item['media_id'] ?? '';
            $iDesc = $item['desc'] ?? '';
            
            $media = Media::find($iImgId);
            $filename = $media ? $media->filename : '';
            ?>
            <div class="masonry-item-row">
                <button type="button" class="btn-delete-masonry-item">Remove</button>
                <div class="block-child-fields-col">
                    <div class="field-group block-child-field-group-8">
                        <label class="block-child-label-desc">Item Title</label>
                        <input type="text" class="masonry-item-title-input" value="<?php echo htmlspecialchars($iTitle, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter item title...">
                    </div>
                    <div class="field-group block-child-field-group-8">
                        <label class="block-child-label-desc">Item Image</label>
                        <div class="block-child-image-select-row">
                            <input type="hidden" class="masonry-item-media_id-input" value="<?php echo htmlspecialchars($iImgId, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="text" class="masonry-item-media-display-input" value="<?php echo htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'); ?>" placeholder="No image selected" readonly style="flex: 1;">
                            <button type="button" class="btn-select-masonry-image">Select</button>
                        </div>
                    </div>
                    <div class="field-group block-child-field-group-0">
                        <label class="block-child-label-desc">Item Description</label>
                        <textarea class="masonry-item-desc-input" placeholder="Enter item description..." rows="2"><?php echo htmlspecialchars($iDesc, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn-add-masonry-item">+ Add Lookbook Card</button>
</div>
