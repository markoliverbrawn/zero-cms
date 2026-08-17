<?php
// tests/MediaCropTest.php
// Integration and unit test to verify focal-point square cropping generation and cache reset.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Models\Media;
use Zero\Database\DB;

echo "=== Media Focal-Point Crop & Cache Reset Tests ===\n";

// Ensure GD is available in this test environment
if (!extension_loaded('gd')) {
    echo "  ⚠️ Skipping GD dependent tests (GD extension not loaded in CLI).\n";
    exit(0);
}

// 1. Create a mock portrait image in storage
$testFilename = 'mock-test-portrait.jpg';
$testPath = '/storage/uploads/default/' . $testFilename;
$uploadsDir = APPLICATION_ROOT . '/public/storage/uploads/default';
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

$physicalPath = confine_test_path(APPLICATION_ROOT . '/public' . $testPath, $uploadsDir);

// Create a 100x200 portrait canvas
$img = imagecreatetruecolor(100, 200);
$red = imagecolorallocate($img, 255, 0, 0);
imagefill($img, 0, 0, $red);
imagejpeg($img, $physicalPath, 90);
imagedestroy($img);

assert_test(file_exists($physicalPath), "Mock portrait image created successfully");

// 2. Instantiate and configure Media model
$media = new Media();
$media->id = 'test-media-id-12345';
$media->filename = $testFilename;
$media->path = $testPath;
$media->mime = 'image/jpeg';
$media->site_id = 'default';
$media->focus_x = 50;
$media->focus_y = 75; // Focus on the bottom half of portrait

// 3. Generate Crop and check caching
echo "  Testing getSquareCropUrl()...\n";
$cropUrl = $media->getSquareCropUrl(50); // Resize to 50x50

assert_test(!empty($cropUrl), "Crop URL returned successfully: {$cropUrl}");
assert_test(strpos($cropUrl, '/_crops/') !== false, "Crop path contains _crops folder");

$croppedPhysicalPath = APPLICATION_ROOT . '/public' . $cropUrl;
assert_test(file_exists($croppedPhysicalPath), "Cropped image physically exists on disk");
$croppedPhysicalPath = confine_test_path($croppedPhysicalPath, APPLICATION_ROOT . '/public/storage/uploads/default/_crops');

$croppedImg = imagecreatefromjpeg($croppedPhysicalPath);
assert_test(imagesx($croppedImg) === 50, "Cropped image width is exactly 50px");
assert_test(imagesy($croppedImg) === 50, "Cropped image height is exactly 50px");
imagedestroy($croppedImg);

// Check that calling again loads from cache (file modified time or existence)
$mtimeBefore = filemtime($croppedPhysicalPath);
usleep(100000); // 0.1s sleep
$cropUrlCached = $media->getSquareCropUrl(50);
$mtimeAfter = filemtime($croppedPhysicalPath);

assert_test($cropUrl === $cropUrlCached, "Cached crop URL is identical on subsequent calls");
assert_test($mtimeBefore === $mtimeAfter, "Subsequent crop call loaded from cache (file was not overwritten)");

// 4. Test cache reset when updating or changing focus
echo "  Testing crop cache reset and unlink...\n";
// Simulate focus points update cache clearing inside FilesController:
$cropsDir = APPLICATION_ROOT . "/public/storage/uploads/default/_crops";
$pattern = "{$cropsDir}/crop_{$media->id}_*.jpg";

$files = glob($pattern);
assert_test(count($files) > 0, "Discovered active cached crop files before reset");

foreach ($files as $f) {
    @unlink(confine_test_path($f, $cropsDir));
}

$filesAfter = glob($pattern);
assert_test(count($filesAfter) === 0, "Successfully purged all cached crop files for this media ID");

// Clean up files
@unlink($physicalPath);
if (file_exists($croppedPhysicalPath)) {
    @unlink($croppedPhysicalPath);
}
@rmdir($cropsDir);

echo "Media Crop and Caching tests completed successfully.\n\n";
