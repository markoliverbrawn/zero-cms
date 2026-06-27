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

echo "Storage driver component tests completed.\n\n";
