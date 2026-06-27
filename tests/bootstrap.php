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
    
    $data['DB_NAME'] = $testDbName;
    $data['GCS_PREDEFINED_ACL'] = '';
    $data['STORAGE_DRIVER'] = 'local';
    $property->setValue(null, $data);
} catch (Exception $e) {
    echo "Warning setting up test database override: " . $e->getMessage() . "\n";
}

// Ensure the isolated test database exists in MySQL
$host = Env::get('DB_HOST', '127.0.0.1');
$port = Env::get('DB_PORT', '3306');
$user = Env::get('DB_USER', 'root');
$pass = Env::get('DB_PASS', '');
$testDb = Env::get('DB_NAME');

try {
    $rawPdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $rawPdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace("`", "``", $testDb) . "`");
} catch (PDOException $e) {
    echo "Fatal Error ensuring test database exists: " . $e->getMessage() . "\n";
    exit(1);
}

// Keep the test database schema 100% synchronized and cleanly truncated for total test isolation
try {
    $pdo = DB::getPDO();
    
    // Always run pending migrations to ensure all core & module schemas are present and up-to-date
    ob_start();
    MigrationManager::up();
    ob_end_clean();

    // Rapid truncate to guarantee 100% clean test isolation on every run
    DB::query("SET FOREIGN_KEY_CHECKS = 0;");

    $tables = ['pages', 'blog_posts', 'audit_logs', 'password_resets', 'sites', 'blog_comments', 'form_submissions', 'queue_jobs', 'queue_scheduled_tasks'];
    foreach ($tables as $t) {
        // Double-check existence to prevent truncation failures if tables are dropped or modified
        $hasTable = DB::query("SHOW TABLES LIKE '{$t}'")->fetch();
        if ($hasTable) {
            DB::query("TRUNCATE TABLE `{$t}`");
        }
    }

    DB::query("SET FOREIGN_KEY_CHECKS = 1;");
} catch (Exception $e) {
    echo "Fatal Error initializing test database schemas: " . $e->getMessage() . "\n";
    exit(1);
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
