<?php
// src/Modules/Admin/Views/fields/modules.php

use Zero\Core\App;
use Zero\Support\Str;

$modules = json_decode($value ?? '[]', true);
if (!is_array($modules)) {
    $modules = [];
}

// Index discovered modules by ID for efficient, O(1) lookup
$moduleObjects = [];
foreach (App::getModules() as $m) {
    $moduleObjects[strtolower($m->getId())] = $m;
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

// Helper function to resolve dynamic metadata and accent colors for a module
$getModuleMeta = function (string $module) use ($moduleObjects): array {
    $moduleLower = strtolower($module);
    $icon = 'settings';
    $label = Str::escape($module);
    $accentColor = '#64748b'; // default fallback for unrecognized/addons

    if ($moduleLower === 'blog') {
        $icon = 'edit-3';
        $label = 'Blog';
    } elseif ($moduleLower === 'shop') {
        $icon = 'shop';
        $label = 'Shop';
    } elseif ($moduleLower === 'formbuilder') {
        $icon = 'clipboard';
        $label = 'Form Builder';
    } elseif ($moduleLower === 'security') {
        $icon = 'shield';
        $label = 'Security';
    } elseif ($moduleLower === 'site-search') {
        $icon = 'search';
        $label = 'Search';
    } elseif ($moduleLower === 'demogenerator') {
        $icon = 'zap';
        $label = 'Demo Generator';
    }

    if (isset($moduleObjects[$moduleLower])) {
        $accentColor = $moduleObjects[$moduleLower]->getAccentColor();
    }

    return [
        'icon' => $icon,
        'label' => $label,
        'accent' => $accentColor
    ];
};
?>
<div class="module-pills-container">
    <?php if (empty($activePills)): ?>
        <span class="module-pill module-empty">
            No modules
        </span>
    <?php else: ?>
        <?php foreach ($displayedPills as $module): ?>
            <?php
            $meta = $getModuleMeta($module);
            $moduleLower = strtolower($module);
            ?>
            <span class="module-pill module-<?php echo Str::escape($moduleLower); ?>" 
                  style="--module-accent: <?php echo Str::escape($meta['accent']); ?>;" 
                  title="<?php echo $meta['label']; ?>">
                <span class="icon-svg">
                    <?php echo App::svg($meta['icon']); ?>
                </span>
                <span class="module-label"><?php echo $meta['label']; ?></span>
            </span>
        <?php endforeach; ?>

        <?php foreach ($hiddenPills as $module): ?>
            <?php
            $meta = $getModuleMeta($module);
            $moduleLower = strtolower($module);
            ?>
            <span class="module-pill module-<?php echo Str::escape($moduleLower); ?> is-hidden" 
                  style="--module-accent: <?php echo Str::escape($meta['accent']); ?>;" 
                  data-hidden="true" 
                  title="<?php echo $meta['label']; ?>">
                <span class="icon-svg">
                    <?php echo App::svg($meta['icon']); ?>
                </span>
                <span class="module-label"><?php echo $meta['label']; ?></span>
            </span>
        <?php endforeach; ?>

        <?php if ($remainingCount > 0): ?>
            <span class="module-pill module-more" data-count="<?php echo $remainingCount; ?>" title="Click to view all <?php echo $totalActive; ?> modules">
                <span class="module-label">+<?php echo $remainingCount; ?> more</span>
            </span>
        <?php endif; ?>
    <?php endif; ?>
</div>
