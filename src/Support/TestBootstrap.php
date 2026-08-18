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
 *
 * A failure is recorded and the file keeps going, so a single run reports every broken assertion
 * instead of stopping at the first. The subprocess still exits with status code 1 if anything
 * failed, which is how the runner detects a failing suite.
 */
function assert_test(bool $condition, string $message) {
    TestRunner::assertTest($condition, $message);
}

/**
 * Assert a precondition, aborting the file immediately if it fails.
 *
 * Use this only where continuing would produce meaningless cascade failures rather than useful
 * information -- no database connection, a fixture that did not get created, a required extension
 * missing. For ordinary checks use assert_test(), so one run surfaces the full list of problems.
 */
function assert_critical(bool $condition, string $message) {
    TestRunner::assertTest($condition, $message, true);
}

/**
 * Resolve a filesystem path a test is about to read/write/delete and confirm it stays inside
 * a safe base directory (e.g. this file's own temp fixture dir, or APPLICATION_ROOT's storage
 * folder), rejecting anything that escapes it via '..' segments or a symlink. Test fixture paths
 * are always built from hardcoded prefixes plus the test's own self-generated random suffixes
 * rather than external input, but every test performing filesystem I/O should still route its
 * target path through here as an explicit, auditable confinement check rather than an implicit
 * assumption.
 *
 * Resolves realpath() on the deepest already-existing ancestor directory, since realpath()
 * itself returns false for a path that doesn't exist yet (the common case right before a file
 * is created).
 */
function confine_test_path(string $path, string $safeBaseDir): string
{
    $safeBaseReal = realpath($safeBaseDir);
    if ($safeBaseReal === false) {
        throw new \RuntimeException("confine_test_path: safe base directory does not exist: {$safeBaseDir}");
    }

    $dirReal = realpath(dirname($path));
    if ($dirReal === false) {
        throw new \RuntimeException("confine_test_path: parent directory does not exist: " . dirname($path));
    }

    if ($dirReal !== $safeBaseReal && strpos($dirReal, $safeBaseReal . DIRECTORY_SEPARATOR) !== 0) {
        throw new \RuntimeException("confine_test_path: '{$path}' escapes safe base '{$safeBaseDir}'");
    }

    return $dirReal . DIRECTORY_SEPARATOR . basename($path);
}
