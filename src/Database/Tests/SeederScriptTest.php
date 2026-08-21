<?php
// tests/SeederScriptTest.php
// Integration test to verify Option 1 and Option 2 fallback of the multi-tenant seeder.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\Env;
use Zero\Database\DB;

echo "=== Seeder Script CLI & Fallback Option Tests ===\n";

// Bootstrap App to guarantee database connectivity and environment variables
\Zero\Core\App::bootstrap();

$testDb = Env::get('DB_NAME');
$phpBinary = 'php';

// bin/seed legitimately wipes the uploads tree before reseeding. Pointing it at a throwaway
// storage root keeps that from destroying the real public/storage/uploads directory -- which is
// shared global state, so a seeder run here was previously deleting the fixture files of any
// other suite running concurrently in another worker slot (and, when run on a developer's
// machine, their actual uploaded media).
$seederStorageRoot = APPLICATION_ROOT . '/storage/seeder-test-root-' . \bin2hex(\random_bytes(4));
if (!\is_dir($seederStorageRoot . '/public/storage/uploads')) {
    \mkdir($seederStorageRoot . '/public/storage/uploads', 0775, true);
}

// Helper to run seeder with specific arguments and environment variables
function run_seeder_test_proc(string $args = '', array $envOverrides = []): string {
    global $testDb, $phpBinary, $seederStorageRoot;
    
    // Merge standard test database name with any test-specific env overrides
    $envVars = array_merge([
        'DB_NAME' => $testDb,
        'STORAGE_DRIVER' => 'local',
        'STORAGE_ROOT' => $seederStorageRoot
    ], $envOverrides);
    
    $envPrefix = '';
    foreach ($envVars as $key => $value) {
        $envPrefix .= "{$key}=" . escapeshellarg($value) . " ";
    }
    
    $cmd = "{$envPrefix} {$phpBinary} " . escapeshellarg(APPLICATION_ROOT . '/bin/seed') . " {$args} 2>&1";
    return (string) shell_exec($cmd);
}

// 1. Test Option 1 Command-Line Argument: --sites=default
echo "  Testing Option 1 CLI Parameter: --sites=default...\n";

$output = run_seeder_test_proc('--sites=default');

// Assert that the command completed and printed correct logs
assert_test(strpos($output, "Selective seeding enabled for: [default]") !== false, "CLI correctly parsed selective seeding modes");
assert_test(strpos($output, "SEEDING DATASET: default.php") !== false, "Default dataset loaded");
assert_test(strpos($output, "DATABASE SEEDING OPERATIONS COMPLETED WITH 100% SUCCESS") !== false, "Seeder executed with 100% success state");

// Verify DB entries to confirm selective database seeding
$siteCount = (int) DB::query("SELECT COUNT(*) FROM sites")->fetchColumn();
assert_test($siteCount === 1, "Only 1 site was seeded (the default base site)");

$siteName = DB::query("SELECT name FROM sites LIMIT 1")->fetchColumn();
assert_test(strpos($siteName, "Zero CMS Main Site") !== false, "The seeded site is the default base tenant");


// 2. Test Option 2 Fallback: SEED_SITES="default" in env
echo "  Testing Option 2 Fallback Env Variable: SEED_SITES=\"default\"...\n";

$output = run_seeder_test_proc('', ['SEED_SITES' => 'default']);

assert_test(strpos($output, "Selective seeding enabled for: [default]") !== false, "Env fallback correctly identified and parsed from SEED_SITES");
assert_test(strpos($output, "SEEDING DATASET: default.php") !== false, "Default dataset loaded for user provisioning");
assert_test(strpos($output, "DATABASE SEEDING OPERATIONS COMPLETED WITH 100% SUCCESS") !== false, "Seeder executed with 100% success state in env fallback mode");

// Verify DB entries to confirm selective database seeding in env fallback mode
$siteCount = (int) DB::query("SELECT COUNT(*) FROM sites")->fetchColumn();
assert_test($siteCount === 1, "Only 1 site was seeded (the default base site)");

$siteName = DB::query("SELECT name FROM sites LIMIT 1")->fetchColumn();
assert_test(strpos($siteName, "Zero CMS Main Site") !== false, "The seeded site is the default base tenant");


// 3. Test CLI Precedence over Env Fallback: --sites=default with SEED_SITES="blank"
echo "  Testing CLI priority overriding SEED_SITES fallback env variable...\n";

$output = run_seeder_test_proc('--sites=default', ['SEED_SITES' => 'blank']);

assert_test(strpos($output, "Selective seeding enabled for: [default]") !== false, "CLI argument took absolute priority over SEED_SITES env fallback");
assert_test(strpos($output, "SEEDING DATASET: default.php") !== false, "Seeded targeted default dataset");

$siteName = DB::query("SELECT name FROM sites LIMIT 1")->fetchColumn();
assert_test(strpos($siteName, "Zero CMS Main Site") !== false, "CLI target produced the default base tenant, not the blank standalone site");


// 4. Test Blank Clean Install via --only=blank
echo "  Testing Blank Standalone Setup with --only=blank...\n";

$output = run_seeder_test_proc('--only=blank');

assert_test(strpos($output, "Selective seeding enabled for: [blank]") !== false, "Blank site option was parsed correctly");
assert_test(strpos($output, "SEEDING DATASET: default.php") !== false, "Default base was executed");

$siteCount = (int) DB::query("SELECT COUNT(*) FROM sites")->fetchColumn();
assert_test($siteCount === 1, "Only 1 blank standalone site was seeded");

$siteName = DB::query("SELECT name FROM sites LIMIT 1")->fetchColumn();
assert_test($siteName === "My New Standalone Site", "Blank site is named My New Standalone Site");


// 5. Test Multiple Comma-Separated Values: --sites=default,security
echo "  Testing multiple targets via --sites=default,security...\n";

$output = run_seeder_test_proc('--sites=default,security');

assert_test(strpos($output, "Selective seeding enabled for: [default, security]") !== false, "Multiple comma-separated list of targets parsed correctly");
assert_test(strpos($output, "SEEDING DATASET: default.php") !== false, "Default dataset ran");

$siteCount = (int) DB::query("SELECT COUNT(*) FROM sites")->fetchColumn();
assert_test($siteCount === 1, "The targeted default site was successfully seeded");


// 6. Test ADMIN_PASSWORD post-run seeder hook override from .env
echo "  Testing ADMIN_PASSWORD custom override post-run seeder hook...\n";

// Run the seeder with a custom admin password env variable
$output = run_seeder_test_proc('--sites=default', ['ADMIN_PASSWORD' => 'CustomTestAdminPassword555']);

assert_test(strpos($output, "Applying custom ADMIN_PASSWORD override from .env") !== false, "Seeder log correctly reported ADMIN_PASSWORD override being applied");
assert_test(strpos($output, "[Seeder-Hook] Successfully updated administrator account passwords to ADMIN_PASSWORD from .env") !== false, "Seeder-Hook success message was outputted");

// Query the database to verify the admin password was updated and verify its hash
$adminHash = DB::query("SELECT password_hash FROM users WHERE username = 'admin'")->fetchColumn();
assert_test(!empty($adminHash), "Admin user is present in the database after seeding");
assert_test(password_verify('CustomTestAdminPassword555', $adminHash) === true, "Admin user password hash matches CustomTestAdminPassword555");


// Remove the throwaway storage root the seeded runs wrote into.
if (\is_dir($seederStorageRoot)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($seederStorageRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        $entry->isDir() ? \rmdir($entry->getPathname()) : \unlink($entry->getPathname());
    }
    \rmdir($seederStorageRoot);
}

echo "\n✅ Seeder Script CLI & Fallback Option Integration Tests Passed Successfully!\n";
