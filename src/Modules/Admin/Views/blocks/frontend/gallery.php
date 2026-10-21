<?php
// src/Modules/Admin/Views/blocks/frontend/gallery.php

use Zero\Support\Assets;
use Zero\Support\Str;

$mediaIds = $block['media_ids'] ?? [];

// The grid cells are a fixed 4:3 aspect ratio (see assets/css/blocks/gallery.css), so the
// thumbnail is cropped to match rather than shipping a full-size image the browser then
// downscales. The lightbox is a separate, much larger rendition that is only ever fetched if
// the visitor actually opens one -- which is what data-src is for: gallery.js reads it to
// populate the modal, so src and data-src being the same file (as they used to be) meant every
// visitor paid full-resolution bytes for a thumbnail nobody had clicked yet.
$thumbnailWidth = 600;
$thumbnailHeight = 450;
$lightboxWidth = 1800;

// Layout settings (see blocks/admin/settings/gallery.php). Every value is checked against an
// allowlist and published as a CSS custom property for gallery.css to consume. Unset columns and
// gap emit nothing, so existing galleries keep the stylesheet's auto-fit grid. Once any column
// count is set the grid switches to fixed columns, filling unset sizes with 2 / 3 / 4.
$allowed = static fn($value, array $ok, string $default): string => in_array((string)$value, $ok, true) ? (string)$value : $default;
$layout = $allowed($block['layout'] ?? 'grid', ['grid', 'masonry'], 'grid');
$colsMobile = $allowed($block['cols_mobile'] ?? '', ['1', '2'], '');
$colsTablet = $allowed($block['cols_tablet'] ?? '', ['2', '3', '4'], '');
$colsDesktop = $allowed($block['cols_desktop'] ?? '', ['3', '4', '5', '6'], '');
$colsWide = $allowed($block['cols_wide'] ?? '', ['5', '6', '7', '8'], '');
$gap = $allowed($block['gap'] ?? '', ['0', '16px', '24px', '32px'], '');

$hasFixedCols = $colsMobile . $colsTablet . $colsDesktop . $colsWide !== '';
$styleVars = [];
if ($hasFixedCols) {
    $colsDesktop = $colsDesktop ?: '4';
    $styleVars['--gallery-cols-mobile'] = $colsMobile ?: '2';
    $styleVars['--gallery-cols-tablet'] = $colsTablet ?: '3';
    $styleVars['--gallery-cols-desktop'] = $colsDesktop;
    $styleVars['--gallery-cols-wide'] = $colsWide ?: $colsDesktop;
}
if ($gap !== '') {
    $styleVars['--gallery-gap'] = $gap;
}

$wrapperClass = 'block block-gallery gallery-layout-' . $layout . ($hasFixedCols ? ' gallery-fixed-cols' : '');
$styleAttr = '';
foreach ($styleVars as $name => $value) {
    $styleAttr .= $name . ': ' . $value . '; ';
}

// Masonry keeps each photo's natural shape, so it asks for a width-only rendition instead of
// the 4:3 crop the grid uses.
$isMasonry = $layout === 'masonry';
$thumbnailCropHeight = $isMasonry ? null : $thumbnailHeight;
$thumbnailAspect = $isMasonry ? null : 4 / 3;
?>
<div class="<?php echo $wrapperClass; ?>"<?php if ($styleAttr !== ''): ?> style="<?php echo trim($styleAttr); ?>"<?php endif; ?>>
  <div class="gallery-grid">
    <?php if (!empty($mediaIds)): ?>
      <?php foreach ($mediaIds as $img):
        $mediaUrl = $resolveMedia($img);
        if (empty($mediaUrl)) {
            continue;
        }
        $titleText = Assets::title($img);
        $thumbnailSize = Assets::size($mediaUrl, $thumbnailWidth, $thumbnailCropHeight);
        ?>
        <div class="gallery-item">
          <img src="<?php echo Assets::url($mediaUrl, width: $thumbnailWidth, height: $thumbnailCropHeight); ?>"
               srcset="<?php echo Str::escape(Assets::srcset($mediaUrl, [400, 600, 900], $thumbnailAspect)); ?>"
               sizes="(max-width: 600px) 100vw, 300px"
               <?php if ($thumbnailSize !== null): ?>width="<?php echo $thumbnailSize['width']; ?>" height="<?php echo $thumbnailSize['height']; ?>"<?php endif; ?>
               class="gallery-img gallery-lightbox-trigger"
               data-src="<?php echo Assets::url($mediaUrl, width: $lightboxWidth, fit: Assets::FIT_CONTAIN); ?>"
               data-title="<?php echo Str::escape($titleText); ?>"
               alt="<?php echo Str::escape($titleText); ?>"
               loading="lazy"
               decoding="async" />
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Beautiful, Zero-Dependency Fullscreen Lightbox Modal Overlay -->
<div id="gallery-lightbox" class="gallery-lightbox">
    <!-- Large Close Button -->
    <button id="gallery-lightbox-close" class="gallery-lightbox-close">&times;</button>

    <!-- Lightbox Image Container -->
    <div id="gallery-lightbox-content" class="gallery-lightbox-content">
        <img id="gallery-lightbox-img" src="" alt="">
        <h4 id="gallery-lightbox-title"></h4>
    </div>
</div>
