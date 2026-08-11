<?php
// tests/EnvTest.php
// Unit tests for the environment loader (Zero\Core\Env)

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\Env;

echo "=== Env Component Tests ===\n";

// 1. Verify get default fallback works
$fallback = Env::get('NON_EXISTENT_KEY_123_XYZ', 'fallback_val');
assert_test($fallback === 'fallback_val', "Env::get returns fallback value if key does not exist");

// 2. Test raw .env file parsing manually on a temp folder
$tempDir = __DIR__ . '/temp_env_' . bin2hex(random_bytes(4));
mkdir($tempDir);

$envContent = "
# This is a comment
DB_HOST=127.0.0.1
DB_PORT= 3306  
MOCK_TEST_UNIQUE_USER_KEY=\"test_user\"
DB_PASS='test_pass_with_#'
INVALID_LINE_NO_EQUALS
COMPLEX_VAL=foo=bar
";

file_put_contents($tempDir . '/.env', $envContent);

// Reset self::$data via reflection to force a fresh load of our temporary .env
try {
    $reflector = new ReflectionClass(Env::class);
    $property = $reflector->getProperty('data');
    $property->setAccessible(true);
    $property->setValue(null, null); // Force null to reload
    
    // Perform Env load from the temp directory
    $parsed = Env::load($tempDir);
    
    assert_test($parsed['DB_HOST'] === '127.0.0.1', "Parses standard variable correctly");
    assert_test($parsed['DB_PORT'] === '3306', "Trims whitespace from value correctly");
    assert_test($parsed['MOCK_TEST_UNIQUE_USER_KEY'] === 'test_user', "Strips double quotes from parsed values");
    assert_test($parsed['DB_PASS'] === 'test_pass_with_#', "Strips single quotes from parsed values and ignores inner hash");
    assert_test(!isset($parsed['INVALID_LINE_NO_EQUALS']), "Ignores malformed lines without equals separator");
    assert_test($parsed['COMPLEX_VAL'] === 'foo=bar', "Correctly handles multiple equal signs on line values");
    
    // Test get helper with loaded mock data
    assert_test(Env::get('MOCK_TEST_UNIQUE_USER_KEY') === 'test_user', "Env::get returns correct loaded variable value");
    
} finally {
    // Clean up temporary files
    @unlink($tempDir . '/.env');
    @rmdir($tempDir);
}

echo "Env component tests completed.\n\n";
