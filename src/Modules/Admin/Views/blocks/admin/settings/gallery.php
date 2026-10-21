<?php
// src/Modules/Admin/Views/blocks/admin/settings/gallery.php

// Blank values mean "not set": the frontend then keeps gallery.css's own auto-fit grid, so
// galleries saved before these settings existed render exactly as they always have.
$layout = (string)($block['layout'] ?? 'grid');
$colsMobile = (string)($block['cols_mobile'] ?? '');
$colsTablet = (string)($block['cols_tablet'] ?? '');
$colsDesktop = (string)($block['cols_desktop'] ?? '');
$colsWide = (string)($block['cols_wide'] ?? '');
$gap = (string)($block['gap'] ?? '');

$options = static function (array $values, string $current): string {
    $html = '';
    foreach ($values as $value => $label) {
        $selected = (string)$value === $current ? ' selected' : '';
        $html .= '<option value="' . $value . '"' . $selected . '>' . $label . '</option>';
    }
    return $html;
};
?>
<div class="form-group">
    <label class="block-settings-label">Layout</label>
    <select class="block-layout-select">
        <?php echo $options(['grid' => 'Grid (cropped 4:3)', 'masonry' => 'Masonry (natural shapes)'], $layout); ?>
    </select>
    <small>Grid crops every photo to the same shape; masonry keeps each photo's own proportions.</small>
</div>

<div class="block-settings-row">
    <div class="form-group">
        <label class="block-settings-label">Mobile Columns</label>
        <select class="block-cols_mobile-select"><?php echo $options(['' => 'Auto', '1' => '1', '2' => '2'], $colsMobile); ?></select>
    </div>
    <div class="form-group">
        <label class="block-settings-label">Tablet Columns</label>
        <select class="block-cols_tablet-select"><?php echo $options(['' => 'Auto', '2' => '2', '3' => '3', '4' => '4'], $colsTablet); ?></select>
    </div>
    <div class="form-group">
        <label class="block-settings-label">Desktop Columns</label>
        <select class="block-cols_desktop-select"><?php echo $options(['' => 'Auto', '3' => '3', '4' => '4', '5' => '5', '6' => '6'], $colsDesktop); ?></select>
    </div>
    <div class="form-group">
        <label class="block-settings-label">Wide Screen Columns</label>
        <select class="block-cols_wide-select"><?php echo $options(['' => 'Same as desktop', '5' => '5', '6' => '6', '7' => '7', '8' => '8'], $colsWide); ?></select>
    </div>
</div>
<small>Leave every size on Auto to fit as many 220px photos as the width allows. Setting any size switches to fixed columns (unset sizes use 2 / 3 / 4).</small>

<div class="form-group">
    <label class="block-settings-label">Spacing</label>
    <select class="block-gap-select">
        <?php echo $options(['' => 'Default', '0' => 'None', '16px' => 'Small', '24px' => 'Medium', '32px' => 'Large'], $gap); ?>
    </select>
</div>
