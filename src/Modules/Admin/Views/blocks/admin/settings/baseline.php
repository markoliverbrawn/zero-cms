<?php
// src/Modules/Admin/Views/blocks/admin/settings/baseline.php
?>
<div class="form-group">
    <label class="block-settings-label">Hero Minimum Height (vh):</label>
    <select class="block-min_height-select">
        <option value="default" <?php echo ($block['min_height'] ?? '') === 'default' ? 'selected' : ''; ?>>Default (Theme Default)</option>
        <option value="40" <?php echo ($block['min_height'] ?? '') === '40' ? 'selected' : ''; ?>>40vh (Short)</option>
        <option value="50" <?php echo ($block['min_height'] ?? '') === '50' ? 'selected' : ''; ?>>50vh</option>
        <option value="60" <?php echo ($block['min_height'] ?? '') === '60' ? 'selected' : ''; ?>>60vh</option>
        <option value="75" <?php echo ($block['min_height'] ?? '') === '75' ? 'selected' : ''; ?>>75vh (Medium Hero)</option>
        <option value="90" <?php echo ($block['min_height'] ?? '') === '90' ? 'selected' : ''; ?>>90vh</option>
        <option value="100" <?php echo ($block['min_height'] ?? '') === '100' ? 'selected' : ''; ?>>100vh (Full Height Screen)</option>
    </select>
    <small>Sets the minimum vertical viewport height of the hero block.</small>
</div>
