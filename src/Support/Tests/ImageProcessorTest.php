<?php
// src/Support/Tests/ImageProcessorTest.php
// Verifies ImageProcessor::render() preserves per-pixel alpha transparency through a resize.
// GD requires both the source and destination images to be configured for per-pixel alpha before
// imagecopyresampled(); leaving the source in GD's default blending mode composites its
// transparent pixels against an opaque background instead of carrying them through.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Support\Assets;
use Zero\Support\ImageProcessor;

echo "=== ImageProcessor Tests ===\n";

if (!extension_loaded('gd')) {
    echo "  Skipping GD dependent tests (GD extension not loaded in CLI).\n";
    exit(0);
}
if (!Assets::isSupported()) {
    echo "  Skipping: this PHP build has no GD WebP support, so no variants are ever minted.\n";
    exit(0);
}

echo "  Testing that a resize preserves per-pixel transparency...\n";

// 1. Build a 100x100 source PNG: opaque red on top, fully transparent on the bottom.
$width = 100;
$height = 100;
$source = imagecreatetruecolor($width, $height);
imagealphablending($source, false);
imagesavealpha($source, true);

$opaqueRed = imagecolorallocatealpha($source, 255, 0, 0, 0);
$transparent = imagecolorallocatealpha($source, 0, 0, 0, 127);
imagefilledrectangle($source, 0, 0, $width - 1, ($height / 2) - 1, $opaqueRed);
imagefilledrectangle($source, 0, (int)($height / 2), $width - 1, $height - 1, $transparent);

ob_start();
imagepng($source);
$pngBytes = ob_get_clean();
imagedestroy($source);

assert_critical(is_string($pngBytes) && $pngBytes !== '', "Source transparent PNG fixture encoded successfully");

// 2. Resize it down through the real render() pipeline (PNG in, WebP out).
$variant = ImageProcessor::render($pngBytes, 50, 50, Assets::FIT_COVER);
assert_test($variant['width'] === 50 && $variant['height'] === 50, "Variant renders at the requested 50x50 geometry");

$rendered = imagecreatefromstring($variant['bytes']);
assert_critical($rendered instanceof \GdImage, "Rendered variant decodes cleanly");
imagealphablending($rendered, false);
imagesavealpha($rendered, true);

// 3. The top half must stay opaque red; the bottom half must stay fully transparent.
$topPixel = imagecolorat($rendered, 25, 10);
$topAlpha = ($topPixel >> 24) & 0x7F;
$topRed = ($topPixel >> 16) & 0xFF;
assert_test($topAlpha === 0, "Opaque region of the resized variant stays fully opaque (alpha={$topAlpha})");
assert_test($topRed > 200, "Opaque region keeps its red color after resampling (r={$topRed})");

$bottomPixel = imagecolorat($rendered, 25, 40);
$bottomAlpha = ($bottomPixel >> 24) & 0x7F;
assert_test($bottomAlpha > 100, "Transparent region of the resized variant stays transparent instead of being flattened opaque (alpha={$bottomAlpha})");

imagedestroy($rendered);

echo "ImageProcessor tests completed successfully.\n\n";
