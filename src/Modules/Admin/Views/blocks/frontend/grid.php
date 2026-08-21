<?php
// src/Modules/Admin/Views/blocks/frontend/grid.php

use Zero\Support\Assets;
use Zero\Support\Str;

$gap = $block['gap'] ?? '16px';
$colsDesktop = $block['cols_desktop'] ?? '4';
$colsTablet = $block['cols_tablet'] ?? '2';
$colsMobile = $block['cols_mobile'] ?? '1';
$items = $block['items'] ?? [];

// This block used to run its own unscoped "SELECT * FROM media WHERE id IN (...)" purely to
// learn each item's mime type. Every referenced record is already loaded, tenant-scoped, by the
// eager-loader that built $resolveMedia, so the mime type is read straight from that primed
// registry instead -- one query fewer per grid, and no second place for tenant scoping to drift.
$cardWidth = 800;
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
        $iMediaUrl = '';
        if (!empty($iMediaId)) {
            $iMediaUrl = $resolveMedia($iMediaId);
            $mime = Assets::mime($iMediaId);
            $lowerUrl = strtolower($iMediaUrl);
            $isSvg = ($mime === 'image/svg+xml' || str_ends_with($lowerUrl, '.svg'));
            $isVideo = (str_starts_with($mime, 'video/') || str_ends_with($lowerUrl, '.mp4'));
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
                        <video src="<?php echo Str::escape($iMediaUrl); ?>" autoplay loop muted playsinline class="grid-card-video"></video>
                    <?php else: ?>
                        <img src="<?php echo Assets::url($iMediaUrl, width: $cardWidth, fit: Assets::FIT_CONTAIN); ?>"
                             srcset="<?php echo Str::escape(Assets::srcset($iMediaUrl, [300, 600, 800])); ?>"
                             sizes="(max-width: 768px) 100vw, (max-width: 1024px) 50vw, 25vw"
                             class="grid-card-image"
                             alt="<?php echo Str::escape($iTitle); ?>"
                             loading="lazy"
                             decoding="async" />
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
