<?php
// tests/TemplateTest.php
// Unit tests for template compilation engine (Zero\Core\Template)

require_once __DIR__ . '/bootstrap.php';

use Zero\Core\Template;

echo "=== Template Component Tests ===\n";

// 1. Create a temporary PHP template file
$tempDir = __DIR__ . '/temp_tpl_' . bin2hex(random_bytes(4));
mkdir($tempDir);
$tplFile = $tempDir . '/mock_view.php';

$tplContent = '
<?php
// Mock PHP View Template
echo "Hello, " . htmlspecialchars($name) . "!";
if (isset($optional)) {
    echo " " . htmlspecialchars($optional);
}
// Trigger an intentional notice to verify suppression
echo $undefined_variable;
?>';

file_put_contents($tplFile, $tplContent);

try {
    // 2. Test template variable extraction and output capturing
    echo "Testing variable extraction and output buffering...\n";
    $rendered = Template::renderFile($tplFile, [
        'name' => 'John Doe',
        'optional' => 'Welcome to Zero CMS.'
    ]);
    
    assert_test(strpos($rendered, 'Hello, John Doe!') !== false, "Correctly extracts and renders extracted variables");
    assert_test(strpos($rendered, 'Welcome to Zero CMS.') !== false, "Correctly handles optional template parameters");
    
    // 3. Test warning and notice suppression
    echo "Testing notice and warning suppression...\n";
    $oldReporting = error_reporting();
    
    // The renderFile should suppress PHP Notice for $undefined_variable
    $renderedWithSuppression = Template::renderFile($tplFile, [
        'name' => 'Alice'
    ]);
    
    assert_test(strpos($renderedWithSuppression, 'Hello, Alice!') !== false, "Render completes successfully despite undefined variable reference");
    assert_test(error_reporting() === $oldReporting, "Restores the original error_reporting value after template compilation completes");

} finally {
    // Clean up temporary template files
    @unlink($tplFile);
    @rmdir($tempDir);
}

echo "Template component tests completed.\n\n";
