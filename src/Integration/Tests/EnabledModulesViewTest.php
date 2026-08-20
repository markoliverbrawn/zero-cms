<?php
// tests/EnabledModulesViewTest.php
// Unit/integration tests for the enabled modules field view renderer

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

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

// Render view with security, site-search, and formbuilder modules
echo "Testing rendering with security, site-search, and formbuilder modules...\n";
$modulesPayload = json_encode(['security', 'site-search', 'formbuilder']);
$renderedModules = Template::renderFile($viewFile, [
    'value' => $modulesPayload
]);

// Assert that Security is rendered with its shield icon
assert_test(strpos($renderedModules, 'Security') !== false, "Correctly renders 'Security' label");
// Assert presence of the shield path segment
assert_test(strpos($renderedModules, 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z') !== false, "Correctly includes SVG icon path for Security Module (shield)");

// Assert that Site-Search is rendered with its search icon and "Search" label
assert_test(strpos($renderedModules, 'Search') !== false, "Correctly renders 'Search' label for site-search module");
// Assert presence of the search icon circle path segment
assert_test(strpos($renderedModules, '<circle cx="11" cy="11" r="8"></circle>') !== false, "Correctly includes SVG icon path for Search Module (search)");

// Assert that FormBuilder is rendered with its clipboard icon and "Form Builder" label
assert_test(strpos($renderedModules, 'Form Builder') !== false, "Correctly renders 'Form Builder' label for formbuilder module");
// Assert presence of the clipboard icon rect segment
assert_test(strpos($renderedModules, '<rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>') !== false, "Correctly includes SVG icon path for Form Builder Module (clipboard)");

echo "Enabled modules view component tests completed successfully!\n\n";
