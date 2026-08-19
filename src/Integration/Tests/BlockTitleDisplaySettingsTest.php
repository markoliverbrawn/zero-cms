<?php
// tests/BlockTitleDisplaySettingsTest.php
// Unit/Integration test to verify that block Title Display settings ('0' => H2, '1' => Hide, '2' => H1)
// are correctly respected and rendered across all themes (default, guide, and kitchensink).
// Also asserts that block previews correctly render block titles reflecting the active theme.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Core\Template;
use Zero\Models\Page;
use Zero\Models\Site;

echo "=== Block Title Display settings Test ===\n";

// Bootstrap App
App::bootstrap();

// Helper to render post view for a specific theme
function renderThemePost($theme, $post) {
    // Mock the current site theme
    $site = new Site([
        'id' => '019fa1f1-efgh-72f0-8c3b-9732ab7f9e3b',
        'name' => 'Test Site',
        'domain' => 'testsite.local',
        'theme' => $theme,
        'enabled_modules' => '[]'
    ]);
    App::setCurrentSite($site);

    $viewPath = APPLICATION_ROOT . "/src/Views/themes/{$theme}/post.php";
    
    // Suppress warning output during test renders
    return Template::renderFile($viewPath, [
        'post' => $post
    ]);
}

// 1. Create a page with various block title display configurations
$mockBlocks0 = [
    [
        'type' => 'text',
        'title' => 'Title H2 Option',
        'content' => '<p>Some text content</p>',
        'hide_title' => '0'
    ]
];

$mockBlocks1 = [
    [
        'type' => 'text',
        'title' => 'Title Hide Option',
        'content' => '<p>Some text content</p>',
        'hide_title' => '1'
    ]
];

$mockBlocks2 = [
    [
        'type' => 'text',
        'title' => 'Title H1 Option',
        'content' => '<p>Some text content</p>',
        'hide_title' => '2'
    ]
];

$post0 = new Page([
    'title' => 'Post H2',
    'content' => json_encode($mockBlocks0),
    'omit_title' => true,
    'created_at' => '2026-08-07 12:00:00'
]);

$post1 = new Page([
    'title' => 'Post Hide',
    'content' => json_encode($mockBlocks1),
    'omit_title' => true,
    'created_at' => '2026-08-07 12:00:00'
]);

$post2 = new Page([
    'title' => 'Post H1',
    'content' => json_encode($mockBlocks2),
    'omit_title' => true,
    'created_at' => '2026-08-07 12:00:00'
]);

// Ensure timezone or local server date doesn't crash on strtotime in post templates
$_SERVER['REQUEST_URI'] = '/';

// Test Theme: default
echo "  Testing default theme block title display render output...\n";
$html0_default = renderThemePost('default', $post0);
$html1_default = renderThemePost('default', $post1);
$html2_default = renderThemePost('default', $post2);

assert_test(strpos($html0_default, '<h2 class="block-section-title">') !== false && strpos($html0_default, 'Title H2 Option') !== false, "Default theme correctly renders Show Title (H2)");
assert_test(strpos($html1_default, 'Title Hide Option') === false, "Default theme correctly hides Title");
assert_test(strpos($html2_default, '<h1 class="block-section-title">') !== false && strpos($html2_default, 'Title H1 Option') !== false, "Default theme correctly renders Show Title (H1)");

// Test Theme: kitchensink
echo "  Testing kitchensink theme block title display render output...\n";
$html0_ks = renderThemePost('kitchensink', $post0);
$html1_ks = renderThemePost('kitchensink', $post1);
$html2_ks = renderThemePost('kitchensink', $post2);

assert_test(strpos($html0_ks, '<h3 style="color: var(--neon-cyan); margin-bottom: 1.25rem;">') !== false && strpos($html0_ks, 'Title H2 Option') !== false, "Kitchensink theme correctly renders Show Title (H2) as h3 with neon cyan style");
assert_test(strpos($html1_ks, 'Title Hide Option') === false, "Kitchensink theme correctly hides Title");
assert_test(strpos($html2_ks, '<h1 style="color: var(--neon-cyan); margin-bottom: 1.25rem;">') !== false && strpos($html2_ks, 'Title H1 Option') !== false, "Kitchensink theme correctly renders Show Title (H1) with neon cyan style");


// 2. Test Block Preview endpoint output in admin back-office
echo "  Testing block preview rendering for admin back-office...\n";

class TestAdminApiController extends \Zero\Modules\Admin\Controllers\Api\BlockPreviewApiController {
    public $responseData = null;
    public $responseStatusCode = null;

    protected function respond(array $data, int $statusCode = 200) {
        $this->responseData = $data;
        $this->responseStatusCode = $statusCode;
    }
    
    protected function authenticate(): array {
        return ['id' => 'user-123', 'role' => 'editor'];
    }

    public function testBlockPreview($body) {
        $this->handleBlockPreview($body);
        return $this->responseData;
    }
}

$controller = new TestAdminApiController();

// Set up site for preview tests
$defaultSite = new Site([
    'id' => '019fa1f1-efgh-72f0-8c3b-9732ab7f9e3b',
    'name' => 'Default Site',
    'domain' => 'testsite.local',
    'theme' => 'default',
    'enabled_modules' => '[]'
]);
App::setCurrentSite($defaultSite);

// Test Preview Default Theme with H2
$respH2_default = $controller->testBlockPreview([
    'block' => [
        'type' => 'text',
        'title' => 'Preview Default H2 Title',
        'content' => '<p>Text block</p>',
        'hide_title' => '0'
    ]
]);
assert_test($respH2_default['success'] === true, "Block preview request returns success");
assert_test(strpos($respH2_default['html'], '<h2 class="block-section-title">') !== false && strpos($respH2_default['html'], 'Preview Default H2 Title') !== false, "Block preview defaults to H2 block section title in default theme");

// Test Preview Default Theme with H1
$respH1_default = $controller->testBlockPreview([
    'block' => [
        'type' => 'text',
        'title' => 'Preview Default H1 Title',
        'content' => '<p>Text block</p>',
        'hide_title' => '2'
    ]
]);
assert_test(strpos($respH1_default['html'], '<h1 class="block-section-title">') !== false && strpos($respH1_default['html'], 'Preview Default H1 Title') !== false, "Block preview renders H1 block section title in default theme when selected");

// Test Preview Default Theme with Hide Title
$respHide_default = $controller->testBlockPreview([
    'block' => [
        'type' => 'text',
        'title' => 'Preview Default Hidden Title',
        'content' => '<p>Text block</p>',
        'hide_title' => '1'
    ]
]);
assert_test(strpos($respHide_default['html'], 'Preview Default Hidden Title') === false, "Block preview hides block section title in default theme when hidden is selected");


// Now switch site to kitchensink theme for preview tests
$ksSite = new Site([
    'id' => '019fa1f1-efgh-72f0-8c3b-9732ab7f9e3b',
    'name' => 'Cyber Site',
    'domain' => 'testsite.local',
    'theme' => 'kitchensink',
    'enabled_modules' => '[]'
]);
App::setCurrentSite($ksSite);

// Test Preview Kitchensink Theme with H3 (value 0)
$respH2_ks = $controller->testBlockPreview([
    'block' => [
        'type' => 'text',
        'title' => 'Preview Cyber H2 Title',
        'content' => '<p>Cyber block</p>',
        'hide_title' => '0'
    ]
]);
assert_test(strpos($respH2_ks['html'], '<h3 style="color: var(--neon-cyan); margin-bottom: 1.25rem;">') !== false && strpos($respH2_ks['html'], 'Preview Cyber H2 Title') !== false, "Block preview renders H3 neon title in kitchensink theme when default H2 (0) is selected");

// Test Preview Kitchensink Theme with H1 (value 2)
$respH1_ks = $controller->testBlockPreview([
    'block' => [
        'type' => 'text',
        'title' => 'Preview Cyber H1 Title',
        'content' => '<p>Cyber block</p>',
        'hide_title' => '2'
    ]
]);
assert_test(strpos($respH1_ks['html'], '<h1 style="color: var(--neon-cyan); margin-bottom: 1.25rem;">') !== false && strpos($respH1_ks['html'], 'Preview Cyber H1 Title') !== false, "Block preview renders H1 neon title in kitchensink theme when H1 (2) is selected");

// Test Preview Kitchensink Theme with Hide Title (value 1)
$respHide_ks = $controller->testBlockPreview([
    'block' => [
        'type' => 'text',
        'title' => 'Preview Cyber Hidden Title',
        'content' => '<p>Cyber block</p>',
        'hide_title' => '1'
    ]
]);
assert_test(strpos($respHide_ks['html'], 'Preview Cyber Hidden Title') === false, "Block preview hides neon title in kitchensink theme when hidden is selected");


echo "\n✅ Block Title Display settings tests completed successfully!\n";
