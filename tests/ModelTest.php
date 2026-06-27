<?php
// tests/ModelTest.php
// Unit and integration tests for Active Record Model Engine (Zero\Models\Traits\IsModel)

require_once __DIR__ . '/bootstrap.php';

use Zero\Models\Page;
use Zero\Database\DB;
use Zero\Core\App;

echo "=== Active Record Model Component Tests ===\n";

// 1. Resolve site ID for testing tenant boundaries
$siteRow = DB::query("SELECT id FROM sites LIMIT 1")->fetch();
$siteId = $siteRow['id'] ?? \Zero\Support\Security::uuidv7();

require_once APPLICATION_ROOT . '/src/Models/Site.php';
$mockSite = new \Zero\Models\Site([
    'id' => $siteId,
    'name' => 'Test Site',
    'domain' => 'localhost',
    'theme' => 'default'
]);

try {
    $appReflector = new ReflectionClass(App::class);
    $siteProp = $appReflector->getProperty('currentSite');
    $siteProp->setAccessible(true);
    $siteProp->setValue(null, $mockSite);
} catch (Exception $e) {
    echo "Reflection helper error: " . $e->getMessage() . "\n";
}

// Clear any preceding mock session state and define the active tenant
$_SESSION['user_id'] = 'test-admin-user';

// Clean up any old stray test pages from previous runs
DB::query("DELETE FROM pages WHERE slug LIKE 'test-model-%'");

// 2. Test Create & Insert (with auto UUIDv7 and site_id injection)
echo "Testing model insert and auto-generating properties...\n";
$uniqueSlug = 'test-model-slug-' . bin2hex(random_bytes(4));
$page = new Page([
    'title' => 'Test Model Page',
    'slug' => $uniqueSlug,
    'status' => 'draft'
]);

$id = $page->save();

assert_test(!empty($id) && strlen($id) === 36, "Active Record saves new rows and auto-generates a 36-character UUIDv7 identifier");
assert_test($page->id === $id, "Assigns the generated UUID back to the model ID property");

// Fetch the saved page directly via raw SQL to inspect auto-injected database values
$rawPage = DB::query("SELECT * FROM pages WHERE id = ?", [$id])->fetch();
assert_test($rawPage['site_id'] === $siteId, "Automatically injects the active tenant site_id into the new database record");
assert_test($rawPage['type'] === 'page', "Automatically injects the static modelType ('page') as the polymorphic type column value");
assert_test(!empty($rawPage['created_at']) && !empty($rawPage['updated_at']), "Populates created_at and updated_at timestamps on database insert");

// 3. Test Find (Identity Map read and query recovery)
echo "Testing model retrieve (find)...\n";
// Read-through cached lookup
$fetchedPage = Page::find($id);
assert_test($fetchedPage instanceof Page, "Page::find retrieves and reinstates the matching record as a Page object");

// Test local time magic getter
assert_test(!empty($fetchedPage->created_at_local), "Magic property getter created_at_local successfully resolves localized datetime string");
assert_test($fetchedPage->title === 'Test Model Page', "Reconstitutes correct property values on retrieved objects");

// Verify identity caching - modifying the cached object should instantly show up in subsequent finds without DB hits
$fetchedPage->title = 'Mutated Cached Value';
$secondFetch = Page::find($id);
assert_test($secondFetch->title === 'Mutated Cached Value', "Page::find retrieves record from the identity cache map directly, keeping object reference identities");

// Reset fetchedPage state and title in the database
$fetchedPage->title = 'Test Model Page';

// 4. Test Update
echo "Testing model update...\n";
$fetchedPage->title = 'Updated Test Model Page';
$fetchedPage->status = 'published';
$updateResult = $fetchedPage->save();

assert_test($updateResult === $id, "Save triggers an update statement if model ID already exists in the database");

// Query the database directly to confirm modifications were physically written
$updatedRaw = DB::query("SELECT title, status FROM pages WHERE id = ?", [$id])->fetch();
assert_test($updatedRaw['title'] === 'Updated Test Model Page', "Correctly persists modified text attributes to database");
assert_test($updatedRaw['status'] === 'published', "Correctly persists modified state attributes to database");

// 5. Test tenant automatic isolation boundaries
echo "Testing tenant isolation...\n";
$allPages = Page::all();
$hasOurPage = false;
foreach ($allPages as $p) {
    if ($p->id === $id) $hasOurPage = true;
}
assert_test($hasOurPage, "Page::all includes our saved page under active tenant boundaries");

// Switch to a mock different site tenant to verify database isolation
$anotherSiteId = \Zero\Support\Security::uuidv7();
$mockAnotherSite = new \Zero\Models\Site([
    'id' => $anotherSiteId,
    'name' => 'Another Test Site',
    'domain' => 'another.localhost',
    'theme' => 'default'
]);
$siteProp->setValue(null, $mockAnotherSite); // Swap active site in App

// Search again - our page should NOT be retrieved since it belongs to the previous tenant
$isolatedPages = Page::all();
$isolatedHasPage = false;
foreach ($isolatedPages as $p) {
    if ($p->id === $id) $isolatedHasPage = true;
}
assert_test(!$isolatedHasPage, "Page::all isolates tenant queries and excludes pages belonging to other tenants");

// Clear identity map and reset active site back to original for subsequent tests
DB::setIdentity('pages', $id, null);
$siteProp->setValue(null, $mockSite);

// 6. Test soft deletes, trashed recovery, and permanent deletion
echo "Testing soft deletion and trashing lifecycle...\n";
$targetPage = Page::find($id);
assert_test($targetPage->deleted_at === null, "New pages are active and do not have a deleted_at timestamp set");

// Soft delete
$deleteResult = $targetPage->delete();
assert_test($deleteResult === true, "Model delete completes successfully");

// Verify database soft delete flag is set
$deletedRaw = DB::query("SELECT deleted_at FROM pages WHERE id = ?", [$id])->fetch();
assert_test(!empty($deletedRaw['deleted_at']), "Soft delete populates deleted_at column instead of deleting row");

// Verify model is excluded from standard finds
DB::setIdentity('pages', $id, null); // Clear cache
$softDeletedFind = Page::find($id);
assert_test($softDeletedFind === null, "find() returns null for soft-deleted records");

// Retrieve via findTrashed
$trashedRecord = Page::findTrashed($id);
assert_test($trashedRecord instanceof Page, "findTrashed() correctly recovers the soft-deleted object for inspection");
assert_test($trashedRecord->id === $id, "Recovered trashed record contains correct properties");

// Restore
$restoreResult = $trashedRecord->restore();
assert_test($restoreResult === true, "Model restore completes successfully");

$restoredRaw = DB::query("SELECT deleted_at FROM pages WHERE id = ?", [$id])->fetch();
assert_test($restoredRaw['deleted_at'] === null, "Restoring model unsets the deleted_at timestamp in the database");

$restoredFind = Page::find($id);
assert_test($restoredFind instanceof Page && $restoredFind->id === $id, "After restoration, standard find() successfully recovers the record again");

// Force Delete
echo "Testing force deletion (permanent wipe)...\n";
$restoredFind->forceDelete();
$forceDeletedRaw = DB::query("SELECT * FROM pages WHERE id = ?", [$id])->fetch();
assert_test($forceDeletedRaw === false, "forceDelete() physically and permanently removes the record from the database table");

echo "Active Record Model component tests completed.\n\n";
