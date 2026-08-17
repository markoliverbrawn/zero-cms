<?php
// tests/SearchTest.php
// Unit tests for the new global site Search module & SearchService

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Models\Page;
use Zero\Modules\Blog\Models\Post;
use Zero\Modules\Shop\Models\Product;
use Zero\Modules\Search\Services\SearchService;
use Zero\Support\Security;

echo "=== Global Site Search Module Component Tests ===\n";

// Clear original bootstrapped static properties to allow clean re-bootstrap
$refApp = new \ReflectionClass('Zero\Core\App');
$propBoot = $refApp->getProperty('bootstrapped');
$propBoot->setAccessible(true);
$propBoot->setValue(null, false);

$propSite = $refApp->getProperty('currentSite');
$propSite->setAccessible(true);
$propSite->setValue(null, null);

// Mock request headers
$_SERVER['HTTP_HOST'] = 'searchtest.zero';

// Insert mock site for isolated integration testing
$mockSiteId = Security::uuidv7();
DB::query("
    INSERT INTO sites (id, name, domain, theme, enabled_modules, created_at, updated_at)
    VALUES (?, 'Search Test Site', 'searchtest.zero', 'default', '[\"blog\", \"shop\", \"site-search\"]', NOW(), NOW())
", [$mockSiteId]);

App::bootstrap();

$siteId = App::getCurrentSiteId();
assert_test($siteId === $mockSiteId, "Correctly bootstraps mock search site environment");

// Create mock data
echo "Seeding test database records...\n";

// 1. Core Page (Standard)
$page1 = new Page([
    'title' => 'Awesome Zero CMS Tutorial',
    'slug' => 'awesome-tutorial',
    'content' => json_encode([['type' => 'text', 'content' => '<p>Learn how to use Zero CMS effectively in this quick tutorial.</p>']]),
    'summary' => 'A nice Zero CMS tutorial page.',
    'status' => 'published',
    'exclude_from_search' => 0
]);
$page1->save();

// 2. Core Page (Excluded from search)
$page2 = new Page([
    'title' => 'Hidden Secret Admin Area',
    'slug' => 'secret-page',
    'content' => json_encode([['type' => 'text', 'content' => '<p>Highly sensitive secret Tutorial details.</p>']]),
    'summary' => 'Secret area.',
    'status' => 'published',
    'exclude_from_search' => 1
]);
$page2->save();

// 3. Core Page (Draft status)
$page3 = new Page([
    'title' => 'Draft Tutorial Page',
    'slug' => 'draft-tutorial',
    'content' => json_encode([['type' => 'text', 'content' => '<p>Work in progress Tutorial page.</p>']]),
    'summary' => 'Draft tutorial.',
    'status' => 'draft',
    'exclude_from_search' => 0
]);
$page3->save();

// 4. Blog Post (Standard)
$post1 = new Post([
    'title' => 'Ultimate PHP Programming Guide',
    'slug' => 'php-guide',
    'summary' => 'A comprehensive guide on coding pure, zero-dependency PHP applications.',
    'content' => '[]',
    'status' => 'published',
    'exclude_from_search' => 0
]);
$post1->save();

// 5. Blog Post (Excluded from search)
$post2 = new Post([
    'title' => 'Old Legacy PHP Hacks',
    'slug' => 'old-php',
    'summary' => 'Some old legacy programming hacks that we do not want searchable.',
    'content' => '[]',
    'status' => 'published',
    'exclude_from_search' => 1
]);
$post2->save();

// 6. Shop Product (Standard)
$product1 = new Product([
    'title' => 'Luxe Gaming Keyboard',
    'slug' => 'luxe-keyboard',
    'sku' => 'LUXE-KEY-100',
    'description' => 'A sleek, mechanical high-contrast keyboard designed for purist developers.',
    'price' => 149.99,
    'status' => 'published',
    'exclude_from_search' => 0
]);
$product1->save();

// 7. Shop Product (Excluded from search)
$product2 = new Product([
    'title' => 'Secret Luxe Gaming Mouse',
    'slug' => 'luxe-mouse',
    'sku' => 'LUXE-MS-200',
    'description' => 'A sleek, gaming mouse designed for developers.',
    'price' => 79.99,
    'status' => 'published',
    'exclude_from_search' => 1
]);
$product2->save();

assert_test(!empty($page1->id) && !empty($page2->id) && !empty($post1->id) && !empty($product1->id), "Successfully seeded mock search targets");

// Verify Search Services and Decoupled Registrations
echo "Verifying registered searchable providers...\n";
$searchables = SearchService::getSearchables();
assert_test(isset($searchables['Zero\Models\Page']), "Page model registered with SearchService");
assert_test(isset($searchables['Zero\Modules\Blog\Models\Post']), "Blog Post model registered with SearchService");
assert_test(isset($searchables['Zero\Modules\Shop\Models\Product']), "Shop Product model registered with SearchService");

// Execute Search and Assert Matches
echo "Testing global site search queries...\n";

// Query 1: "Tutorial" (should match Page 1, but NOT Page 2 [excluded] and NOT Page 3 [draft])
$searchData = SearchService::search("Tutorial");
$results = $searchData['results'] ?? [];
assert_test(count($results) === 1, "Query 'Tutorial' returns exactly 1 result (got: " . count($results) . ")");
assert_test($results[0]['id'] === $page1->id, "Matched record is the Awesome Zero CMS Tutorial page");
assert_test($results[0]['type_label'] === 'Page', "Result type label is correctly set to 'Page'");
assert_test($results[0]['url'] === '/awesome-tutorial', "Result URL correctly resolved via getFrontendUrl()");

// Query 2: "PHP" (should match Blog Post 1, but NOT Post 2 [excluded])
$searchData = SearchService::search("PHP");
$results = $searchData['results'] ?? [];
assert_test(count($results) === 1, "Query 'PHP' returns exactly 1 result (got: " . count($results) . ")");
assert_test($results[0]['id'] === $post1->id, "Matched record is the Ultimate PHP Programming Guide post");
assert_test($results[0]['type_label'] === 'Blog Post', "Result type label is correctly set to 'Blog Post'");
assert_test($results[0]['url'] === '/post/php-guide', "Result URL correctly resolved via blog post getFrontendUrl()");

// Query 3: "Keyboard" (should match Product 1)
$searchData = SearchService::search("Keyboard");
$results = $searchData['results'] ?? [];
assert_test(count($results) === 1, "Query 'Keyboard' returns exactly 1 result (got: " . count($results) . ")");
assert_test($results[0]['id'] === $product1->id, "Matched record is the Luxe Gaming Keyboard product");
assert_test($results[0]['type_label'] === 'Product', "Result type label is correctly set to 'Product'");
assert_test($results[0]['url'] === '/shop/product/luxe-keyboard', "Result URL correctly resolved via product getFrontendUrl()");

// Query 4: "Secret" (matches nothing because both Page 2, Post 2, and Product 2 containing secret are excluded from search!)
$searchData = SearchService::search("Secret");
$results = $searchData['results'] ?? [];
assert_test(count($results) === 0, "Query 'Secret' returns 0 results due to strict exclude_from_search checks (got: " . count($results) . ")");

// Query 5: Empty/blank
$searchData = SearchService::search(" ");
$results = $searchData['results'] ?? [];
assert_test(count($results) === 0, "Empty/blank search queries return 0 results safely");

// Cleanup and Restore
echo "Cleaning up search test records...\n";
$page1->forceDelete();
$page2->forceDelete();
$page3->forceDelete();
$post1->forceDelete();
$post2->forceDelete();
$product1->forceDelete();
$product2->forceDelete();

DB::query("DELETE FROM sites WHERE id = ?", [$mockSiteId]);

echo "Global Site Search module component tests completed successfully!\n\n";
