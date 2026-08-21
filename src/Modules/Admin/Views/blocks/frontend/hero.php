<?php
// src/Modules/Admin/Views/blocks/frontend/hero.php

use Zero\Support\Assets;
use Zero\Support\Security;
use Zero\Support\Str;

$title = $block['title'] ?? '';
$content = $block['content'] ?? '';
$mediaId = $block['media_id'] ?? '';
$fullWidth = !empty($block['full_width']) && $block['full_width'] === '1';

$isVideo = false;
$resolvedUrl = '';
if (!empty($mediaId)) {
    // Media type comes from the registry the eager-loader primed rather than a per-block model
    // lookup, so a page with several heroes does not pay a query for each of them.
    $isVideo = str_starts_with(Assets::mime($mediaId), 'video/');
    $resolvedUrl = $resolveMedia($mediaId);
}

// A hero has a defined height and is cropped to fill it, which makes it the block where the
// focal point matters most: this is where a centred crop would otherwise cut off the subject.
$backgroundUrl = '';
if (!empty($resolvedUrl) && !$isVideo) {
    $backgroundUrl = Assets::url($resolvedUrl, width: 1920, height: 900);
}

// Custom properties only. The gradient stack and layout live in assets/css/blocks/hero.css --
// the template's job is to hand the stylesheet the two values it cannot know statically.
$styleVars = [];
if ($backgroundUrl !== '') {
    $styleVars[] = "--hero-bg-image: url('" . $backgroundUrl . "')";
}

$minHeight = $block['min_height'] ?? 'default';
if (!empty($minHeight) && $minHeight !== 'default') {
    $styleVars[] = '--hero-min-height: ' . (int)$minHeight . 'vh';
}

$styleAttr = empty($styleVars) ? '' : ' style="' . implode('; ', $styleVars) . ';"';
?>
<div class="block-hero <?php echo $fullWidth ? 'full-width-hero' : ''; ?> <?php echo $isVideo ? 'has-video-bg' : ''; ?>"<?php echo $styleAttr; ?>>
    <?php if ($isVideo && !empty($resolvedUrl)): ?>
        <!-- Continually looping background video -->
        <video class="hero-video-bg" loop muted playsinline preload="none">
            <source data-src="<?php echo Str::escape($resolvedUrl); ?>" type="video/mp4">
        </video>
        <div class="hero-video-overlay"></div>
    <?php endif; ?>

    <div class="hero-text-container">
        <?php if (!empty($title)): ?>
            <h1><?php echo Security::sanitizeHtml($title); ?></h1>
        <?php endif; ?>
        <?php if (!empty($content)): ?>
            <div class="hero-content-area">
                <?php echo Security::sanitizeHtml($content); ?>
            </div>
        <?php endif; ?>
    </div>
</div>
