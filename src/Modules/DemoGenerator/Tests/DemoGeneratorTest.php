<?php
// tests/DemoGeneratorTest.php
// Integration test to verify the Demo Generator module, sandbox creations, and teardown lifecycle.

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Database\DB;
use Zero\Models\Site;
use Zero\Models\User;
use Zero\Models\Page;
use Zero\Models\Media;
use Zero\Modules\DemoGenerator\Jobs\TeardownExpiredDemosJob;
use Zero\Modules\DemoGenerator\Services\DemoSiteFactory;
use Zero\Core\Storage\Storage;

echo "=== Sandbox Demo Generator Lifecycle Integration Tests ===\n";

// Ensure clean environment for testing
$testEmail = 'sandbox-test-runner@zero.guide';
$preset = 'kitchensink';

// Clean out any existing test sites under this email if present from previous failed dry runs
$existingUsers = DB::query("SELECT site_id FROM users WHERE email = ?", [$testEmail])->fetchAll(\PDO::FETCH_COLUMN);
foreach ($existingUsers as $siteId) {
    if (!empty($siteId)) {
        $oldSite = Site::find($siteId);
        if ($oldSite) {
            $oldSite->forceDelete();
        }
    }
}

// Clean up any orphan user rows directly to guarantee unique key constraints
DB::query("DELETE FROM users WHERE email = ?", [$testEmail]);

echo "  1. Simulating Demo Sandbox Site Creation...\n";
$factory = new DemoSiteFactory();
$result = $factory->createDemoSite($testEmail, $preset);

$domain = $result['domain'];
$password = $result['password'];

assert_test(!empty($domain), "Demo site domain generated successfully: {$domain}");
assert_test(!empty($password), "Demo admin secure password generated successfully");

// Verify Site was created in the database
$siteRow = DB::query("SELECT * FROM sites WHERE domain = ? LIMIT 1", [$domain])->fetch();
assert_test($siteRow !== false, "Demo Site successfully exists in sites table");

$siteId = $siteRow['id'];
$site = Site::find($siteId);
assert_test($site !== null, "Site model loaded successfully via Active Record");
assert_test(!empty($site->expires_at), "Demo Site has expiration timestamp set");
assert_test(strtotime($site->expires_at) > time(), "Demo Site expiration date is in the future");

// Verify Administrator User was created
$userRow = DB::query("SELECT * FROM users WHERE site_id = ? LIMIT 1", [$siteId])->fetch();
assert_test($userRow !== false, "Demo Site administrator exists in users table");
assert_test($userRow['username'] === $testEmail, "Administrator username is unique email address");
assert_test($userRow['role'] === 'super_admin', "Administrator role is super_admin for the tenant");

// Verify Pages were seeded from the preset blueprint
$pagesCount = intval(DB::query("SELECT COUNT(*) FROM pages WHERE site_id = ?", [$siteId])->fetchColumn());
assert_test($pagesCount > 0, "Pages populated successfully from blueprint: {$pagesCount} pages found");

// Verify Media metadata were seeded
$mediaCount = intval(DB::query("SELECT COUNT(*) FROM media WHERE site_id = ?", [$siteId])->fetchColumn());
assert_test($mediaCount > 0, "Media metadata records populated successfully: {$mediaCount} items found");

// Verify physical files were copied successfully to the tenant uploads directory
$uploadDir = Storage::getUploadsRoot() . '/' . $siteId;
assert_test(file_exists($uploadDir) && is_dir($uploadDir), "Tenant physical uploads directory created on disk");

$copiedFiles = glob($uploadDir . '/*');
assert_test(is_array($copiedFiles) && count($copiedFiles) >= 3, "Physical files successfully copied to the isolated sandbox folder (Count: " . count($copiedFiles) . ")");

// Verify referential integrity of page-builder block media_id references
$pagesWithBlockImages = DB::query("SELECT content FROM pages WHERE site_id = ? AND content LIKE '%media_id%'", [$siteId])->fetchAll(\PDO::FETCH_COLUMN);
$referentialIntegrityMaintained = true;

foreach ($pagesWithBlockImages as $contentJson) {
    $blocks = json_decode($contentJson, true);
    if (is_array($blocks)) {
        foreach ($blocks as $b) {
            if (isset($b['media_id'])) {
                $mId = $b['media_id'];
                $mRecord = DB::query("SELECT id FROM media WHERE id = ? AND site_id = ? LIMIT 1", [$mId, $siteId])->fetch();
                if (!$mRecord) {
                    $referentialIntegrityMaintained = false;
                    break 2;
                }
            }
        }
    }
}
assert_test($referentialIntegrityMaintained, "Referential media ID mapping between page-builder blocks and media rows maintained 100%");


echo "\n  2. Simulating TeardownExpiredDemosJob Execution...\n";

// Force-expire the created test site in the database to simulate a 24-hour timeout passing
DB::query("UPDATE sites SET expires_at = ? WHERE id = ?", [date('Y-m-d H:i:s', time() - 3600), $siteId]);

// Execute the teardown background job
$teardownJob = new TeardownExpiredDemosJob();
$teardownJob->execute([]);

// Verify that the site was permanently deleted
$siteCheck = DB::query("SELECT COUNT(*) FROM sites WHERE id = ?", [$siteId])->fetchColumn();
assert_test(intval($siteCheck) === 0, "Teardown Job successfully deleted the Site database record");

// Verify cascading deletions of users, pages, media records
$userCheck = DB::query("SELECT COUNT(*) FROM users WHERE site_id = ?", [$siteId])->fetchColumn();
assert_test(intval($userCheck) === 0, "Users cascade-deleted successfully from users table");

$pageCheck = DB::query("SELECT COUNT(*) FROM pages WHERE site_id = ?", [$siteId])->fetchColumn();
assert_test(intval($pageCheck) === 0, "Pages cascade-deleted successfully from pages table");

$mediaCheck = DB::query("SELECT COUNT(*) FROM media WHERE site_id = ?", [$siteId])->fetchColumn();
assert_test(intval($mediaCheck) === 0, "Media cascade-deleted successfully from media table");

// Verify physical files and directories were deleted recursively from storage
assert_test(!file_exists($uploadDir), "Tenant physical uploads directory completely removed from disk storage");

echo "Demo Generator module integration tests completed successfully!\n\n";
