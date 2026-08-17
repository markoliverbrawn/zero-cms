<?php
// tests/ModuleSettingsFormFieldMigrationTest.php
// Integration tests proving the module_settings.php + ModuleSettingsController migration onto
// the FormField component system preserved casting behavior (checkbox presence, number min/max
// clamping, select allow-listing) identically to the hand-rolled logic it replaced.

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Core\Template;
use Zero\Modules\Admin\Controllers\ModuleSettingsController;

echo "=== Module Settings Form / FormField Migration Tests ===\n";

App::bootstrap();

$schema = [
    'default_status' => ['type' => 'select', 'label' => 'Default Status', 'options' => ['pending' => 'Pending Review', 'approved' => 'Auto-Approved'], 'default' => 'pending', 'required' => true],
    'retention_days' => ['type' => 'number', 'label' => 'Retention Days', 'default' => 7, 'min' => 1, 'max' => 365, 'required' => true],
    'enable_feature' => ['type' => 'checkbox', 'label' => 'Enable Feature', 'default' => false],
    'notes' => ['type' => 'textarea', 'label' => 'Notes'],
    'display_name' => ['type' => 'text', 'label' => 'Display Name'],
];

// 1. Rendering
echo "Testing module_settings.php rendering...\n";
$viewPath = APPLICATION_ROOT . '/src/Modules/Admin/Views/module_settings.php';
$html = Template::renderFile($viewPath, [
    'moduleId' => 'demo',
    'moduleLabel' => 'Demo',
    'schema' => $schema,
    'values' => ['default_status' => 'approved', 'retention_days' => 14, 'enable_feature' => true, 'notes' => 'hello', 'display_name' => 'My Site'],
    'success' => '',
    'error' => '',
]);
assert_test(\strpos($html, 'type="number"') !== false, "Number field renders type=\"number\"");
assert_test(\strpos($html, 'min="1"') !== false && \strpos($html, 'max="365"') !== false, "Number field renders min/max attributes");
assert_test(\preg_match('/<option value="approved"[^>]*selected/', $html) === 1, "Select field renders the currently-saved option as selected");
assert_test(\strpos($html, 'settings-checkbox-label') !== false, "Checkbox renders its own wrapping label");
assert_test(\preg_match('/type="checkbox"[^>]*checked/', $html) === 1, "Checkbox renders checked for a truthy stored value");
assert_test(\strpos($html, '<textarea') !== false, "Textarea field renders a <textarea> element");
assert_test(\strpos($html, 'value="My Site"') !== false, "Text field renders its saved value");

// 2. Casting via ModuleSettingsController::collectSubmittedValues() (reflection, since protected)
echo "Testing ModuleSettingsController::collectSubmittedValues() casting...\n";
$controller = new ModuleSettingsController();
$reflector = new ReflectionMethod($controller, 'collectSubmittedValues');
$reflector->setAccessible(true);

$_POST = ['default_status' => 'approved', 'retention_days' => '-5', 'notes' => 'test notes', 'display_name' => 'Site A'];
// enable_feature deliberately absent, simulating an unchecked checkbox
$result = $reflector->invoke($controller, $schema);
assert_test($result['default_status'] === 'approved', "Select casting accepts a valid submitted option");
assert_test($result['retention_days'] == 1, "Number casting clamps a negative submission up to 'min' (identical to the pre-migration behavior)");
assert_test($result['enable_feature'] === false, "Checkbox casting treats an absent POST key as false");
assert_test($result['notes'] === 'test notes', "Textarea casting passes the submitted string through");
assert_test($result['display_name'] === 'Site A', "Text casting passes the submitted string through");

$_POST = ['default_status' => 'tampered_value', 'retention_days' => '999999', 'enable_feature' => '1'];
$result2 = $reflector->invoke($controller, $schema);
assert_test($result2['default_status'] === 'pending', "Select casting falls back to 'default' for an out-of-options submission");
assert_test($result2['retention_days'] == 365, "Number casting clamps an oversized submission down to 'max'");
assert_test($result2['enable_feature'] === true, "Checkbox casting treats a present POST key as true");

$_POST = [];
echo "Module Settings Form / FormField Migration tests completed.\n\n";
