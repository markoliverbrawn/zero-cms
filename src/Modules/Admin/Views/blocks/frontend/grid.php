<?php
// src/Modules/Admin/Views/blocks/frontend/grid.php

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Models\Media;
use Zero\Support\Str;

$gap = $block['gap'] ?? '16px';
$colsDesktop = $block['cols_desktop'] ?? '4';
$colsTablet = $block['cols_tablet'] ?? '2';
$colsMobile = $block['cols_mobile'] ?? '1';
$items = $block['items'] ?? [];

// Eager load all media records in a single database query to prevent N+1 queries!
$mediaIds = [];
foreach ($items as $item) {
    if (!empty($item['media_id'])) {
        $mediaIds[] = $item['media_id'];
    }
}

$mediaRecords = [];
if (!empty($mediaIds)) {
    $uniqueIds = array_unique(array_filter($mediaIds));
    if (!empty($uniqueIds)) {
        $placeholders = implode(',', array_fill(0, count($uniqueIds), '?'));
        $stmt = DB::query("SELECT * FROM media WHERE id IN ($placeholders)", array_values($uniqueIds));
        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            $mediaRecords[$row['id']] = $row;
        }
    }
}
?>
<div class="block-grid" style="--gap: <?php echo Str::escape($gap); ?>; --cols-desktop: <?php echo Str::escape($colsDesktop); ?>; --cols-tablet: <?php echo Str::escape($colsTablet); ?>; --cols-mobile: <?php echo Str::escape($colsMobile); ?>;">
    <?php foreach ($items as $item): 
        $iTitle = $item['title'] ?? '';
        $iDesc = $item['desc'] ?? '';
        $iLinkUrl = $item['link_url'] ?? '';
        $iMediaId = $item['media_id'] ?? '';
        $iColSpanDesktop = $item['col_span_desktop'] ?? '1';
        $iColSpanTablet = $item['col_span_tablet'] ?? '1';
        
        $isSvg = false;
        $isVideo = false;
        if (!empty($iMediaId) && isset($mediaRecords[$iMediaId])) {
            $media = $mediaRecords[$iMediaId];
            $mime = $media['mime'] ?? '';
            $path = $media['path'] ?? '';
            $isSvg = ($mime === 'image/svg+xml' || str_ends_with(strtolower($path), '.svg'));
            $isVideo = (str_starts_with($mime, 'video/') || str_ends_with(strtolower($path), '.mp4'));
        }

        $hasLink = !empty($iLinkUrl);
        $wrapperTag = $hasLink ? 'a' : 'div';
        
        $cardClasses = 'grid-card';
        if ($hasLink) {
            $cardClasses .= ' has-link';
        }
        if ($isSvg) {
            $cardClasses .= ' has-svg-icon';
        }
        
        $styleAttr = ' style="--col-span-desktop: ' . Str::escape($iColSpanDesktop) . '; --col-span-tablet: ' . Str::escape($iColSpanTablet) . ';"';
        $wrapperAttrs = $hasLink ? ' href="' . Str::escape($iLinkUrl) . '" class="' . $cardClasses . '"' . $styleAttr : ' class="' . $cardClasses . '"' . $styleAttr;
        ?>
        <<?php echo $wrapperTag; ?><?php echo $wrapperAttrs; ?>>
            <?php if (!empty($iMediaId)): ?>
                <div class="grid-card-image-wrapper<?php echo $isSvg ? ' is-svg' : ($isVideo ? ' is-video' : ''); ?>">
                    <?php if ($isVideo): ?>
                        <video src="<?php echo Str::escape($resolveMedia($iMediaId)); ?>" autoplay loop muted playsinline class="grid-card-video"></video>
                    <?php else: ?>
                        <img src="<?php echo Str::escape($resolveMedia($iMediaId)); ?>" class="grid-card-image" alt="" />
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="grid-card-content">
                <?php if (!empty($iTitle)): ?>
                    <h4 class="grid-card-title"><?php echo Str::escape($iTitle); ?></h4>
                <?php endif; ?>
                <?php if (!empty($iDesc)): ?>
                    <p class="grid-card-desc"><?php echo Str::escape($iDesc); ?></p>
                <?php endif; ?>
            </div>
        </<?php echo $wrapperTag; ?>>
    <?php endforeach; ?>
</div>
