<?php
// tests/EnabledModulesViewTest.php
// Unit/integration tests for the enabled modules field view renderer

require_once __DIR__ . '/bootstrap.php';

use Zero\Core\Template;

echo "=== Enabled Modules View Component Tests ===\n";

$viewFile = APPLICATION_ROOT . '/src/Modules/Admin/Views/fields/modules.php';

assert_test(file_exists($viewFile), "The modules list view file exists on disk");

// Render view with empty value
echo "Testing empty enabled modules list rendering...\n";
$renderedEmpty = Template::renderFile($viewFile, [
    'value' => '[]'
]);
assert_test(strpos($renderedEmpty, 'No modules') !== false, "Displays fallback text 'No modules' when no modules are enabled");

// Render view with blog, security, site-search, and demogenerator modules
echo "Testing rendering with blog, security, site-search, and demogenerator modules...\n";
$modulesPayload = json_encode(['blog', 'security', 'site-search', 'demogenerator']);
$renderedModules = Template::renderFile($viewFile, [
    'value' => $modulesPayload
]);

// Assert that Blog is rendered with its edit-3 icon
assert_test(strpos($renderedModules, 'Blog') !== false, "Correctly renders 'Blog' label");
// Assert presence of the edit-3 path segment
assert_test(strpos($renderedModules, 'M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z') !== false, "Correctly includes SVG icon path for Blog (edit-3)");

// Assert that Security is rendered with its shield icon
assert_test(strpos($renderedModules, 'Security') !== false, "Correctly renders 'Security' label");
// Assert presence of the shield path segment
assert_test(strpos($renderedModules, 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z') !== false, "Correctly includes SVG icon path for Security Module (shield)");

// Assert that Site-Search is rendered with its search icon and "Search" label
assert_test(strpos($renderedModules, 'Search') !== false, "Correctly renders 'Search' label for site-search module");
// Assert presence of the search icon circle path segment
assert_test(strpos($renderedModules, '<circle cx="11" cy="11" r="8"></circle>') !== false, "Correctly includes SVG icon path for Search Module (search)");

// Assert that DemoGenerator is rendered with its zap icon and "Demo Generator" label
assert_test(strpos($renderedModules, 'Demo Generator') !== false, "Correctly renders 'Demo Generator' label for demogenerator module");
// Assert presence of the zap icon polygon path segment
assert_test(strpos($renderedModules, 'points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"') !== false, "Correctly includes SVG icon path for Demo Generator Module (zap)");

echo "Enabled modules view component tests completed successfully!\n\n";
