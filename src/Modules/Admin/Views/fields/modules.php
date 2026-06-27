<?php
// src/Modules/Admin/Views/fields/modules.php

use Zero\Core\App;

$modules = json_decode($value ?? '[]', true);
if (!is_array($modules)) {
    $modules = [];
}

// Filter out system modules (like queue and admin) so they are never listed as enabled add-ons
$activePills = [];
foreach ($modules as $module) {
    $moduleLower = strtolower($module);
    if ($moduleLower === 'queue' || $moduleLower === 'admin') {
        continue;
    }
    $activePills[] = $module;
}

$totalActive = count($activePills);
$displayedPills = array_slice($activePills, 0, 3);
$hiddenPills = array_slice($activePills, 3);
$remainingCount = count($hiddenPills);
?>
<div class="module-pills-container">
    <?php if (empty($activePills)): ?>
        <span class="module-pill module-empty">
            No modules
        </span>
    <?php else: ?>
        <?php foreach ($displayedPills as $module): ?>
            <?php
            $moduleLower = strtolower($module);
            $icon = 'settings';
            $label = htmlspecialchars($module);
            
            if ($moduleLower === 'blog') {
                $icon = 'edit-3';
                $label = 'Blog';
            } elseif ($moduleLower === 'shop') {
                $icon = 'shop';
                $label = 'Shop';
            } elseif ($moduleLower === 'formbuilder') {
                $icon = 'clipboard';
                $label = 'Form Builder';
            } elseif ($moduleLower === 'forum') {
                $icon = 'users';
                $label = 'Forum';
            } elseif ($moduleLower === 'security') {
                $icon = 'shield';
                $label = 'Security';
            }
            ?>
            <span class="module-pill module-<?php echo htmlspecialchars($moduleLower); ?>" title="<?php echo $label; ?>">
                <span class="icon-svg">
                    <?php echo App::svg($icon); ?>
                </span>
                <span class="module-label"><?php echo $label; ?></span>
            </span>
        <?php endforeach; ?>

        <?php foreach ($hiddenPills as $module): ?>
            <?php
            $moduleLower = strtolower($module);
            $icon = 'settings';
            $label = htmlspecialchars($module);
            
            if ($moduleLower === 'blog') {
                $icon = 'edit-3';
                $label = 'Blog';
            } elseif ($moduleLower === 'shop') {
                $icon = 'shop';
                $label = 'Shop';
            } elseif ($moduleLower === 'formbuilder') {
                $icon = 'clipboard';
                $label = 'Form Builder';
            } elseif ($moduleLower === 'forum') {
                $icon = 'users';
                $label = 'Forum';
            } elseif ($moduleLower === 'security') {
                $icon = 'shield';
                $label = 'Security';
            }
            ?>
            <span class="module-pill module-<?php echo htmlspecialchars($moduleLower); ?> is-hidden" data-hidden="true" title="<?php echo $label; ?>">
                <span class="icon-svg">
                    <?php echo App::svg($icon); ?>
                </span>
                <span class="module-label"><?php echo $label; ?></span>
            </span>
        <?php endforeach; ?>

        <?php if ($remainingCount > 0): ?>
            <span class="module-pill module-more" data-count="<?php echo $remainingCount; ?>" title="Click to view all <?php echo $totalActive; ?> modules">
                <span class="module-label">+<?php echo $remainingCount; ?> more</span>
            </span>
        <?php endif; ?>
    <?php endif; ?>
</div>
