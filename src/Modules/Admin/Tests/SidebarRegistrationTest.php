<?php
/**
 * tests/SidebarRegistrationTest.php
 *
 * Comprehensive integration test suite for the dynamic admin sidebar navigation system.
 * Verifies registrations of sections, links, ordering/precedence, and visibility filtering rules.
 *
 * @package Zero\Tests
 */

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Models\Site;
use Zero\Models\User;

echo "=== Admin Sidebar Navigation Registration Tests ===\n";

// Ensure App is bootstrapped (which loads defaults)
App::bootstrap();

// 1. Verify default sections and links are loaded correctly
$sections = App::getAdminSidebarSections();

assert_test(isset($sections['dashboard']), "Default 'dashboard' sidebar section is registered");
assert_test(isset($sections['content']), "Default 'content' sidebar section is registered");
assert_test(isset($sections['security']), "Default 'security' sidebar section is registered");

// Verify links under content
$contentLinks = $sections['content']['links'] ?? [];
$hasPages = false;
$hasFiles = false;
foreach ($contentLinks as $link) {
    if ($link['url'] === '/admin/list/pages') {
        $hasPages = true;
    }
    if ($link['url'] === '/admin/list/files') {
        $hasFiles = true;
    }
}
assert_test($hasPages, "Manage Pages link is registered under content section");
assert_test($hasFiles, "Media Library link is registered under content section");

// 2. Register custom section with precedence and verify sorting order
App::registerAdminSidebarSection('test_beta', [
    'title' => 'Beta Section',
    'icon' => 'zap',
    'precedence' => 150
]);

$sortedSections = App::getAdminSidebarSections();
$sectionKeys = array_keys($sortedSections);

// Find index of 'content', 'test_beta', and 'security'
$contentIdx = array_search('content', $sectionKeys);
$betaIdx = array_search('test_beta', $sectionKeys);
$securityIdx = array_search('security', $sectionKeys);

if ($contentIdx !== false && $betaIdx !== false && $securityIdx !== false) {
    assert_test($betaIdx > $contentIdx, "Custom beta section is sorted after 'content' section");
    assert_test($betaIdx < $securityIdx, "Custom beta section is sorted before 'security' section");
} else {
    assert_test(false, "Sections sorting check skipped (keys missing)");
}

// 3. Register custom link with precedence and verify link sorting
App::registerAdminSidebarLink('content', [
    'title' => 'Alpha Content Link',
    'url' => '/admin/alpha',
    'icon' => 'file',
    'precedence' => 5 // Smaller than default pages (40)
]);

$sectionsWithCustomLink = App::getAdminSidebarSections();
$contentLinksSorted = $sectionsWithCustomLink['content']['links'] ?? [];

assert_test(!empty($contentLinksSorted), "Content links are not empty after registering custom link");
assert_test($contentLinksSorted[0]['url'] === '/admin/alpha', "Alpha content link with lower precedence is sorted first");

// 4. Test visibility filtering by role (super_admin vs editor) and module active state
$superAdminItem = [
    'title' => 'Super Admin Section',
    'super_admin_only' => true
];
$editorItem = [
    'title' => 'Editor Section',
    'super_admin_only' => false
];
$shopDependentItem = [
    'title' => 'Shop Management',
    'module_dependency' => 'shop'
];

$siteWithShop = new Site([
    'id' => 'site-with-shop',
    'name' => 'Site With Shop',
    'domain' => 'shop.zero',
    'theme' => 'default',
    'enabled_modules' => '["shop"]'
]);

$siteWithoutShop = new Site([
    'id' => 'site-without-shop',
    'name' => 'Site Without Shop',
    'domain' => 'plain.zero',
    'theme' => 'default',
    'enabled_modules' => '[]'
]);

// Use Reflection to mock currentUser role in App
$reflector = new ReflectionClass(App::class);
$property = $reflector->getProperty('currentUser');
$property->setAccessible(true);

// Case A: User is Guest/null
$property->setValue(null, null);
assert_test(!App::isSidebarItemVisible($superAdminItem, $siteWithShop), "Guest user cannot see super_admin_only items");
assert_test(App::isSidebarItemVisible($editorItem, $siteWithShop), "Guest user can see standard items");

// Case B: User is Standard Editor
$editorUser = new User([
    'id' => 'editor-id',
    'username' => 'editor_user',
    'role' => 'editor'
]);
$property->setValue(null, $editorUser);
assert_test(!App::isSidebarItemVisible($superAdminItem, $siteWithShop), "Editor user cannot see super_admin_only items");
assert_test(App::isSidebarItemVisible($editorItem, $siteWithShop), "Editor user can see standard items");
assert_test(App::isSidebarItemVisible($shopDependentItem, $siteWithShop), "Editor user can see shop items if shop module is enabled on the active site");
assert_test(!App::isSidebarItemVisible($shopDependentItem, $siteWithoutShop), "Editor user cannot see shop items if shop module is disabled on the active site");

// Case C: User is Super Admin
$superAdminUser = new User([
    'id' => 'superadmin-id',
    'username' => 'super_admin_user',
    'role' => 'super_admin'
]);
$property->setValue(null, $superAdminUser);
assert_test(App::isSidebarItemVisible($superAdminItem, $siteWithShop), "Super Admin user can see super_admin_only items");
assert_test(App::isSidebarItemVisible($shopDependentItem, $siteWithShop), "Super Admin user can see shop items if shop module is enabled on the active site");
assert_test(!App::isSidebarItemVisible($shopDependentItem, $siteWithoutShop), "Super Admin user cannot see shop items if shop module is disabled on the active site");

echo "✅ All Sidebar Navigation Registration Tests Passed Successfully!\n";
exit(0);