<?php
// src/Modules/Admin/Views/blocks/admin/chart.php
use Zero\Core\App;

$items = $block['items'] ?? [];
?>
<div class="field-group">
    <label>Chart Title</label>
    <input type="text" class="block-title-input" value="<?php echo htmlspecialchars($blockTitle, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter chart title...">
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
                        <input type="text" class="chart-item-label-input" value="<?php echo htmlspecialchars($iLabel, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Zero CMS">
                    </div>
                    <div class="field-group">
                        <label>Numeric Value</label>
                        <input type="number" step="any" class="chart-item-value-input" value="<?php echo htmlspecialchars($iValue, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. 30.31">
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
