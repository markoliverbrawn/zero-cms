<?php
/**
 * tests/PagesListingTitleStripTagsTest.php
 *
 * Integration test suite verifying that HTML tags are stripped from columns configured as rich_text.
 *
 * @package Zero\Tests
 */

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Core\Template;

echo "=== Pages Listing Title Strip Tags Tests ===\n";

// Ensure App is bootstrapped
App::bootstrap();

// 1. Setup mock records for rendering
$record1 = (object)[
    'id' => 'abc-111',
    'title' => '<strong>My Bold Page Title</strong>',
    'slug' => 'bold-page'
];

$record2 = (object)[
    'id' => 'abc-222',
    'title' => '<em>Another <u>Formatted</u> Page Title</em>',
    'slug' => 'formatted-page'
];

$configRichText = [
    'title' => [
        'type' => 'rich_text',
        'label' => 'Title',
        'listDisplay' => true
    ],
    'slug' => [
        'type' => 'text',
        'label' => 'Slug',
        'listDisplay' => true
    ]
];

$configStandardText = [
    'title' => [
        'type' => 'text',
        'label' => 'Title',
        'listDisplay' => true
    ],
    'slug' => [
        'type' => 'text',
        'label' => 'Slug',
        'listDisplay' => true
    ]
];

// Compile view path
$viewPath = APPLICATION_ROOT . '/src/Modules/Admin/Views/model/list.php';

// Render listing for model with rich_text type (e.g. pages)
echo "Testing tag stripping for columns configured as rich_text...\n";
$renderedPages = Template::renderFile($viewPath, [
    'modelName' => 'pages',
    'records' => [$record1, $record2],
    'config' => $configRichText,
    'sort' => 'title',
    'order' => 'asc',
    'csrf' => 'mock-csrf-token',
    'isOrderable' => false,
    'status' => 'active'
]);

// Assertions for rich_text configured column
assert_test(strpos($renderedPages, 'My Bold Page Title') !== false, "Correctly strips bold tag from page title");
assert_test(strpos($renderedPages, '<strong>My Bold Page Title</strong>') === false, "Bold HTML tags are not present in rendered output for pages listing");

assert_test(strpos($renderedPages, 'Another Formatted Page Title') !== false, "Correctly strips em and u tags from formatted page title");
assert_test(strpos($renderedPages, '<em>Another <u>Formatted</u> Page Title</em>') === false, "Formatted HTML tags are not present in rendered output for pages listing");

// Render listing for standard text type to verify that tags are preserved
echo "Testing tag preservation for columns configured as standard text...\n";
$renderedBlog = Template::renderFile($viewPath, [
    'modelName' => 'blog_posts',
    'records' => [$record1],
    'config' => $configStandardText,
    'sort' => 'title',
    'order' => 'asc',
    'csrf' => 'mock-csrf-token',
    'isOrderable' => false,
    'status' => 'active'
]);

// Assertions for standard text column
assert_test(strpos($renderedBlog, '&lt;strong&gt;My Bold Page Title&lt;/strong&gt;') !== false, "Standard text column keeps original tags safely escaped (no strip_tags)");

echo "All Pages Listing Title Strip Tags Tests completed successfully!\n";
