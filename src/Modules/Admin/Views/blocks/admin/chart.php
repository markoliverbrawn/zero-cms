<?php
// src/Modules/Admin/Views/blocks/admin/chart.php
use Zero\Core\App;
use Zero\Support\Str;

$items = $block['items'] ?? [];
?>
<div class="field-group">
    <label>Chart Title (Rich Text)</label>
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
    <label>Chart Data Points</label>
    <div class="chart-items-list">
        <?php foreach ($items as $item): ?>
            <?php 
            $iLabel = $item['label'] ?? '';
            $iValue = $item['value'] ?? '';
            ?>
            <div class="chart-item-row">
                <div class="block-child-fields-col">
                    <div class="field-group">
                        <label>Bar Label</label>
                        <input type="text" class="chart-item-label-input" value="<?php echo Str::escape($iLabel); ?>" placeholder="e.g. Zero CMS">
                    </div>
                    <div class="field-group">
                        <label>Numeric Value</label>
                        <input type="number" step="any" class="chart-item-value-input" value="<?php echo Str::escape($iValue); ?>" placeholder="e.g. 30.31">
                    </div>
                </div>
                <button type="button" class="btn-delete-chart-item" title="Remove Data Point">
                    <span class="icon-svg icon-svg-14">
                        <?php echo App::svg('trash-2'); ?>
                    </span>
                </button>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn-add-chart-item">+ Add Chart Point</button>
</div>
