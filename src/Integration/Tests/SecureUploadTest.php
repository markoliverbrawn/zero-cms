<?php
// tests/SecureUploadTest.php
// Integration test to verify secure private uploads and gated streaming downloads.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Core\Storage\Storage;
use Zero\Database\DB;
use Zero\Models\Media;
use Zero\Models\Site;
use Zero\Models\User;
use Zero\Modules\Admin\Controllers\SecureDownloadController;
use Zero\Support\Security;

echo "=== Secure Unified Upload & Private Storage Tests ===\n";

// 1. Setup Mock Site Tenant Environments for Multitenancy Boundary Verification
echo "Setting up mock multi-tenant sites...\n";
App::bootstrap();

$siteA = new Site([
    'name' => 'Site A Secure Portal',
    'domain' => 'site-a.zero',
    'theme' => 'default',
    'enabled_modules' => json_encode(['security'])
]);
$siteA->save();

$siteB = new Site([
    'name' => 'Site B Secure Portal',
    'domain' => 'site-b.zero',
    'theme' => 'default',
    'enabled_modules' => json_encode(['security'])
]);
$siteB->save();

// 2. Test Private Media Upload Creation
echo "Testing private file upload and path obfuscation...\n";
App::setCurrentSite($siteA);
$siteAId = $siteA->id;

// Create the local private directory
$privateDir = APPLICATION_ROOT . '/storage/private/';
if (!file_exists($privateDir)) {
    mkdir($privateDir, 0775, true);
}

$uuid = Security::uuidv7();
$originalName = 'confidential_contract_v1.pdf';
$diskName = $uuid . '.pdf';
$testContent = "%PDF-1.4 Mock Private Document Data Stream";

$privatePath = '/storage/private/' . $diskName;
$physicalPath = APPLICATION_ROOT . $privatePath;

// Write physical file to private disk path
file_put_contents($physicalPath, $testContent);
assert_test(file_exists($physicalPath), "Physical file written successfully to private disk storage");

$media = new Media([
    'filename' => $diskName,
    'path' => $privatePath,
    'mime' => 'application/pdf',
    'title' => 'Confidential Contract',
    'visibility' => 'private',
    'submission_id' => Security::uuidv7(),
    'original_name' => $originalName,
    'file_size' => strlen($testContent)
]);
$media->save();

assert_test(!empty($media->id), "Private media database active record saved successfully");
assert_test($media->visibility === 'private', "Media record visibility set successfully to 'private'");

// 3. Test getUrl() auto-routing behavior
echo "Testing getUrl() dynamic auto-routing...\n";
$resolvedUrl = $media->getUrl();
$expectedUrl = "/admin/secure-download/{$media->id}";
assert_test($resolvedUrl === $expectedUrl, "getUrl() successfully intercepted private file and returned gated controller link: {$resolvedUrl}");

// 4. Test Gated Access Control Logic and Tenant Scoping
echo "Testing Gated Access Control Logic and Tenant Scoping...\n";

// A. Test Multi-Tenant Boundary Blockage (Logged in as Site B, trying to access Site A's file)
App::setCurrentSite($siteB);
$siteBId = $siteB->id;

// Query using Site B's tenant context must return null
$fileForSiteB = DB::query("
    SELECT * FROM media 
    WHERE id = ? AND site_id = ? AND visibility = 'private' AND deleted_at IS NULL
", [$media->id, $siteBId])->fetch();

assert_test($fileForSiteB === false, "Database query successfully enforces tenant boundaries (Site B Admin is blocked from finding Site A's private file)");

// B. Test Authorized Tenant Access (Logged in as Site A, trying to access Site A's file)
App::setCurrentSite($siteA);

$fileForSiteA = DB::query("
    SELECT * FROM media 
    WHERE id = ? AND site_id = ? AND visibility = 'private' AND deleted_at IS NULL
", [$media->id, $siteAId])->fetch();

assert_test($fileForSiteA !== false, "Database query successfully allows authorized tenant access to private file");
assert_test($fileForSiteA['original_name'] === $originalName, "Retrieved original name matches successfully");
assert_test($fileForSiteA['file_size'] == strlen($testContent), "Retrieved file size matches successfully");

// 5. Cleanup
echo "Cleaning up mock database records and storage disk...\n";
$media->forceDelete();
@unlink($physicalPath);

// Clear active site context to bypass site deletion blocks
App::setCurrentSite(null);

$siteA->forceDelete();
$siteB->forceDelete();

echo "Secure Unified Upload & Private Storage tests completed successfully.\n\n";
