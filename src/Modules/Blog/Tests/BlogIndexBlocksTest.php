<?php
// tests/BlogIndexBlocksTest.php
// Integration test to verify that the blog index page supports page builder blocks across themes.

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Core\Template;
use Zero\Models\Page;
use Zero\Models\Site;
use Zero\Database\DB;

// Bootstrap App to register blocks and discover modules
App::bootstrap();

echo "=== Blog Index Blocks Support Integration Tests ===\n";

// 1. Setup mock environment
$siteRow = DB::query("SELECT id FROM sites LIMIT 1")->fetch();
$siteId = $siteRow['id'] ?? \Zero\Support\Security::uuidv7();

$mockSite = new Site([
    'id' => $siteId,
    'name' => 'Test Blog Site',
    'domain' => 'localhost',
    'theme' => 'default'
]);

// Set active site in App class
try {
    $appReflector = new ReflectionClass(App::class);
    $siteProp = $appReflector->getProperty('currentSite');
    $siteProp->setAccessible(true);
    $siteProp->setValue(null, $mockSite);
} catch (Exception $e) {
    echo "Reflection helper error: " . $e->getMessage() . "\n";
}

// 2. Create a mock blog Page record containing serialized page builder blocks
$blocksPayload = [
    [
        'type' => 'baseline',
        'title' => 'Blog Hero baseline',
        'content' => 'Welcome to our baseline publication hub.'
    ],
    [
        'type' => 'text',
        'title' => 'Special notice',
        'content' => 'Important announcements live here.'
    ]
];

$mockBlogPage = new Page([
    'title' => 'Publications Hub',
    'slug' => 'blog-index-test',
    'content' => json_encode($blocksPayload),
    'status' => 'published'
]);

// 3. Define mock publications list
$mockPublications = [
    (object)[
        'title' => 'First Article',
        'slug' => 'first-article',
        'created_at' => gmdate('Y-m-d H:i:s'),
        'summary' => 'This is a summary of the first article.'
    ],
    (object)[
        'title' => 'Second Article',
        'slug' => 'second-article',
        'created_at' => gmdate('Y-m-d H:i:s'),
        'summary' => 'This is a summary of the second article.'
    ]
];

$mockPagination = [
    'data' => $mockPublications,
    'total_pages' => 1,
    'current_page' => 1
];

// 4. Test rendering across all 2 visual themes
$themes = ['default', 'kitchensink'];

foreach ($themes as $theme) {
    echo "  Testing block rendering in theme: {$theme}...\n";
    
    // Set theme on site dynamically
    $mockSite->theme = $theme;
    
    $viewPath = APPLICATION_ROOT . "/src/Views/themes/{$theme}/blog.php";
    
    assert_test(file_exists($viewPath), "Blog index template exists for theme '{$theme}'");
    
    // Render the view file
    $renderedOutput = Template::renderFile($viewPath, [
        'post' => $mockBlogPage,
        'posts' => $mockPublications,
        'pagination' => $mockPagination
    ]);
    
    // Check that page builder block titles and content are rendered correctly
    assert_test(strpos($renderedOutput, 'Blog Hero baseline') !== false, "Renders 'baseline' block title in '{$theme}' theme");
    assert_test(strpos($renderedOutput, 'Welcome to our baseline publication hub.') !== false, "Renders 'baseline' block content in '{$theme}' theme");
    
    assert_test(strpos($renderedOutput, 'Special notice') !== false, "Renders 'text' block title in '{$theme}' theme");
    assert_test(strpos($renderedOutput, 'Important announcements live here.') !== false, "Renders 'text' block content in '{$theme}' theme");
    
    // Check that standard blog publication list is also rendered correctly alongside blocks
    assert_test(strpos($renderedOutput, 'First Article') !== false, "Renders publication list titles in '{$theme}' theme");
    assert_test(strpos($renderedOutput, 'This is a summary of the second article.') !== false, "Renders publication list summaries in '{$theme}' theme");
}

echo "Blog index blocks support integration tests completed successfully.\n\n";
