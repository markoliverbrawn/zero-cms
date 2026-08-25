<?php
// src/Integration/Tests/HttpPipelineErrorPathsTest.php
// Integration test suite targeting HTTP error boundaries, 403 access rejections, 404 fallback routing, and site-not-found layouts.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Models\Site;
use Zero\Models\User;
use Zero\Support\Security;

echo "=== HTTP Pipeline Error Paths & Rejections Tests ===\n";

// 1. Setup Mock site for baseline registrations
App::bootstrap();

$siteId = Security::uuidv7();
$mockSite = new Site([
    'id' => $siteId,
    'name' => 'Error Pipeline Site',
    'domain' => 'errors.zero',
    'theme' => 'default',
    'enabled_modules' => json_encode(['security'])
]);
$mockSite->save();

App::setCurrentSite($mockSite);

// Enable Benchmarking to test RendersViews' appendBenchmarkWidget()
putenv('BENCHMARKING=true');

// 2. Test Site Not Found Diagnostic Page (renderSiteNotFoundPage)
echo "Testing site-not-found diagnostic rendering...\n";

// renderSiteNotFoundPage() gates the tenant diagnostic list behind ENVIRONMENT=development, so the
// environment has to be forced here rather than inherited: Env::get() reads the real environment
// before .env, and a deployment with no .env at all (CI) defaults to 'production', which renders
// the deliberately information-free variant of this page.
putenv('ENVIRONMENT=development');

ob_start();
App::renderSiteNotFoundPage('unregistered.zero');
$siteNotFoundHtml = ob_get_clean();

assert_test(str_contains($siteNotFoundHtml, 'unregistered.zero'), "Site-not-found layout includes target host");
assert_test(str_contains($siteNotFoundHtml, 'Error Pipeline Site'), "Site-not-found lists active sites registered in multi-tenant DB");

// The converse of that gate: in production the page must not disclose the tenant list to a visitor
// who guessed an unregistered host.
putenv('ENVIRONMENT=production');

ob_start();
App::renderSiteNotFoundPage('unregistered.zero');
$productionNotFoundHtml = ob_get_clean();

assert_test(!str_contains($productionNotFoundHtml, 'Error Pipeline Site'), "Site-not-found withholds the registered tenant list outside development");

putenv('ENVIRONMENT=development');

// 3. Test Access Denied and Role Rejection UI compilation
echo "Testing 403 access denied template compilation...\n";

ob_start();
App::render('admin/access-denied', [
    'currentRole' => 'editor',
    'requiredPermission' => 'users.manage'
]);
$accessDeniedHtml = ob_get_clean();

assert_test(str_contains($accessDeniedHtml, 'Access Denied'), "Access-denied layout renders rejection content");
assert_test(str_contains($accessDeniedHtml, 'Benchmark'), "Benchmark widget is appended onto layout footer when BENCHMARKING is active");

// 4. Test Router dynamic fallback on a nonexistent page (returns false)
echo "Testing router dynamic page-view fallback for nonexistent slug...\n";
$router = new \Zero\Http\Router();
$handled = $router->handle('/nonexistent-page-slug-xyz');
assert_test($handled === false, "Router correctly returns false (allows 404 fallback) for unregistered page slugs");

// Clean up DB tables
App::setCurrentSite(null);
$mockSite->forceDelete();

// 5. Test End-to-End Terminal 404 Fallback for Admin URIs
// We set $_SERVER variables targeting a nonexistent admin route, running handleRequest() to terminate with an exit
echo "Executing terminal fallback for nonexistent admin route. Terminating subprocess successfully.\n";

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/admin/nonexistent-dashboard-action';

App::handleRequest(APPLICATION_ROOT . '/public');
