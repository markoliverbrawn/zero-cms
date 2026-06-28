<?php
// src/Modules/Admin/Views/blocks/frontend/grid.php

use Zero\Models\Media;

$colsDesktop = $block['cols_desktop'] ?? '4';
$colsTablet = $block['cols_tablet'] ?? '2';
$colsMobile = $block['cols_mobile'] ?? '1';
$items = $block['items'] ?? [];
?>
<div class="block-grid" style="--cols-desktop: <?php echo htmlspecialchars($colsDesktop, ENT_QUOTES, 'UTF-8'); ?>; --cols-tablet: <?php echo htmlspecialchars($colsTablet, ENT_QUOTES, 'UTF-8'); ?>; --cols-mobile: <?php echo htmlspecialchars($colsMobile, ENT_QUOTES, 'UTF-8'); ?>;">
    <?php foreach ($items as $item): 
        $iTitle = $item['title'] ?? '';
        $iDesc = $item['desc'] ?? '';
        $iLinkUrl = $item['link_url'] ?? '';
        $iMediaId = $item['media_id'] ?? '';
        
        $isSvg = false;
        $isVideo = false;
        if (!empty($iMediaId)) {
            $media = Media::find($iMediaId);
            if ($media) {
                $isSvg = ($media->mime === 'image/svg+xml' || str_ends_with(strtolower($media->path), '.svg'));
                $isVideo = (str_starts_with($media->mime, 'video/') || str_ends_with(strtolower($media->path), '.mp4'));
            }
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
        
        $wrapperAttrs = $hasLink ? ' href="' . htmlspecialchars($iLinkUrl, ENT_QUOTES, 'UTF-8') . '" class="' . $cardClasses . '"' : ' class="' . $cardClasses . '"';
        ?>
        <<?php echo $wrapperTag; ?><?php echo $wrapperAttrs; ?>>
            <?php if (!empty($iMediaId)): ?>
                <div class="grid-card-image-wrapper<?php echo $isSvg ? ' is-svg' : ($isVideo ? ' is-video' : ''); ?>">
                    <?php if ($isVideo): ?>
                        <video src="<?php echo htmlspecialchars($resolveMedia($iMediaId), ENT_QUOTES, 'UTF-8'); ?>" autoplay loop muted playsinline class="grid-card-video"></video>
                    <?php else: ?>
                        <img src="<?php echo htmlspecialchars($resolveMedia($iMediaId), ENT_QUOTES, 'UTF-8'); ?>" class="grid-card-image" alt="" />
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="grid-card-content">
                <?php if (!empty($iTitle)): ?>
                    <h4 class="grid-card-title"><?php echo htmlspecialchars($iTitle, ENT_QUOTES, 'UTF-8'); ?></h4>
                <?php endif; ?>
                <?php if (!empty($iDesc)): ?>
                    <p class="grid-card-desc"><?php echo htmlspecialchars($iDesc, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
            </div>
        </<?php echo $wrapperTag; ?>>
    <?php endforeach; ?>
</div>
