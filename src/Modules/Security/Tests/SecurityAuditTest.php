<?php
// tests/SecurityAuditTest.php
// Unit tests for Gemini Security Audit Component (Zero\Modules\Admin\Controllers\SecurityAuditController)

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Modules\Security\Controllers\SecurityAuditController;
use Zero\Interfaces\Controller;
use Zero\Http\Router;
use Zero\Core\Storage\Storage;

echo "=== Gemini Security Audit Component Tests ===\n";

// 1. Test Controller Instance
echo "Testing controller instantiation...\n";
$controller = new SecurityAuditController();
assert_test($controller instanceof Controller, "SecurityAuditController class is successfully instantiated and implements Controller interface");

// 2. Test Telemetry Collection
echo "Testing system telemetry collection...\n";
$reflector = new ReflectionClass(SecurityAuditController::class);

$collectMethod = $reflector->getMethod('collectTelemetry');
$collectMethod->setAccessible(true);
$telemetry = $collectMethod->invoke($controller);

assert_test(is_array($telemetry), "collectTelemetry returns a valid array");
assert_test(isset($telemetry['install_file_exists']), "Telemetry contains 'install_file_exists' key");
assert_test(isset($telemetry['benchmarking_enabled']), "Telemetry contains 'benchmarking_enabled' key");
assert_test(isset($telemetry['default_admin_password_in_use']), "Telemetry contains 'default_admin_password_in_use' key");
assert_test(isset($telemetry['storage_directory_writable']), "Telemetry contains 'storage_directory_writable' key");
assert_test(isset($telemetry['total_super_admins']), "Telemetry contains 'total_super_admins' key");
assert_test(isset($telemetry['framework_cves']), "Telemetry contains 'framework_cves' key");
assert_test(isset($telemetry['static_analysis_findings']), "Telemetry contains 'static_analysis_findings' key");
assert_test(is_array($telemetry['static_analysis_findings']), "'static_analysis_findings' is a valid array");

// 2b. Test storage_directory_open_access telemetry across STORAGE_DRIVER configurations
echo "Testing storage open-access telemetry across storage drivers...\n";

// Under a remote storage driver, uploads never touch the local filesystem, so this check must
// never flag anything regardless of what's on disk.
putenv('STORAGE_DRIVER=gcs');
$gcsTelemetry = $collectMethod->invoke($controller);
assert_test(
    $gcsTelemetry['storage_directory_open_access'] === false,
    "storage_directory_open_access is false under STORAGE_DRIVER=gcs (uploads aren't served locally)"
);
putenv('STORAGE_DRIVER=local');

// Under the local driver, an existing uploads directory with no protective .htaccess must be
// flagged as open access. Point Storage at a throwaway root (unique per run) so this doesn't
// touch the real public/storage/ tree, which does carry a committed .htaccess.
$throwawayRoot = sys_get_temp_dir() . '/zero-security-audit-test-' . bin2hex(random_bytes(6));
mkdir($throwawayRoot . '/public/storage/uploads', 0777, true);
Storage::setRoot($throwawayRoot);

try {
    $openTelemetry = $collectMethod->invoke($controller);
    assert_test(
        $openTelemetry['storage_directory_open_access'] === true,
        "storage_directory_open_access is true when the local uploads directory exists with no .htaccess"
    );
} finally {
    // Restore Storage's root so later code in this process (including the fallback report test
    // below) resolves the real application tree again.
    $storageReflector = new ReflectionClass(Storage::class);
    $rootProp = $storageReflector->getProperty('root');
    $rootProp->setAccessible(true);
    $rootProp->setValue(null, null);

    @rmdir($throwawayRoot . '/public/storage/uploads');
    @rmdir($throwawayRoot . '/public/storage');
    @rmdir($throwawayRoot . '/public');
    @rmdir($throwawayRoot);
}

// 3. Test Fallback Report Compiler
echo "Testing local fallback report compiler...\n";
$reportMethod = $reflector->getMethod('getFallbackReport');
$reportMethod->setAccessible(true);
$report = $reportMethod->invoke($controller, $telemetry);

assert_test(is_string($report), "getFallbackReport returns a valid string");
assert_test(strpos($report, '#') !== false, "Report is formatted in beautiful Markdown containing heading titles");
assert_test(strpos($report, 'EXECUTIVE SUMMARY') !== false, "Report correctly contains 'EXECUTIVE SUMMARY' section");
assert_test(strpos($report, 'DISCOVERED VULNERABILITIES') !== false, "Report correctly contains 'DISCOVERED VULNERABILITIES' section");
assert_test(strpos($report, 'ARCHITECTURAL STRENGTHS') !== false, "Report correctly contains 'ARCHITECTURAL STRENGTHS' section");
assert_test(strpos($report, 'PHP FRAMEWORK CVE FEEDS') !== false, "Report correctly contains framework CVE comparative reference section");

// 4. Test Router Integration and Handshake Routes Mapping
echo "Testing router integration and audit routes mapping...\n";
$module = new \Zero\Modules\Security\Module();
$module->init();

$routerReflector = new ReflectionClass(Router::class);
$routesProp = $routerReflector->getProperty('routes');
$routesProp->setAccessible(true);
$routes = $routesProp->getValue();

assert_test(isset($routes['#^/admin/security/audit$#']), "Router registers security audit handshake endpoint pattern");
assert_test($routes['#^/admin/security/audit$#'] === SecurityAuditController::class, "Security audit handshake route is mapped to SecurityAuditController");
assert_test(isset($routes['#^/admin/list/security_audits$#']), "Router registers security audit list view endpoint pattern");
assert_test($routes['#^/admin/list/security_audits$#'] === SecurityAuditController::class, "Security audit list view route is mapped to SecurityAuditController");

echo "Gemini Security Audit component tests completed successfully!\n";
