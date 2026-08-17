<?php
// tests/PrecedenceTest.php
// Integration test to verify the IsOrderable trait and model reordering features.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Database\DB;
use Zero\Models\Page;
use Zero\Core\App;

echo "=== Precedence Reordering Component Tests ===\n";

// 1. Resolve site ID and domain for multi-tenant isolation
$siteRow = DB::query("SELECT id, domain FROM sites LIMIT 1")->fetch();
if (!$siteRow) {
    echo "  Creating dummy site for testing...\n";
    $siteId = \Zero\Support\Security::uuidv7();
    $domain = 'test.localhost';
    DB::query("INSERT INTO sites (id, name, domain, theme, enabled_modules, created_at, updated_at) VALUES (?, 'Test Site', ?, 'default', '[]', NOW(), NOW())", [$siteId, $domain]);
} else {
    $siteId = $siteRow['id'];
    $domain = $siteRow['domain'];
}

require_once APPLICATION_ROOT . '/src/Models/Site.php';
$mockSite = new \Zero\Models\Site([
    'id' => $siteId,
    'name' => 'Test Site',
    'domain' => $domain,
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

// Clean up any stray test pages from previous aborted runs
DB::query("DELETE FROM pages WHERE slug LIKE 'test-page-%'");

// Mock the active site session
$_SESSION['user_id'] = 'test-user-id'; // Allow admin behaviors
$_SERVER['HTTP_HOST'] = $domain;

// Generate unique test slugs
$rand = bin2hex(random_bytes(4));
$slugA = 'test-page-a-' . $rand;
$slugB = 'test-page-b-' . $rand;
$slugC = 'test-page-c-' . $rand;

// 2. Create three temporary pages with explicitly defined slugs
echo "  Creating test pages...\n";
$pageA = new Page([
    'title' => 'Test Page A ' . $rand,
    'slug' => $slugA,
    'status' => 'draft',
    'site_id' => $siteId
]);
$idA = $pageA->save();

$pageB = new Page([
    'title' => 'Test Page B ' . $rand,
    'slug' => $slugB,
    'status' => 'draft',
    'site_id' => $siteId
]);
$idB = $pageB->save();

$pageC = new Page([
    'title' => 'Test Page C ' . $rand,
    'slug' => $slugC,
    'status' => 'draft',
    'site_id' => $siteId
]);
$idC = $pageC->save();

assert_test(!empty($idA) && !empty($idB) && !empty($idC), "Created and saved test pages A, B, and C with UUIDs and slugs");

// 3. Test IsOrderable detection
$traits = class_uses(Page::class);
$isOrderable = isset($traits[\Zero\Models\Traits\IsOrderable::class]) || (method_exists(Page::class, 'isOrderable') && Page::isOrderable());
assert_test($isOrderable, "Page model successfully detects IsOrderable trait support");

// 4. Test reordering logic via IsOrderable::reorder
echo "  Reordering pages: Page C (1st), Page A (2nd), Page B (3rd)...\n";
$reorderResult = Page::reorder([$idC, $idA, $idB]);
assert_test($reorderResult, "Page::reorder executed successfully");

// Fetch from database and verify precedence values
$dbPageC = Page::find($idC);
$dbPageA = Page::find($idA);
$dbPageB = Page::find($idB);

assert_test((int)$dbPageC->precedence === 10, "Page C (1st in list) has precedence of 10 (got: {$dbPageC->precedence})");
assert_test((int)$dbPageA->precedence === 20, "Page A (2nd in list) has precedence of 20 (got: {$dbPageA->precedence})");
assert_test((int)$dbPageB->precedence === 30, "Page B (3rd in list) has precedence of 30 (got: {$dbPageB->precedence})");

// 5. Clean up from database
echo "  Cleaning up test pages...\n";
$dbPageA->forceDelete();
$dbPageB->forceDelete();
$dbPageC->forceDelete();

assert_test(Page::find($idA) === null, "Test Page A permanently deleted");
assert_test(Page::find($idB) === null, "Test Page B permanently deleted");
assert_test(Page::find($idC) === null, "Test Page C permanently deleted");

echo "Precedence reordering component tests completed.\n\n";
