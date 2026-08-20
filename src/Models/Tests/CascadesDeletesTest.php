<?php
// tests/CascadesDeletesTest.php
// Unit tests for the new CascadesDeletes Core Model Trait

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Models\Media;
use Zero\Models\Site;
use Zero\Models\Page;
use Zero\Modules\FormBuilder\Models\Submission;

echo "=== CascadesDeletes Trait Component Tests ===\n";

// Clear original bootstrapped static properties to allow clean re-bootstrap
$refApp = new \ReflectionClass('Zero\Core\App');
$propBoot = $refApp->getProperty('bootstrapped');
$propBoot->setAccessible(true);
$propBoot->setValue(null, false);

$propSite = $refApp->getProperty('currentSite');
$propSite->setAccessible(true);
$propSite->setValue(null, null);

// Mock request headers
$_SERVER['HTTP_HOST'] = 'cascades.zero';

// Insert mock site for isolated integration testing
$mockSiteId = \Zero\Support\Security::uuidv7();
DB::query("
    INSERT INTO sites (id, name, domain, theme, enabled_modules, created_at, updated_at)
    VALUES (?, 'Cascading Deletes Site', 'cascades.zero', 'default', '[\"formbuilder\"]', NOW(), NOW())
", [$mockSiteId]);

App::bootstrap();

$siteId = App::getCurrentSiteId();
assert_test($siteId === $mockSiteId, "Correctly bootstraps mock site environment");

// 1. Create mock Media asset (to act as featured image)
echo "Creating dummy featured image...\n";
$media = new Media([
    'filename' => 'featured-hero.jpg',
    'path' => '/storage/uploads/featured-hero.jpg',
    'mime' => 'image/jpeg'
]);
$media->save();
$mediaId = $media->id;

assert_test(!empty($mediaId), "Featured image media asset successfully saved");

// 2. Create mock Page
echo "Creating dummy page...\n";
$page = new Page([
    'title' => 'Cascading Deletes Core Trait Test',
    'slug' => 'cascading-deletes-test',
    'summary' => 'This is a core test for the CascadesDeletes trait.',
    'content' => '[]',
    'status' => 'published'
]);
$page->save();
$pageId = $page->id;

assert_test(!empty($pageId), "Page successfully saved");

// 3. Soft Delete the Page
echo "Soft deleting the page...\n";
$page->delete();

// Verify Page is soft-deleted
assert_test(Page::find($pageId) === null, "Page is no longer findable via find() (soft-deleted)");
$trashedPage = Page::findTrashed($pageId);
assert_test($trashedPage !== null && !empty($trashedPage->deleted_at), "Page resides in trash with a valid deleted_at timestamp");

// Verify the media record has NOT been deleted alongside it
assert_test(Media::find($mediaId) !== null, "Media asset was NOT deleted (remains active)");

// Verify that Page::paginate with trash filter returns the soft-deleted page, and NOT the active ones
$trashPagination = Page::paginate(1, 10, ['trash' => true]);
$trashPageIds = array_map(fn($p) => $p->id, $trashPagination['data'] ?? []);
assert_test(in_array($pageId, $trashPageIds), "Page::paginate() with trash filter correctly returns the soft-deleted page");

// 4. Force Delete the Page
echo "Force deleting the page (permanent clean)...\n";
$trashedPage->forceDelete();

// Verify Page is permanently deleted from DB
assert_test(Page::findTrashed($pageId) === null, "Page is permanently removed from the database");

// Verify the media record is still untouched and alive!
assert_test(Media::find($mediaId) !== null, "Media asset remains active in the database (untouched during force deletion)");

// Clean up mock media asset
$media->forceDelete();
assert_test(Media::find($mediaId) === null, "Cleanup of mock media asset successfully complete");

// 5. Test Site dynamic cascade deletes
echo "Testing dynamic Site cascade deletions (Zero module dependencies)...\n";

$testSite = new Site([
    'name' => 'Decoupled Cascade Site',
    'domain' => 'decoupled.zero',
    'theme' => 'default',
    'enabled_modules' => '["formbuilder"]'
]);
$testSite->save();
$testSiteId = $testSite->id;

assert_test(!empty($testSiteId), "Site successfully created and saved");

// Create Page under testSite
$testPage = new Page([
    'site_id' => $testSiteId,
    'title' => 'Decoupled Site Page',
    'slug' => 'decoupled-page',
    'content' => '[]',
    'status' => 'published'
]);
$testPage->save();
$testPageId = $testPage->id;
assert_test(!empty($testPageId), "Page successfully saved under testSite");

// Create a module-owned child record (FormBuilder Submission) under testSite, registered
// dynamically via App::registerCascadeDelete() rather than hardcoded on the Site model
$testSubmission = new Submission([
    'site_id' => $testSiteId,
    'name' => 'Decoupled Submitter',
    'email' => 'submitter@decoupled.zero',
    'message' => 'Dynamic cascade submission'
]);
$testSubmission->save();
$testSubmissionId = $testSubmission->id;
assert_test(!empty($testSubmissionId), "Submission successfully saved under testSite");

// Create mock physical uploads for this site to verify automatic directory purging on soft-delete
$uploadDir = APPLICATION_ROOT . '/public/storage/uploads/' . $testSiteId;
@mkdir($uploadDir, 0775, true);
$cropsDir = $uploadDir . '/_crops';
@mkdir($cropsDir, 0775, true);
$tempFile1 = confine_test_path($uploadDir . '/file1.jpg', $uploadDir);
$tempFile2 = confine_test_path($cropsDir . '/crop1.jpg', $cropsDir);
file_put_contents($tempFile1, 'mock JPEG data');
file_put_contents($tempFile2, 'mock crop JPEG data');

assert_test(file_exists($tempFile1) === true, "Mock physical media file exists on disk prior to soft-deletion");
assert_test(file_exists($tempFile2) === true, "Mock physical crop file exists on disk prior to soft-deletion");

// Soft delete Site
echo "Soft deleting the Site...\n";
$testSite->delete();

// Verify Site is soft-deleted
assert_test(Site::find($testSiteId) === null, "Site is soft-deleted (find returns null)");
$trashedSite = Site::findTrashed($testSiteId);
assert_test($trashedSite !== null && !empty($trashedSite->deleted_at), "Site exists in trash with valid deleted_at");

// Verify dynamic cascade soft-deletions!
assert_test(Page::find($testPageId) === null, "Page is successfully soft-deleted via Site cascade");
assert_test(Submission::find($testSubmissionId) === null, "Submission is successfully soft-deleted via the dynamically registered Site cascade");

// Verify physical uploads are cleanly, recursively purged upon soft delete!
assert_test(file_exists($tempFile1) === false, "Mock physical media file is cleanly deleted from disk when site is soft-deleted");
assert_test(file_exists($tempFile2) === false, "Mock physical crop file is cleanly deleted from disk when site is soft-deleted");
assert_test(file_exists($uploadDir) === false, "Site uploads directory is cleanly deleted and recursively purged from disk when site is soft-deleted");

// Re-create mock physical uploads for this site to verify automatic directory purging on force-delete
@mkdir($uploadDir, 0775, true);
@mkdir($cropsDir, 0775, true);
file_put_contents($tempFile1, 'mock JPEG data');
file_put_contents($tempFile2, 'mock crop JPEG data');

assert_test(file_exists($tempFile1) === true, "Mock physical media file exists on disk prior to force-deletion");
assert_test(file_exists($tempFile2) === true, "Mock physical crop file exists on disk prior to force-deletion");

// Force delete Site
echo "Force deleting the Site...\n";
$trashedSite->forceDelete();

// Verify absolute permanent deletion across all related entities!
assert_test(Site::findTrashed($testSiteId) === null, "Site permanently deleted from DB");
assert_test(Page::findTrashed($testPageId) === null, "Page permanently deleted from DB via Site cascade force delete");
assert_test(Submission::findTrashed($testSubmissionId) === null, "Submission permanently deleted from DB via the dynamically registered Site cascade force delete");

// Verify physical uploads are cleanly, recursively purged upon force-delete!
assert_test(file_exists($tempFile1) === false, "Mock physical media file is cleanly deleted from disk when site is force-deleted");
assert_test(file_exists($tempFile2) === false, "Mock physical crop file is cleanly deleted from disk when site is force-deleted");
assert_test(file_exists($uploadDir) === false, "Site uploads directory is cleanly deleted and recursively purged from disk when site is force-deleted");

// 6. Verify we CANNOT delete the current site
echo "Testing blocked deletion of active tenant site...\n";
$currentSite = Site::find($mockSiteId);
assert_test($currentSite !== null, "Current site retrieved successfully");

$softDeleteBlocked = false;
try {
    $currentSite->delete();
} catch (\Exception $e) {
    if (strpos($e->getMessage(), "Deletion blocked") !== false) {
        $softDeleteBlocked = true;
    }
}
assert_test($softDeleteBlocked, "Soft deleting the active tenant site is successfully blocked and throws Exception");

// Clean up DB mock active site
DB::query("DELETE FROM sites WHERE id = ?", [$mockSiteId]);

// 7. Verify dynamic cascade delete registration
echo "Testing dynamic cascade delete registry...\n";
$parentClass = 'Zero\Models\Site';
$childClass = 'Zero\Modules\FormBuilder\Models\Submission';
$foreignKey = 'site_id';

App::registerCascadeDelete($parentClass, $childClass, $foreignKey);
$cascades = App::getCascadeDeletesFor($parentClass);

assert_test(isset($cascades[$childClass]) && $cascades[$childClass] === $foreignKey, "App::registerCascadeDelete and getCascadeDeletesFor correctly store and retrieve dynamic cascade configs");

echo "CascadesDeletes trait component tests completed successfully!\n";
