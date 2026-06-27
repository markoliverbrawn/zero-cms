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

// Render view with blog and security modules
echo "Testing rendering with blog and security modules...\n";
$modulesPayload = json_encode(['blog', 'security']);
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

echo "Enabled modules view component tests completed successfully!\n\n";
