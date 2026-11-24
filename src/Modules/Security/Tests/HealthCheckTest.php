<?php
// src/Modules/Security/Tests/HealthCheckTest.php
// Deployment health checks: each check's decision logic (evaluate(), no network or database),
// how runs are compared and when they notify, the archived row shape, and route registration.

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Http\Router;
use Zero\Models\Site;
use Zero\Modules\Security\Controllers\HealthCheckController;
use Zero\Modules\Security\HealthChecks\CheckResult;
use Zero\Modules\Security\HealthChecks\Checks\CoreVersionCheck;
use Zero\Modules\Security\HealthChecks\Checks\DefaultPasswordCheck;
use Zero\Modules\Security\HealthChecks\Checks\FailedLoginCheck;
use Zero\Modules\Security\HealthChecks\Checks\InstallFileCheck;
use Zero\Modules\Security\HealthChecks\Checks\PhpVersionCheck;
use Zero\Modules\Security\HealthChecks\Checks\ProxySecretCheck;
use Zero\Modules\Security\HealthChecks\Checks\SecurityHeadersCheck;
use Zero\Modules\Security\HealthChecks\ProjectRoot;
use Zero\Modules\Security\Models\SecurityAudit;
use Zero\Modules\Security\Module;
use Zero\Modules\Security\Services\HealthCheckRunner;

echo "=== Deployment Health Check Tests ===\n";

$status = fn (CheckResult $r): string => $r->status;

echo "Testing the proxy secret check...\n";
assert_test($status(ProxySecretCheck::evaluate('s3cret', true)) === 'pass', "A configured proxy secret passes");
assert_test($status(ProxySecretCheck::evaluate('', true)) === 'fail', "An empty proxy secret fails in production");
assert_test($status(ProxySecretCheck::evaluate('', false)) === 'warn', "An empty proxy secret only warns outside production");

echo "Testing the PHP version check...\n";
$feed = ['8' => ['8.3' => ['version' => '8.3.28', 'tags' => []], '8.2' => ['version' => '8.2.36', 'tags' => ['security']]]];
assert_test($status(PhpVersionCheck::evaluate('8.3.28', $feed)) === 'pass', "The latest patch of a supported branch passes");
assert_test($status(PhpVersionCheck::evaluate('8.3.10', $feed)) === 'warn', "A supported branch behind its latest patch warns");
assert_test(str_contains(PhpVersionCheck::evaluate('8.2.36', $feed)->message, 'security-fix-only'), "A security-fix-only branch is called out");
assert_test($status(PhpVersionCheck::evaluate('8.1.31', $feed)) === 'fail', "A branch missing from php.net's supported list fails");

echo "Testing the served security headers check...\n";
$good = [
    'content-security-policy' => "default-src 'self'; frame-ancestors 'self'",
    'x-content-type-options' => 'nosniff',
    'strict-transport-security' => 'max-age=31536000',
    'referrer-policy' => 'strict-origin-when-cross-origin',
];
assert_test($status(SecurityHeadersCheck::evaluate($good, true, true)) === 'pass', "All headers over HTTPS pass (CSP frame-ancestors stands in for X-Frame-Options)");
$noHsts = $good;
unset($noHsts['strict-transport-security']);
assert_test($status(SecurityHeadersCheck::evaluate($noHsts, true, true)) === 'fail', "HTTPS without HSTS fails");
assert_test($status(SecurityHeadersCheck::evaluate($noHsts, false, false)) === 'pass', "HSTS is not required on plain HTTP outside production");
assert_test($status(SecurityHeadersCheck::evaluate($noHsts, false, true)) === 'fail', "Plain HTTP fails in production");
$noCsp = $good;
unset($noCsp['content-security-policy']);
assert_test($status(SecurityHeadersCheck::evaluate($noCsp, true, true)) === 'fail', "A missing CSP fails");
$noReferrer = $good;
unset($noReferrer['referrer-policy']);
assert_test($status(SecurityHeadersCheck::evaluate($noReferrer, true, true)) === 'warn', "A missing Referrer-Policy only warns");

echo "Testing the default password check...\n";
assert_test($status(DefaultPasswordCheck::evaluate([], true)) === 'pass', "No default-password accounts passes");
assert_test($status(DefaultPasswordCheck::evaluate(['admin'], true)) === 'fail', "A default-password account fails in production");
assert_test($status(DefaultPasswordCheck::evaluate(['admin'], false)) === 'warn', "A default-password account only warns outside production");

echo "Testing the installer file check...\n";
assert_test($status(InstallFileCheck::evaluate([])) === 'pass', "No installer passes");
assert_test($status(InstallFileCheck::evaluate(['install.php' => "<?php if (php_sapi_name() !== 'cli') { exit; }"])) === 'pass', "A CLI-locked installer passes");
assert_test($status(InstallFileCheck::evaluate(['install.php' => '<?php // installs things'])) === 'fail', "A browser-runnable installer fails");

echo "Testing the core version check...\n";
$tags = ['v2.0.0', 'v2.1.1', 'v2.1.2', 'nightly'];
assert_test($status(CoreVersionCheck::evaluate('v2.1.2', $tags)) === 'pass', "The newest release passes");
assert_test($status(CoreVersionCheck::evaluate('v2.1.1', $tags)) === 'warn', "An older release warns");
assert_test($status(CoreVersionCheck::evaluate('dev-main', $tags)) === 'warn', "A non-release install warns");
assert_test($status(CoreVersionCheck::evaluate('v2.1.2', ['nightly'])) === 'warn', "No release tags to compare against warns");

echo "Testing the failed sign-in check...\n";
assert_test($status(FailedLoginCheck::evaluate(20, 20)) === 'pass', "A count at the threshold passes");
assert_test($status(FailedLoginCheck::evaluate(21, 20)) === 'warn', "A count above the threshold warns");

echo "Testing installation root resolution...\n";
assert_test(ProjectRoot::resolve('/srv/site/vendor/markoliverbrawn/zero-cms-core', '/srv/site') === '/srv/site', "SUBPROJECT_ROOT wins when defined");
assert_test(ProjectRoot::resolve('/srv/site/vendor/markoliverbrawn/zero-cms-core', null) === '/srv/site', "Core's CLI run from vendor/ finds the sub-project root");
assert_test(ProjectRoot::resolve('/srv/zero-cms/', null) === '/srv/zero-cms', "A core checkout is its own root");

echo "Testing run comparison and notification...\n";
assert_test(CheckResult::worst(['pass', 'warn', 'pass']) === 'warn' && CheckResult::worst(['warn', 'fail']) === 'fail' && CheckResult::worst([]) === 'pass', "The overall status is the worst check status");
$runA = [['id' => 'a', 'status' => 'pass'], ['id' => 'b', 'status' => 'warn']];
$runB = [['id' => 'b', 'status' => 'warn'], ['id' => 'a', 'status' => 'pass']];
$runC = [['id' => 'a', 'status' => 'pass'], ['id' => 'b', 'status' => 'pass']];
assert_test(!HealthCheckRunner::hasChanged($runA, $runB), "The same statuses in a different order are not a change");
assert_test(HealthCheckRunner::hasChanged($runA, $runC), "A recovered check is a change");
assert_test(HealthCheckRunner::hasChanged(null, $runA), "A first run is a change");
assert_test(!HealthCheckRunner::shouldNotify(['status' => 'warn', 'changed' => false]), "An unchanged run with only warnings sends no email");
assert_test(HealthCheckRunner::shouldNotify(['status' => 'pass', 'changed' => true]), "A changed run sends an email");
assert_test(HealthCheckRunner::shouldNotify(['status' => 'fail', 'changed' => false]), "A failing run always sends an email");

echo "Testing the archived run...\n";
App::bootstrap();
$siteId = 'health-check-test-site';
DB::query("DELETE FROM security_audits WHERE site_id = ?", [$siteId]);
App::setCurrentSite(new Site(['id' => $siteId, 'name' => 'Health', 'domain' => 'health.test']));
$audit = new SecurityAudit(['user_id' => null, 'status' => 'warn', 'environment' => 'test', 'results' => json_encode($runA)]);
$audit->save();
assert_test(HealthCheckRunner::latestResults($siteId) === $runA, "A saved run's results are read back as the site's latest");
assert_test(HealthCheckRunner::latestResults('no-such-site') === null, "A site with no runs has no latest results");
DB::query("DELETE FROM security_audits WHERE site_id = ?", [$siteId]);

echo "Testing route registration...\n";
(new Module())->init();
$routesProp = (new ReflectionClass(Router::class))->getProperty('routes');
$routesProp->setAccessible(true);
$routes = $routesProp->getValue();
assert_test(($routes['#^/admin/security/audit$#'] ?? null) === HealthCheckController::class, "The health check page is routed to HealthCheckController");
assert_test(($routes['#^/admin/list/security_audits$#'] ?? null) === HealthCheckController::class, "The old audit list URL shows the health check page");

echo "Health check tests completed successfully!\n";
