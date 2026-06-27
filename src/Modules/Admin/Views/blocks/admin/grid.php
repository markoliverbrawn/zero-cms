<?php
// src/Modules/Admin/Views/blocks/admin/grid.php

use Zero\Models\Media;

$items = $block['items'] ?? [];
?>
<div class="field-group">
    <label>Block Section Title</label>
    <input type="text" class="block-title-input" value="<?php echo htmlspecialchars($blockTitle, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter grid section title...">
</div>

<div class="field-group">
    <label>Grid Cards List</label>
    <div class="grid-items-list" style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 10px;">
        <?php foreach ($items as $index => $item): 
            $iTitle = $item['title'] ?? '';
            $iDesc = $item['desc'] ?? '';
            $iLinkUrl = $item['link_url'] ?? '';
            $iMediaId = $item['media_id'] ?? '';
            $filename = '';
            if (!empty($iMediaId)) {
                $media = Media::find($iMediaId);
                $filename = $media ? $media->filename : '';
            }
            ?>
            <div class="grid-item-row collapsed">
                <button type="button" class="btn-delete-grid-item" title="Remove Grid Card">
                    <?php echo \Zero\Core\App::svg('trash-2'); ?>
                </button>
                
                <!-- Collapsible Header Panel (Clickable to Toggle Collapse/Expand) -->
                <div class="grid-item-row-header">
                    <div class="grid-item-row-title-label">
                        <span class="grid-item-row-collapse-icon">
                            <?php echo \Zero\Core\App::svg('chevron-right'); ?>
                        </span>
                        <span class="grid-item-row-title-text"><?php echo !empty($iTitle) ? htmlspecialchars($iTitle, ENT_QUOTES, 'UTF-8') : 'Grid Card (Untitled)'; ?></span>
                    </div>
                    <div class="grid-item-controls">
                        <button type="button" class="btn-sort-grid-item-up" title="Move Card Up">
                            <?php echo \Zero\Core\App::svg('arrow-up'); ?>
                        </button>
                        <button type="button" class="btn-sort-grid-item-down" title="Move Card Down">
                            <?php echo \Zero\Core\App::svg('arrow-down'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Collapsible Fields Container -->
                <div class="block-child-fields-col grid-item-fields-container" style="width: 100%;">
                    <div class="field-group block-child-field-group-8">
                        <label class="block-child-label-desc">Card Title</label>
                        <input type="text" class="grid-item-title-input" value="<?php echo htmlspecialchars($iTitle, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter card title...">
                    </div>
                    <div class="field-group block-child-field-group-8">
                        <label class="block-child-label-desc">Card Image (Optional)</label>
                        <div class="block-child-image-select-row">
                            <input type="hidden" class="grid-item-media_id-input" value="<?php echo htmlspecialchars($iMediaId, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="text" class="grid-item-media-display-input" value="<?php echo htmlspecialchars($filename, ENT_QUOTES, 'UTF-8'); ?>" placeholder="No image selected" readonly style="flex: 1;">
                            <button type="button" class="btn-select-grid-image">Select</button>
                        </div>
                    </div>
                    <div class="field-group block-child-field-group-0">
                        <label class="block-child-label-desc">Card Description</label>
                        <textarea class="grid-item-desc-input" placeholder="Enter card description..." rows="2"><?php echo htmlspecialchars($iDesc, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="field-group block-child-field-group-0" style="margin-top: 8px;">
                        <label class="block-child-label-desc">Card Click URL Link (e.g. /intro)</label>
                        <input type="text" class="grid-item-link_url-input" value="<?php echo htmlspecialchars($iLinkUrl, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter card target URL...">
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn-add-grid-item" style="margin-top: 10px;">+ Add Grid Card</button>
</div>
