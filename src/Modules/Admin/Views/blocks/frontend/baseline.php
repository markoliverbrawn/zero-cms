<?php
// src/Modules/Admin/Views/blocks/frontend/baseline.php

use Zero\Models\Media;
use Zero\Support\Security;

$title = $block['title'] ?? '';
$content = $block['content'] ?? '';
$mediaId = $block['media_id'] ?? '';
$fullWidth = !empty($block['full_width']) && $block['full_width'] === '1';

$isVideo = false;
$resolvedUrl = '';
if (!empty($mediaId)) {
    $mediaRec = Media::find($mediaId);
    $isVideo = $mediaRec && str_starts_with($mediaRec->mime ?? '', 'video/');
    $resolvedUrl = $resolveMedia($mediaId);
}

$bgStyle = '';
if (!empty($resolvedUrl) && !$isVideo) {
    $bgStyle = "background-image: linear-gradient(to bottom, transparent 60%, var(--bg-color, #051424) 100%), linear-gradient(to right, var(--bg-color, #051424) 0%, rgba(5, 20, 36, 0.75) 50%, rgba(5, 20, 36, 0.25) 100%), url('{$resolvedUrl}');";
}

$minHeight = $block['min_height'] ?? 'default';
if (!empty($minHeight) && $minHeight !== 'default') {
    $bgStyle .= " min-height: {$minHeight}vh;";
}
?>
<div class="block-baseline <?php echo $fullWidth ? 'full-width-hero' : ''; ?> <?php echo $isVideo ? 'has-video-bg' : ''; ?>" style="<?php echo $bgStyle; ?>">
    <?php if ($isVideo && !empty($resolvedUrl)): ?>
        <!-- Continually looping background video -->
        <video class="hero-video-bg" loop muted playsinline preload="none">
            <source data-src="<?php echo htmlspecialchars($resolvedUrl, ENT_QUOTES, "UTF-8"); ?>" type="video/mp4">
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
