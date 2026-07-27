<?php
// src/Modules/Admin/Views/blocks/admin/grid.php

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Models\Media;
use Zero\Support\Str;

$items = $block['items'] ?? [];

// Eager load all media records in a single database query to prevent N+1 queries!
$mediaIds = [];
foreach ($items as $item) {
    if (!empty($item['media_id'])) {
        $mediaIds[] = $item['media_id'];
    }
}

$mediaRecords = [];
if (!empty($mediaIds)) {
    $uniqueIds = array_unique(array_filter($mediaIds));
    if (!empty($uniqueIds)) {
        $placeholders = implode(',', array_fill(0, count($uniqueIds), '?'));
        $stmt = DB::query("SELECT * FROM media WHERE id IN ($placeholders)", array_values($uniqueIds));
        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            $mediaRecords[$row['id']] = $row;
        }
    }
}
?>
<div class="field-group">
    <label>Block Section Title</label>
    <input type="text" class="block-title-input" value="<?php echo Str::escape($blockTitle); ?>" placeholder="Enter grid section title...">
</div>

<div class="field-group">
    <label>Grid Cards List</label>
    <div class="grid-items-list" style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 10px;">
        <?php foreach ($items as $index => $item): 
            $iTitle = $item['title'] ?? '';
            $iDesc = $item['desc'] ?? '';
            $iLinkUrl = $item['link_url'] ?? '';
            $iMediaId = $item['media_id'] ?? '';
            $iColSpanDesktop = $item['col_span_desktop'] ?? '1';
            $iColSpanTablet = $item['col_span_tablet'] ?? '1';
            
            $filename = '';
            if (!empty($iMediaId) && isset($mediaRecords[$iMediaId])) {
                $filename = $mediaRecords[$iMediaId]['filename'] ?? '';
            }
            ?>
            <div class="grid-item-row collapsed">
                <button type="button" class="btn-delete-grid-item" title="Remove Grid Card">
                    <?php echo App::svg('trash-2'); ?>
                </button>
                
                <!-- Collapsible Header Panel (Clickable to Toggle Collapse/Expand) -->
                <div class="grid-item-row-header">
                    <div class="grid-item-row-title-label">
                        <span class="grid-item-row-collapse-icon">
                            <?php echo App::svg('chevron-right'); ?>
                        </span>
                        <span class="grid-item-row-title-text"><?php echo !empty($iTitle) ? Str::escape($iTitle) : 'Grid Card (Untitled)'; ?></span>
                    </div>
                    <div class="grid-item-controls">
                        <button type="button" class="btn-sort-grid-item-up" title="Move Card Up">
                            <?php echo App::svg('arrow-up'); ?>
                        </button>
                        <button type="button" class="btn-sort-grid-item-down" title="Move Card Down">
                            <?php echo App::svg('arrow-down'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Collapsible Fields Container -->
                <div class="block-child-fields-col grid-item-fields-container" style="width: 100%;">
                    <div class="field-group block-child-field-group-8">
                        <label class="block-child-label-desc">Card Title</label>
                        <input type="text" class="grid-item-title-input" value="<?php echo Str::escape($iTitle); ?>" placeholder="Enter card title...">
                    </div>
                    <div class="field-group block-child-field-group-8">
                        <label class="block-child-label-desc">Card Image (Optional)</label>
                        <div class="block-child-image-select-row">
                            <input type="hidden" class="grid-item-media_id-input" value="<?php echo Str::escape($iMediaId); ?>">
                            <input type="text" class="grid-item-media-display-input" value="<?php echo Str::escape($filename); ?>" placeholder="No image selected" readonly style="flex: 1;">
                            <button type="button" class="btn-select-grid-image">Select</button>
                        </div>
                    </div>
                    <div class="field-group block-child-field-group-0">
                        <label class="block-child-label-desc">Card Description</label>
                        <textarea class="grid-item-desc-input" placeholder="Enter card description..." rows="2"><?php echo Str::escape($iDesc); ?></textarea>
                    </div>
                    <div class="field-group block-child-field-group-0" style="margin-top: 8px;">
                        <label class="block-child-label-desc">Card Click URL Link (e.g. /intro)</label>
                        <input type="text" class="grid-item-link_url-input" value="<?php echo Str::escape($iLinkUrl); ?>" placeholder="Enter card target URL...">
                    </div>
                    <div class="block-flex-row" style="margin-top: 8px; display: flex; gap: 10px;">
                        <div class="field-group block-flex-col-1" style="flex: 1;">
                            <label class="block-child-label-desc">Desktop Column Span</label>
                            <select class="grid-item-col_span_desktop">
                                <option value="1" <?php echo $iColSpanDesktop === '1' ? 'selected' : ''; ?>>1 Column</option>
                                <option value="2" <?php echo $iColSpanDesktop === '2' ? 'selected' : ''; ?>>2 Columns</option>
                                <option value="3" <?php echo $iColSpanDesktop === '3' ? 'selected' : ''; ?>>3 Columns</option>
                                <option value="4" <?php echo $iColSpanDesktop === '4' ? 'selected' : ''; ?>>4 Columns</option>
                            </select>
                        </div>
                        <div class="field-group block-flex-col-1" style="flex: 1;">
                            <label class="block-child-label-desc">Tablet Column Span</label>
                            <select class="grid-item-col_span_tablet">
                                <option value="1" <?php echo $iColSpanTablet === '1' ? 'selected' : ''; ?>>1 Column</option>
                                <option value="2" <?php echo $iColSpanTablet === '2' ? 'selected' : ''; ?>>2 Columns</option>
                                <option value="3" <?php echo $iColSpanTablet === '3' ? 'selected' : ''; ?>>3 Columns</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn-add-grid-item" style="margin-top: 10px;">+ Add Grid Card</button>
</div>
