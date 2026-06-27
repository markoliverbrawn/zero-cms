<?php
// src/Modules/Admin/Views/blocks/admin/settings/grid.php

$colsDesktop = $block['cols_desktop'] ?? '4';
$colsTablet = $block['cols_tablet'] ?? '2';
$colsMobile = $block['cols_mobile'] ?? '1';
?>
<div class="form-group">
    <label class="block-settings-label">Desktop Columns</label>
    <select class="block-cols_desktop-select">
        <option value="4" <?php echo $colsDesktop === '4' ? 'selected' : ''; ?>>4 Columns</option>
        <option value="3" <?php echo $colsDesktop === '3' ? 'selected' : ''; ?>>3 Columns</option>
        <option value="2" <?php echo $colsDesktop === '2' ? 'selected' : ''; ?>>2 Columns</option>
        <option value="1" <?php echo $colsDesktop === '1' ? 'selected' : ''; ?>>1 Column</option>
    </select>
    <small>Select the number of columns to display on large desktop screens.</small>
</div>

<div class="form-group" style="margin-top: 10px;">
    <label class="block-settings-label">Tablet Columns</label>
    <select class="block-cols_tablet-select">
        <option value="3" <?php echo $colsTablet === '3' ? 'selected' : ''; ?>>3 Columns</option>
        <option value="2" <?php echo $colsTablet === '2' ? 'selected' : ''; ?>>2 Columns</option>
        <option value="1" <?php echo $colsTablet === '1' ? 'selected' : ''; ?>>1 Column</option>
    </select>
    <small>Select the number of columns to display on medium tablet screens.</small>
</div>

<div class="form-group" style="margin-top: 10px;">
    <label class="block-settings-label">Mobile Columns</label>
    <select class="block-cols_mobile-select">
        <option value="2" <?php echo $colsMobile === '2' ? 'selected' : ''; ?>>2 Columns</option>
        <option value="1" <?php echo $colsMobile === '1' ? 'selected' : ''; ?>>1 Column</option>
    </select>
    <small>Select the number of columns to display on small mobile screens.</small>
</div>
