<?php
// tests/HasSlugTest.php
// Unit and integration tests for HasSlug Active Record Trait (Zero\Models\Traits\HasSlug)

require_once __DIR__ . '/bootstrap.php';

use Zero\Models\Page;
use Zero\Database\DB;
use Zero\Core\App;

echo "=== HasSlug Component Tests ===\n";

// 1. Setup active site tenant and mock it in App
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
DB::query("DELETE FROM pages WHERE slug LIKE 'test-slug-%'");

// 2. Create mock published and draft page records
$rand = bin2hex(random_bytes(4));
$publishedSlug = 'test-slug-pub-' . $rand;
$draftSlug = 'test-slug-draft-' . $rand;

$pubPage = new Page([
    'title' => 'Published Slug Test',
    'slug' => $publishedSlug,
    'status' => 'published',
    'site_id' => $siteId
]);
$pubPage->save();

$draftPage = new Page([
    'title' => 'Draft Slug Test',
    'slug' => $draftSlug,
    'status' => 'draft',
    'site_id' => $siteId
]);
$draftPage->save();

try {
    // 3. Test findBySlug as an Admin
    echo "Testing slug resolution for Admin session...\n";
    $_SESSION['user_id'] = 'mock-admin-session-id'; // Set admin session
    
    $foundPub = Page::findBySlug($publishedSlug);
    assert_test($foundPub instanceof Page && $foundPub->slug === $publishedSlug, "Admin can retrieve published page by slug");

    $foundDraft = Page::findBySlug($draftSlug);
    assert_test($foundDraft instanceof Page && $foundDraft->slug === $draftSlug, "Admin can retrieve draft page by slug");

    // 4. Test findBySlug as a Guest (Public)
    echo "Testing slug resolution for Guest (Public) session...\n";
    unset($_SESSION['user_id']); // Clear session to mimic a guest visitor

    $foundPubGuest = Page::findBySlug($publishedSlug);
    assert_test($foundPubGuest instanceof Page && $foundPubGuest->slug === $publishedSlug, "Guests are allowed to retrieve published pages by slug");

    $foundDraftGuest = Page::findBySlug($draftSlug);
    assert_test($foundDraftGuest === null, "Guests are strictly BLOCKED from retrieving draft pages by slug (returns null)");

    // 5. Test Slug Cascading updates for children
    echo "Testing hierarchical slug cascading updates on parent rename...\n";
    $_SESSION['user_id'] = 'mock-admin-session-id';

    // Create parent page
    $parentSlug = 'test-slug-parent-' . $rand;
    $parentPage = new Page([
        'title' => 'Parent Page',
        'slug' => $parentSlug,
        'status' => 'published',
        'site_id' => $siteId
    ]);
    $parentPage->save();

    // Create child and grandchild pages
    $childSlug = $parentSlug . '/child';
    $grandchildSlug = $parentSlug . '/child/grandchild';

    $childPage = new Page([
        'title' => 'Child Page',
        'slug' => $childSlug,
        'status' => 'published',
        'site_id' => $siteId
    ]);
    $childPage->save();

    $grandchildPage = new Page([
        'title' => 'Grandchild Page',
        'slug' => $grandchildSlug,
        'status' => 'published',
        'site_id' => $siteId
    ]);
    $grandchildPage->save();

    // Now, rename parent slug!
    $newParentSlug = 'test-slug-new-parent-' . $rand;
    $parentPage->slug = $newParentSlug;
    $parentPage->save(); // This triggers our dynamic cascade update inside Page::save()!

    // Verify that child and grandchild slugs were dynamically updated in database!
    $updatedChild = Page::find($childPage->id);
    $expectedChildSlug = $newParentSlug . '/child';
    assert_test($updatedChild && $updatedChild->slug === $expectedChildSlug, "Renaming parent page slug successfully cascades to nested children records");

    $updatedGrandchild = Page::find($grandchildPage->id);
    $expectedGrandchildSlug = $newParentSlug . '/child/grandchild';
    assert_test($updatedGrandchild && $updatedGrandchild->slug === $expectedGrandchildSlug, "Renaming parent page slug successfully cascades recursively to nested grandchildren records");

    // 6. Test auto slug generation for homepages
    echo "Testing homepage empty slug generation...\n";
    $homePage1 = new Page([
        'title' => 'Home',
        'status' => 'published',
        'site_id' => $siteId
    ]);
    $homePage1->save();
    assert_test($homePage1->slug === '', "Page titled 'Home' with blank slug_part generates an empty string slug");
    $homePage1->forceDelete(); // Delete to avoid collision for the next test page

    $homePage2 = new Page([
        'title' => 'Welcome',
        'slug_part' => 'home',
        'status' => 'published',
        'site_id' => $siteId
    ]);
    $homePage2->save();
    assert_test($homePage2->slug === '', "Page with slug_part explicitly set to 'home' generates an empty string slug");

    $homePage2->forceDelete();

    // Clean up
    $parentPage->forceDelete();
    $childPage->forceDelete();
    $grandchildPage->forceDelete();

} finally {
    // Teardown
    $_SESSION['user_id'] = 'mock-admin-session-id';
    $pubPage->forceDelete();
    $draftPage->forceDelete();
    unset($_SESSION['user_id']);
}

echo "HasSlug component tests completed.\n\n";
