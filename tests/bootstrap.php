<?php
// tests/bootstrap.php
// Common bootstrapping and helpers for Zero CMS unit tests.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APPLICATION_ROOT', dirname(__DIR__));
define('TEST_SUITE_RUNNING', true);

// Register PSR-4 Namespace Autoloader for Zero namespace mapping
spl_autoload_register(function ($class) {
    $prefix = 'Zero\\';
    $base_dir = APPLICATION_ROOT . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use Zero\Core\Env;
use Zero\Database\DB;
use Zero\Database\MigrationManager;

// Load env configuration
Env::load(APPLICATION_ROOT);

// Force the test suite to use an isolated temporary database (e.g. cms_test) instead of the one defined in .env
try {
    $reflector = new ReflectionClass(Env::class);
    $property = $reflector->getProperty('data');
    $property->setAccessible(true);
    $data = $property->getValue();
    
    $originalDbName = $data['DB_NAME'] ?? 'cms';
    $testDbName = $originalDbName . '_test';
    
    if ($workerToken = getenv('TEST_TOKEN')) {
        $testDbName .= '_' . $workerToken;
    }
    
    $data['DB_NAME'] = $testDbName;
    $data['GCS_PREDEFINED_ACL'] = '';
    $data['STORAGE_DRIVER'] = 'local';
    $property->setValue(null, $data);
} catch (Exception $e) {
    echo "Warning setting up test database override: " . $e->getMessage() . "\n";
}

/**
 * Custom light-weight test assertion helper.
 * Exits with status code 1 on failure to signal the test runner.
 */
function assert_test(bool $condition, string $message) {
    if ($condition) {
        echo "  \033[32m✅ PASS:\033[0m {$message}\n";
    } else {
        echo "  \033[31m❌ FAIL:\033[0m {$message}\n";
        exit(1);
    }
}
