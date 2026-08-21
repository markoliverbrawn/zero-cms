<?php
// src/Support/Tests/AssetsCropGeometryTest.php
// Unit tests for the pure focal-point crop and scale geometry behind resized image variants.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Support\Assets;

echo "=== Assets Crop Geometry Tests ===\n";

// --- 1. Cover crop on a landscape source, square target ---
echo "  Testing square crop of a landscape source...\n";
$geometry = Assets::computeCrop(1000, 500, 300, 300, 50, 50);

assert_test($geometry['source_width'] === 500 && $geometry['source_height'] === 500, "Landscape square crop takes a full-height 500x500 window");
assert_test($geometry['source_x'] === 250, "Centred focal point puts a 500px window at x=250 on a 1000px source");
assert_test($geometry['source_y'] === 0, "Full-height window sits at y=0");
assert_test($geometry['width'] === 300 && $geometry['height'] === 300, "Output is exactly the requested 300x300");

// --- 2. Focal point moves the crop window horizontally ---
echo "  Testing horizontal focal point positioning...\n";
$left = Assets::computeCrop(1000, 500, 300, 300, 0, 50);
$right = Assets::computeCrop(1000, 500, 300, 300, 100, 50);

assert_test($left['source_x'] === 0, "Focal point at the far left clamps the window to x=0");
assert_test($right['source_x'] === 500, "Focal point at the far right clamps the window to the last valid x");
assert_test($left['source_width'] === $right['source_width'], "Focal point changes position, never window size");

// --- 3. Focal point moves the crop window vertically on a portrait source ---
echo "  Testing vertical focal point positioning on a portrait source...\n";
$portraitTop = Assets::computeCrop(500, 1000, 300, 300, 50, 0);
$portraitBottom = Assets::computeCrop(500, 1000, 300, 300, 50, 100);
$portraitMid = Assets::computeCrop(500, 1000, 300, 300, 50, 50);

assert_test($portraitTop['source_y'] === 0, "Focal point at the top clamps the window to y=0");
assert_test($portraitBottom['source_y'] === 500, "Focal point at the bottom clamps the window to the last valid y");
assert_test($portraitMid['source_y'] === 250, "Centred focal point centres the window vertically");
assert_test($portraitMid['source_x'] === 0, "Full-width window on a portrait source sits at x=0");

// --- 4. Non-square target aspect ratios ---
echo "  Testing non-square target aspect ratios...\n";
$wide = Assets::computeCrop(1000, 1000, 800, 400, 50, 50);

assert_test($wide['source_width'] === 1000, "A 2:1 crop of a square source spans the full width");
assert_test($wide['source_height'] === 500, "A 2:1 crop of a square source takes half the height");
assert_test($wide['source_y'] === 250, "The 2:1 window is centred vertically for a centred focal point");
assert_test($wide['width'] === 800 && $wide['height'] === 400, "Output matches the requested 800x400");

$tall = Assets::computeCrop(1000, 1000, 400, 800, 50, 50);
assert_test($tall['source_height'] === 1000 && $tall['source_width'] === 500, "A 1:2 crop of a square source spans the full height at half width");

// --- 5. Upscaling is refused ---
echo "  Testing that variants are never upscaled...\n";
$upscale = Assets::computeCrop(200, 200, 800, 800, 50, 50);

assert_test($upscale['width'] === 200 && $upscale['height'] === 200, "A 200x200 source requested at 800x800 stays 200x200");

$upscaleRatio = Assets::computeCrop(400, 200, 1600, 800, 50, 50);
assert_test($upscaleRatio['width'] === 400 && $upscaleRatio['height'] === 200, "A too-small source keeps its own size rather than being blown up");
assert_test(
    \abs(($upscaleRatio['width'] / $upscaleRatio['height']) - 2.0) < 0.01,
    "Refusing to upscale still preserves the requested 2:1 aspect ratio"
);

// --- 6. Contain scaling ---
echo "  Testing contain (scale to fit) geometry...\n";
$contain = Assets::computeContain(1000, 500, 400, 0);

assert_test($contain['width'] === 400 && $contain['height'] === 200, "Width-only scaling preserves aspect ratio");
assert_test($contain['source_x'] === 0 && $contain['source_y'] === 0, "Contain never offsets into the source");
assert_test($contain['source_width'] === 1000 && $contain['source_height'] === 500, "Contain reads the whole source");

$containHeight = Assets::computeContain(1000, 500, 0, 100);
assert_test($containHeight['width'] === 200 && $containHeight['height'] === 100, "Height-only scaling preserves aspect ratio");

$containBox = Assets::computeContain(1000, 500, 400, 400);
assert_test($containBox['width'] === 400 && $containBox['height'] === 200, "A box constrains on whichever axis binds first");

$containUpscale = Assets::computeContain(100, 50, 1000, 1000);
assert_test($containUpscale['width'] === 100 && $containUpscale['height'] === 50, "Contain never upscales past the source either");

// --- 7. geometry() dispatch ---
echo "  Testing the fit-mode dispatcher...\n";
$dispatchCover = Assets::geometry(1000, 500, 300, 300, Assets::FIT_COVER, 50, 50);
$dispatchContain = Assets::geometry(1000, 500, 300, 300, Assets::FIT_CONTAIN, 50, 50);

assert_test($dispatchCover['width'] === 300 && $dispatchCover['height'] === 300, "Cover fills the requested box exactly");
assert_test($dispatchContain['width'] === 300 && $dispatchContain['height'] === 150, "Contain fits inside the box without cropping");

$dispatchSingle = Assets::geometry(1000, 500, 400, 0, Assets::FIT_COVER, 50, 50);
assert_test($dispatchSingle['width'] === 400 && $dispatchSingle['height'] === 200, "A single dimension always scales, even when cover is requested");

// --- 8. Extreme focal values are clamped rather than escaping the canvas ---
echo "  Testing focal point clamping...\n";
$outOfRange = Assets::computeCrop(1000, 500, 300, 300, 900, -400);

assert_test($outOfRange['source_x'] >= 0 && $outOfRange['source_x'] <= 500, "An out-of-range focal X still yields a window inside the canvas");
assert_test($outOfRange['source_y'] >= 0, "An out-of-range focal Y still yields a window inside the canvas");

// --- 9. Degenerate one-pixel sources do not divide by zero or emit a zero-size canvas ---
echo "  Testing degenerate source sizes...\n";
$tiny = Assets::computeCrop(1, 1, 300, 300, 50, 50);
assert_test($tiny['width'] >= 1 && $tiny['height'] >= 1, "A 1x1 source yields at least a 1x1 output");

$sliver = Assets::computeCrop(1000, 1, 300, 300, 50, 50);
assert_test($sliver['width'] >= 1 && $sliver['height'] >= 1, "An extreme aspect ratio still yields a valid canvas size");

echo "Assets crop geometry tests completed successfully.\n\n";
