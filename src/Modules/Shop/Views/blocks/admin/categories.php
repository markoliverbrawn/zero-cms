<?php
use Zero\Support\Str;
// src/Modules/Shop/Views/blocks/categories.php
?>

<div class="field-group">
    <label>Block Title</label>
    <input type="text" class="block-title-input" value="<?php echo Str::escape($blockTitle); ?>" placeholder="Enter categories title...">
</div>
<div class="block-categories-guidelines-banner">
    <p class="block-categories-guidelines-title">✦ Categories Grid Block</p>
    <p class="block-categories-guidelines-desc">This block automatically fetches and lists all active product categories belonging to this site tenant, using their representative images and descriptions.</p>
</div>
