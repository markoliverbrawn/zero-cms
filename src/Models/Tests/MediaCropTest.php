<?php
// src/Models/Tests/MediaCropTest.php
// Verifies the Media model's variant URL helpers: focal-point-aware square thumbnails, the
// deferred (render-on-request) contract, and that renditions never land in the uploads tree.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Models\Media;
use Zero\Support\Assets;
use Zero\Support\ImageProcessor;
use Zero\Support\VariantCache;

echo "=== Media Focal-Point Variant URL Tests ===\n";

// Ensure GD is available in this test environment
if (!extension_loaded('gd')) {
    echo "  Skipping GD dependent tests (GD extension not loaded in CLI).\n";
    exit(0);
}
if (!Assets::isSupported()) {
    echo "  Skipping: this PHP build has no GD WebP support, so no variants are ever minted.\n";
    exit(0);
}

// 1. Create a mock portrait image in storage
$siteId = 'default';
$testFilename = 'mock-test-portrait.jpg';
$testPath = '/storage/uploads/' . $siteId . '/' . $testFilename;
$uploadsDir = APPLICATION_ROOT . '/public/storage/uploads/' . $siteId;
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

$physicalPath = confine_test_path(APPLICATION_ROOT . '/public' . $testPath, $uploadsDir);

/**
 * Write (or rewrite) the source fixture image.
 *
 * A 100x200 portrait canvas, top half red and bottom half blue, so a focal crop is verifiable
 * from the rendered pixels. Rewritten on demand rather than created once, because
 * public/storage/uploads is shared global state across the parallel worker slots and
 * SeederScriptTest shells out to bin/seed, which recursively cleans that whole tree.
 *
 * @param string $path Absolute destination path.
 * @return void
 */
function writePortraitFixture(string $path): void
{
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $img = imagecreatetruecolor(100, 200);
    imagefilledrectangle($img, 0, 0, 99, 99, imagecolorallocate($img, 255, 0, 0));
    imagefilledrectangle($img, 0, 100, 99, 199, imagecolorallocate($img, 0, 0, 255));
    imagejpeg($img, $path, 90);
    imagedestroy($img);
}

writePortraitFixture($physicalPath);
assert_critical(file_exists($physicalPath), "Mock portrait image created successfully");

// 2. Instantiate and configure Media model
$media = new Media();
$media->id = 'test-media-id-12345';
$media->filename = $testFilename;
$media->path = $testPath;
$media->mime = 'image/jpeg';
$media->site_id = $siteId;
$media->width = 100;
$media->height = 200;
$media->focus_x = 50;
$media->focus_y = 75; // Focus on the bottom half of the portrait
$media->created_at = '2026-01-01 00:00:00';
$media->updated_at = '2026-01-02 00:00:00';

// 3. Square thumbnail URL
echo "  Testing getSquareCropUrl()...\n";
$cropUrl = $media->getSquareCropUrl(50);

assert_test(!empty($cropUrl), "Crop URL returned successfully: {$cropUrl}");
assert_test(strpos($cropUrl, '/' . Assets::CACHE_DIRECTORY . '/') === 0, "Crop URL points into the dedicated variant cache");
assert_test(strpos($cropUrl, '50x50-cover-') !== false, "Crop URL describes a 50x50 cover rendition");
assert_test(substr($cropUrl, -5) === '.webp', "Crop URL resolves to a WebP rendition");

// The point of moving the cache out of uploads: a thumbnail must never appear beside the original.
assert_test(strpos($cropUrl, '/storage/uploads/') === false, "Crop URL never points inside the uploads tree");
assert_test(!is_dir($uploadsDir . '/_crops'), "No _crops folder is created inside the user's own media folder");
assert_test(!in_array('_crops', array_map('basename', glob($uploadsDir . '/*') ?: []), true), "The uploads folder gains no generated-thumbnail folder");

// 4. Generation is deferred, not synchronous
echo "  Testing that thumbnail generation is deferred to first request...\n";
$absolutePath = VariantCache::absolutePath(ltrim($cropUrl, '/'));
assert_test(!file_exists($absolutePath), "Asking for a thumbnail URL renders nothing; the image is produced on first request");
assert_test($media->getSquareCropUrl(50) === $cropUrl, "Repeated calls return an identical URL, so the cache can hit");

// 5. Rendering that URL's rendition honours the focal point
echo "  Testing focal-point cropping of the deferred rendition...\n";
writePortraitFixture($physicalPath);
$sourceBytes = file_get_contents($physicalPath);
$variant = ImageProcessor::render($sourceBytes, 50, 50, Assets::FIT_COVER, $media->focus_x, $media->focus_y);

assert_test($variant['width'] === 50 && $variant['height'] === 50, "Rendered variant is exactly 50x50");

$rendered = imagecreatefromstring($variant['bytes']);
assert_critical($rendered !== false, "Rendered variant decodes cleanly");
$sample = imagecolorat($rendered, 25, 25);
$red = ($sample >> 16) & 0xFF;
$blue = $sample & 0xFF;
assert_test($blue > 200 && $red < 60, "A focus_y of 75 crops to the lower (blue) half; sampled r={$red} b={$blue}");
imagedestroy($rendered);

// 6. Storing and invalidating the rendition
echo "  Testing variant cache storage and invalidation...\n";
VariantCache::store(ltrim($cropUrl, '/'), $variant['bytes']);

assert_test(file_exists($absolutePath), "Rendered variant is published at the exact path its URL describes");
assert_test(VariantCache::fetch(ltrim($cropUrl, '/')) === $variant['bytes'], "A stored variant is served back byte-identical");

writePortraitFixture($physicalPath);
$purged = VariantCache::forget($siteId, (string)$media->id);
assert_test($purged > 0, "Invalidation removed {$purged} cached rendition(s)");
assert_test(!file_exists($absolutePath), "Cached rendition is gone after invalidation");
assert_test(file_exists($physicalPath), "Invalidation never touches the original upload");

// 7. Moving the focal point republishes under a new URL
echo "  Testing focal-point change invalidation...\n";
$media->focus_y = 10;
$media->updated_at = '2026-01-03 00:00:00';
$refocusedUrl = $media->getSquareCropUrl(50);

assert_test($refocusedUrl !== $cropUrl, "Re-focusing publishes the thumbnail under a new URL");
assert_test(strpos($refocusedUrl, '50x50-cover-') !== false, "The new URL still describes the same requested rendition");

// 8. Arbitrary renditions through getVariantUrl()
echo "  Testing getVariantUrl()...\n";
$scaled = $media->getVariantUrl(80, null, Assets::FIT_CONTAIN);
$banner = $media->getVariantUrl(400, 200);

assert_test(strpos($scaled, '80x0-contain-') !== false, "A width-only request scales without cropping: {$scaled}");
assert_test(strpos($banner, '400x200-cover-') !== false, "A two-dimension request crops to fill: {$banner}");

// 9. Non-images and private files keep their ordinary URLs
echo "  Testing pass-through for non-resizable media...\n";
$document = new Media();
$document->id = 'test-doc-id-12345';
$document->filename = 'brochure.pdf';
$document->path = '/storage/uploads/' . $siteId . '/brochure.pdf';
$document->mime = 'application/pdf';
$document->site_id = $siteId;
$document->created_at = '2026-01-01 00:00:00';

assert_test($document->getSquareCropUrl(50) === $document->path, "A non-image keeps its own path");

$private = new Media();
$private->id = 'test-private-id-123';
$private->filename = 'confidential.jpg';
$private->path = '/storage/uploads/' . $siteId . '/confidential.jpg';
$private->mime = 'image/jpeg';
$private->site_id = $siteId;
$private->visibility = 'private';
$private->created_at = '2026-01-01 00:00:00';

assert_test(
    $private->getSquareCropUrl(50) === '/admin/secure-download/test-private-id-123',
    "Private media is never republished as a public variant; it keeps its gated route"
);
assert_test($private->getUrl() === '/admin/secure-download/test-private-id-123', "Private media keeps its access-gated download URL");

// Clean up
VariantCache::clear($siteId);
if (file_exists($physicalPath)) {
    unlink($physicalPath);
}
if (is_dir($uploadsDir) && count(glob($uploadsDir . '/*')) === 0) {
    rmdir($uploadsDir);
}
Assets::clearRegistry();

echo "Media focal-point variant URL tests completed successfully.\n\n";
