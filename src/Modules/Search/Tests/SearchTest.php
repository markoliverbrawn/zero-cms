<?php
// tests/SearchTest.php
// Unit tests for the new global site Search module & SearchService

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Models\Page;
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
    VALUES (?, 'Search Test Site', 'searchtest.zero', 'default', '[\"site-search\"]', NOW(), NOW())
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

assert_test(!empty($page1->id) && !empty($page2->id) && !empty($page3->id), "Successfully seeded mock search targets");

// Verify Search Services and Decoupled Registrations
echo "Verifying registered searchable providers...\n";
$searchables = SearchService::getSearchables();
assert_test(isset($searchables['Zero\Models\Page']), "Page model registered with SearchService");

// Execute Search and Assert Matches
echo "Testing global site search queries...\n";

// Query 1: "Tutorial" (should match Page 1, but NOT Page 2 [excluded] and NOT Page 3 [draft])
$searchData = SearchService::search("Tutorial");
$results = $searchData['results'] ?? [];
assert_test(count($results) === 1, "Query 'Tutorial' returns exactly 1 result (got: " . count($results) . ")");
assert_test($results[0]['id'] === $page1->id, "Matched record is the Awesome Zero CMS Tutorial page");
assert_test($results[0]['type_label'] === 'Page', "Result type label is correctly set to 'Page'");
assert_test($results[0]['url'] === '/awesome-tutorial', "Result URL correctly resolved via getFrontendUrl()");

// Query 2: "Secret" (matches nothing because Page 2, which contains it, is excluded from search!)
$searchData = SearchService::search("Secret");
$results = $searchData['results'] ?? [];
assert_test(count($results) === 0, "Query 'Secret' returns 0 results due to strict exclude_from_search checks (got: " . count($results) . ")");

// Query 3: Empty/blank
$searchData = SearchService::search(" ");
$results = $searchData['results'] ?? [];
assert_test(count($results) === 0, "Empty/blank search queries return 0 results safely");

// Cleanup and Restore
echo "Cleaning up search test records...\n";
$page1->forceDelete();
$page2->forceDelete();
$page3->forceDelete();

DB::query("DELETE FROM sites WHERE id = ?", [$mockSiteId]);

echo "Global Site Search module component tests completed successfully!\n\n";
