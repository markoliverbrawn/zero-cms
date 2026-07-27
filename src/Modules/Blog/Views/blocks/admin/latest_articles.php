<?php
use Zero\Support\Str;
// src/Modules/Blog/Views/blocks/latest_articles.php
$limit = $block['limit'] ?? 3;
$layout = $block['layout'] ?? 'grid';
?>

<div class="field-group">
    <label>Block Title</label>
    <input type="text" class="block-title-input" value="<?php echo Str::escape($blockTitle); ?>" placeholder="Enter block title...">
</div>
<div class="block-flex-row">
    <div class="field-group block-flex-col-1">
        <label>Articles Limit</label>
        <select class="block-limit-select">
            <option value="3" <?php echo $limit == 3 ? 'selected' : ''; ?>>3 Articles</option>
            <option value="5" <?php echo $limit == 5 ? 'selected' : ''; ?>>5 Articles</option>
            <option value="6" <?php echo $limit == 6 ? 'selected' : ''; ?>>6 Articles</option>
            <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10 Articles</option>
        </select>
    </div>
    <div class="field-group block-flex-col-1">
        <label>Display Layout</label>
        <select class="block-layout-select">
            <option value="grid" <?php echo $layout === 'grid' ? 'selected' : ''; ?>>Grid Layout</option>
            <option value="list" <?php echo $layout === 'list' ? 'selected' : ''; ?>>List Layout</option>
        </select>
    </div>
</div>
