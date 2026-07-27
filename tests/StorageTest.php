<?php
// tests/StorageTest.php
// Integration test to verify the Storage Driver framework and LocalStorageDriver behaviors.

require_once __DIR__ . '/bootstrap.php';

use Zero\Core\Storage\Storage;
use Zero\Models\Media;

echo "=== Storage Driver Component Tests ===\n";

$testFile = 'test-file-' . bin2hex(random_bytes(4)) . '.txt';
$testContent = "Zero CMS storage driver test payload: " . bin2hex(random_bytes(16));

// 1. Test raw write and existence
echo "  Testing Storage::write() and Storage::exists()...\n";
$writeResult = Storage::write($testFile, $testContent);
assert_test($writeResult, "Write operation completed successfully");
assert_test(Storage::exists($testFile), "File exists on storage");

// 2. Test reading content and URL resolution
echo "  Testing Storage::getUrl()...\n";
$resolvedUrl = Storage::getUrl($testFile);

$driverName = \Zero\Core\Env::get('STORAGE_DRIVER', 'local');
if ($driverName === 'gcs') {
    $bucketName = \Zero\Core\Env::get('GCS_BUCKET_NAME');
    assert_test(strpos($resolvedUrl, "https://storage.googleapis.com/{$bucketName}/") === 0, "Resolved URL matches correct GCS public layout: {$resolvedUrl}");
    assert_test(Storage::exists($testFile), "File exists in the Google Cloud bucket");
} else {
    assert_test(strpos($resolvedUrl, '/storage/uploads/') === 0, "Resolved URL matches correct local public layout: {$resolvedUrl}");
    $physicalPath = APPLICATION_ROOT . '/public/storage/uploads/' . $testFile;
    assert_test(file_exists($physicalPath), "Physical file exists on local disk");
    assert_test(file_get_contents($physicalPath) === $testContent, "Physical file content matches payload");
}

// 3. Test rename
echo "  Testing Storage::rename()...\n";
$renamedFile = 'renamed-' . $testFile;
$renameResult = Storage::rename($testFile, $renamedFile);
assert_test($renameResult, "Rename operation completed successfully");
assert_test(!Storage::exists($testFile), "Old file path no longer exists");
assert_test(Storage::exists($renamedFile), "New file path exists on storage");

// 4. Test delete
echo "  Testing Storage::delete()...\n";
$deleteResult = Storage::delete($renamedFile);
assert_test($deleteResult, "Delete operation completed successfully");
assert_test(!Storage::exists($renamedFile), "File no longer exists after deletion");

// 5. Test cleanDirectory
echo "  Testing Storage::cleanDirectory()...\n";
$testDir = 'test-dir-' . bin2hex(random_bytes(4));
$subFile1 = $testDir . '/file1.txt';
$subFile2 = $testDir . '/file2.txt';

Storage::write($subFile1, "file1");
Storage::write($subFile2, "file2");

assert_test(Storage::exists($subFile1), "Subfile 1 created successfully");
assert_test(Storage::exists($subFile2), "Subfile 2 created successfully");

$cleanResult = Storage::cleanDirectory($testDir);
assert_test($cleanResult, "Directory cleaning executed successfully");
assert_test(!Storage::exists($subFile1), "Subfile 1 was physically deleted");
assert_test(!Storage::exists($subFile2), "Subfile 2 was physically deleted");

// Clean up virtual folders
@rmdir(APPLICATION_ROOT . '/public/storage/uploads/' . $testDir);

// 6. Test Media model physical file deletion on forceDelete
echo "  Testing Media model physical file deletion on forceDelete...\n";

$mediaFilename = 'media-delete-test-' . bin2hex(random_bytes(4)) . '.txt';
$mediaRelativePath = 'default/' . $mediaFilename;
$mediaPublicPath = '/storage/uploads/' . $mediaRelativePath;

// Write physical file matching media path first
Storage::write($mediaRelativePath, "media test content");
assert_test(Storage::exists($mediaRelativePath), "Physical file successfully written to storage");

// Save Media active record referencing this path
$mediaRecord = new Media([
    'filename' => $mediaFilename,
    'path' => $mediaPublicPath,
    'mime' => 'text/plain'
]);
$mediaRecord->save();
$mediaRecordId = $mediaRecord->id;

assert_test(!empty($mediaRecordId), "Media model record saved successfully");
assert_test(Media::find($mediaRecordId) !== null, "Media active record successfully exists in DB");

// Perform permanent forceDelete on Media record
echo "    Force deleting Media record...\n";
$mediaRecord->forceDelete();

// Verify that BOTH database record AND physical file are deleted!
assert_test(Media::find($mediaRecordId) === null, "Media database active record successfully deleted");
assert_test(!Storage::exists($mediaRelativePath), "Media physical file successfully deleted from storage");

// 6b. Test Media model physical file deletion in a simulated multi-tenant environment (checking resolvePath prefix duplication bug)
echo "  Testing Media model physical file deletion in simulated multi-tenant environment...\n";

// Mock an active site ID
$mockSiteId = \Zero\Support\Security::uuidv7();
$refApp = new \ReflectionClass('Zero\Core\App');
$propSite = $refApp->getProperty('currentSite');
$propSite->setAccessible(true);
$propSite->setValue(null, new \Zero\Models\Site([
    'id' => $mockSiteId,
    'name' => 'Active Site',
    'domain' => 'active.zero',
    'theme' => 'default'
]));

// Create a file scoped under a different site (cross-tenant)
$otherSiteId = \Zero\Support\Security::uuidv7();
$mediaFilename = 'media-delete-multitenant-' . bin2hex(random_bytes(4)) . '.txt';
// Path stored in DB already contains the other site ID prefix
$mediaRelativePath = $otherSiteId . '/' . $mediaFilename;
$mediaPublicPath = '/storage/uploads/' . $mediaRelativePath;

// Write physical file to disk using the specific path directly
$physicalPath = APPLICATION_ROOT . '/public/storage/uploads/' . $mediaRelativePath;
@mkdir(dirname($physicalPath), 0775, true);
file_put_contents($physicalPath, "tenant test content");
assert_test(file_exists($physicalPath), "Physical file successfully written to storage for other tenant: {$physicalPath}");

// Save Media active record referencing this path
$mediaRecord = new Media([
    'filename' => $mediaFilename,
    'path' => $mediaPublicPath,
    'mime' => 'text/plain'
]);
$mediaRecord->save();
$mediaRecordId = $mediaRecord->id;

assert_test(!empty($mediaRecordId), "Media record saved successfully for other tenant");

// Perform permanent forceDelete on Media record
echo "    Force deleting Media record for other tenant while logged into active site...\n";
$mediaRecord->forceDelete();

// Verify that the database record is deleted AND physical file is physically deleted from disk (not hijacked by active site id)
assert_test(Media::find($mediaRecordId) === null, "Media database record successfully deleted");
assert_test(!file_exists($physicalPath), "Media physical file successfully deleted from disk (resolved without active site prefix duplication)");

// Cleanup folder
@rmdir(dirname($physicalPath));

// Reset currentSite to null
$propSite->setValue(null, null);

// 7. Test automatic image optimization
echo "  Testing automatic image optimization (resizing and compression)...\n";
if (extension_loaded('gd')) {
    $largeImageWidth = 1500;
    $largeImageHeight = 1000;
    $img = imagecreatetruecolor($largeImageWidth, $largeImageHeight);
    $bg = imagecolorallocate($img, 255, 0, 0);
    imagefill($img, 0, 0, $bg);
    
    $tmpImgPath = tempnam(sys_get_temp_dir(), 'test_large_img_');
    imagejpeg($img, $tmpImgPath, 100);
    imagedestroy($img);
    
    $targetImgPath = 'optimized-test-image-' . bin2hex(random_bytes(4)) . '.jpg';
    
    // Store image via putFile
    $putResult = Storage::putFile($targetImgPath, $tmpImgPath);
    assert_test($putResult, "Large image uploaded successfully");
    
    // Verify saved image dimensions
    $physicalSavedPath = APPLICATION_ROOT . '/public/storage/uploads/' . $targetImgPath;
    $savedInfo = @getimagesize($physicalSavedPath);
    assert_test($savedInfo !== false, "Saved file is a valid image");
    assert_test($savedInfo[0] <= 1200, "Width of saved image is resized to no larger than 1200px (Actual width: {$savedInfo[0]}px)");
    assert_test($savedInfo[1] <= 1200, "Height of saved image is resized to no larger than 1200px (Actual height: {$savedInfo[1]}px)");
    
    // Cleanup
    @unlink($tmpImgPath);
    Storage::delete($targetImgPath);
} else {
    echo "    Skipping image optimization test (GD extension not loaded).\n";
}

echo "Storage driver component tests completed.\n\n";
