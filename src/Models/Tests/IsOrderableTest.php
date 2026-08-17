<?php
// tests/IsOrderableTest.php
// Unit and integration tests for IsOrderable Active Record Trait (Zero\Models\Traits\IsOrderable)

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Models\Page;
use Zero\Database\DB;
use Zero\Core\App;

echo "=== IsOrderable Component Tests ===\n";

// 1. Resolve site ID for testing and mock it in App
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

// Clean up old stray test pages
DB::query("DELETE FROM pages WHERE slug LIKE 'test-order-%'");

// 2. Setup mock orderable page entries
$rand = bin2hex(random_bytes(4));
$p1 = new Page(['title' => 'Page 1', 'slug' => 'test-order-1-' . $rand, 'status' => 'draft', 'site_id' => $siteId]);
$id1 = $p1->save();

$p2 = new Page(['title' => 'Page 2', 'slug' => 'test-order-2-' . $rand, 'status' => 'draft', 'site_id' => $siteId]);
$id2 = $p2->save();

$p3 = new Page(['title' => 'Page 3', 'slug' => 'test-order-3-' . $rand, 'status' => 'draft', 'site_id' => $siteId]);
$id3 = $p3->save();

try {
    // 3. Verify trait isOrderable reports true
    assert_test(Page::isOrderable() === true, "Page model correctly identifies itself as orderable");

    // 4. Perform reordering (P3 first, then P1, then P2)
    echo "Executing reordering (P3, P1, P2)...\n";
    $_SESSION['user_id'] = 'mock-admin';
    $result = Page::reorder([$id3, $id1, $id2]);
    assert_test($result === true, "Reorder transaction completed successfully");

    // 5. Fetch updated pages and verify precedence values
    $fetch3 = Page::find($id3);
    $fetch1 = Page::find($id1);
    $fetch2 = Page::find($id2);

    assert_test((int)$fetch3->precedence === 10, "First row in list (Page 3) is assigned a precedence score of 10");
    assert_test((int)$fetch1->precedence === 20, "Second row in list (Page 1) is assigned a precedence score of 20");
    assert_test((int)$fetch2->precedence === 30, "Third row in list (Page 2) is assigned a precedence score of 30");

} finally {
    // Teardown and delete records
    $p1->forceDelete();
    $p2->forceDelete();
    $p3->forceDelete();
    unset($_SESSION['user_id']);
}

echo "IsOrderable component tests completed.\n\n";
