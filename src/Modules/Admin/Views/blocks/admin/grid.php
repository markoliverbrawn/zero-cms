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
            <div class="grid-item-row">
                <button type="button" class="btn-delete-grid-item">Remove</button>
                
                <!-- Collapsible Header Panel -->
                <div class="grid-item-row-header" style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 12px; margin-bottom: 12px; border-bottom: 1px solid var(--border-color, #e2e8f0); width: 100%;">
                    <div class="grid-item-row-title-label" style="display: flex; align-items: center; gap: 6px; font-weight: bold; font-size: 0.85rem; cursor: pointer; color: var(--text-color, #0f172a); user-select: none;">
                        <span class="grid-item-row-collapse-icon" style="color: #64748b;">▼</span>
                        <span class="grid-item-row-title-text"><?php echo !empty($iTitle) ? htmlspecialchars($iTitle, ENT_QUOTES, 'UTF-8') : 'Grid Card (Untitled)'; ?></span>
                    </div>
                    <div style="display: flex; gap: 8px; margin-right: 90px; align-items: center;">
                        <button type="button" class="btn-sort-grid-item-up" style="padding: 4px 10px; font-size: 11px; cursor: pointer; border-radius: 4px;">▲ Up</button>
                        <button type="button" class="btn-sort-grid-item-down" style="padding: 4px 10px; font-size: 11px; cursor: pointer; border-radius: 4px;">▼ Down</button>
                        <button type="button" class="btn-toggle-grid-item-collapse" style="padding: 4px 10px; font-size: 11px; cursor: pointer; border-radius: 4px;">Collapse</button>
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
