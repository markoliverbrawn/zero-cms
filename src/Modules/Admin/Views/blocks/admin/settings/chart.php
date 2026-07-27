<?php
// src/Modules/Admin/Views/blocks/admin/settings/chart.php

$chartLayout = $block['chart_layout'] ?? 'horizontal';
?>
<div class="form-group">
    <label class="block-settings-label">Chart Layout</label>
    <select class="block-chart_layout-select">
        <option value="horizontal" <?php echo $chartLayout === 'horizontal' ? 'selected' : ''; ?>>Horizontal Bars</option>
        <option value="vertical" <?php echo $chartLayout === 'vertical' ? 'selected' : ''; ?>>Vertical Columns</option>
    </select>
    <small>Select the orientation of the bars inside the chart.</small>
</div>
