<?php
// src/Support/TestBootstrap.php
// Common bootstrapping and helpers for Zero CMS unit tests.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APPLICATION_ROOT', dirname(dirname(__DIR__)));
define('TEST_SUITE_RUNNING', true);

// Register the Zero\ -> src/ namespace autoloader (shared by every entry point)
require_once APPLICATION_ROOT . '/src/Core/Autoloader.php';
\Zero\Core\Autoloader::init();

use Zero\Core\Env;
use Zero\Support\Emailer;
use Zero\Support\TestRunner;

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

// Prevent any real email from ever being sent as a side effect of running the test suite (e.g. a
// scheduled job like SecurityAuditJob getting dispatched and executed via the Queue module's own
// tests, which would otherwise email the real ADMIN_EMAIL configured in .env). Tests that need to
// exercise Emailer::send()'s real code path opt back out explicitly (see EmailerTest.php).
Emailer::enableTestMode();

/**
 * Custom light-weight test assertion helper.
 * Exits with status code 1 on failure to signal the test runner.
 */
function assert_test(bool $condition, string $message) {
    TestRunner::assertTest($condition, $message);
}
