<?php
// src/Integration/Tests/HttpPipelineIntegrationTest.php
// Integration test suite targeting the HTTP request-routing spine, traits, and middleware pipeline.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Http\Router;
use Zero\Http\Middleware\AuthMiddleware;
use Zero\Models\Site;
use Zero\Models\User;
use Zero\Support\Security;

echo "=== HTTP Pipeline & Spine Integration Tests ===\n";

// 1. Setup Mock Site Tenant and User Environments
echo "Initializing mock multi-tenant site and authenticated user...\n";
App::bootstrap();

$siteId = Security::uuidv7();
$mockSite = new Site([
    'id' => $siteId,
    'name' => 'Mock Pipeline Site',
    'domain' => 'pipeline.zero',
    'theme' => 'default',
    'enabled_modules' => json_encode(['blog', 'security'])
]);
$mockSite->save();

// Set active site context
App::setCurrentSite($mockSite);

$userId = Security::uuidv7();
$mockUser = new User([
    'id' => $userId,
    'site_id' => $siteId,
    'username' => 'testadmin',
    'email' => 'admin@pipeline.zero',
    'password_hash' => password_hash('Secret123', PASSWORD_BCRYPT),
    'role' => 'super_admin',
    'created_at' => gmdate('Y-m-d H:i:s'),
    'updated_at' => gmdate('Y-m-d H:i:s')
]);

App::setCurrentUser($mockUser);

// Assert the current context matches
assert_test(App::getCurrentSiteId() === $siteId, "Current site context successfully registered");
assert_test(App::getCurrentUserRole() === 'super_admin', "Current user is correctly marked as super_admin");

// 2. Test RendersViews Traits & Pagination Layout compilation
echo "Testing RendersViews pagination compilation...\n";

$paginationMeta = [
    'currentPage' => 2,
    'totalPages' => 5
];
$baseUrl = '/blog';
$queryParams = ['search' => 'purist', 'page' => 2];

$paginationHtml = App::renderPagination($paginationMeta, $baseUrl, $queryParams);

assert_test(str_contains($paginationHtml, 'Prev'), "Pagination includes 'Prev' navigation button");
assert_test(str_contains($paginationHtml, 'unified-pagination-wrapper'), "Pagination wraps correct unified class descriptor");
assert_test(str_contains($paginationHtml, 'search=purist'), "Pagination successfully preserves active GET query variables");

// 3. Test Access Control and Role Management Middleware (EnforcesAccessControl)
echo "Testing role middleware authorization checks...\n";

// A. Super Admin should pass any role checks cleanly
$passedSuperAdminCheck = false;
try {
    App::applyRoleMiddleware('editor');
    $passedSuperAdminCheck = true;
} catch (Exception $e) {
    // Should not reach here
}
assert_test($passedSuperAdminCheck === true, "applyRoleMiddleware cleanly allows Super Admins to pass");

// B. Mock normal user and verify middleware pass/fail logic
$mockEditor = new User([
    'id' => Security::uuidv7(),
    'site_id' => $siteId,
    'username' => 'testeditor',
    'role' => 'editor'
]);
App::setCurrentUser($mockEditor);

$passedEditorCheck = false;
try {
    App::applyRoleMiddleware('editor');
    $passedEditorCheck = true;
} catch (Exception $e) {
    // Should not reach here
}
assert_test($passedEditorCheck === true, "applyRoleMiddleware successfully permits editor role check");

// Revert back to Super Admin for subsequent tests
App::setCurrentUser($mockUser);

// 4. Test Router Dynamic Mapping and Matching End-to-End
echo "Testing HTTP Router dynamic matching and controller dispatching...\n";

// Register a mock custom controller class dynamically
class MockCustomTestController implements \Zero\Interfaces\Controller {
    public static $handledParam = null;
    public function handle($param) {
        self::$handledParam = $param;
    }
}

// Statically register mock route
Router::register('#^/api/v1/test-pipeline-endpoint/([a-zA-Z0-9]+)$#', MockCustomTestController::class, 'security');

$router = new Router();
$routeUri = '/api/v1/test-pipeline-endpoint/success987';

$routeHandled = $router->handle($routeUri);

assert_test($routeHandled === true, "Router successfully maps and matches registered regex route patterns");
assert_test(MockCustomTestController::$handledParam !== null, "Router successfully invokes matched controller handle() interface");
assert_test(MockCustomTestController::$handledParam[1] === 'success987', "Regex group parameter successfully parsed and routed to controller: " . MockCustomTestController::$handledParam[1]);

// 5. Test Router Module Activation Check (Bypass routing if module is disabled for tenant)
echo "Testing Router module activation enforcement...\n";

$disabledSite = new Site([
    'id' => Security::uuidv7(),
    'name' => 'Disabled Blog Site',
    'domain' => 'disabled-blog.zero',
    'theme' => 'default',
    'enabled_modules' => json_encode([]) // Blog module is disabled!
]);
$disabledSite->save();

App::setCurrentSite($disabledSite);

// Attempt to resolve a Blog API route mapped to posts list
class MockBlogController implements \Zero\Interfaces\Controller {
    public function handle($param) {}
}
Router::register('#^/api/v1/test-blog-endpoint$#', MockBlogController::class, 'blog');

$blogRouteHandled = $router->handle('/api/v1/test-blog-endpoint');
assert_test($blogRouteHandled === false, "Router correctly bypasses routing and treats as 404 if matching module is disabled for tenant");

// 6. Test AuthMiddleware handling under valid and invalid sessions
echo "Testing AuthMiddleware pass boundaries...\n";

// Set up mock session
App::ensureSession();
$_SESSION['user_id'] = $userId;
App::setCurrentUser($mockUser);
App::setCurrentSite($mockSite);

$middleware = new AuthMiddleware();
$middlewarePassed = false;

$middleware->handle(function() use (&$middlewarePassed) {
    $middlewarePassed = true;
});

assert_test($middlewarePassed === true, "AuthMiddleware allows valid authenticated sessions to proceed onto next request handlers");

// 7. Cleaning up DB tables
echo "Cleaning up mock site data...\n";
App::setCurrentSite(null);
$mockSite->forceDelete();
$disabledSite->forceDelete();

// 8. End-to-End Request Dispatch via App::handleRequest()
// We'll set up $_SERVER variables to trigger static CSS Bundle Controller and exit the subprocess naturally
echo "Executing end-to-end request dispatcher. Terminating subprocess successfully.\n";

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/assets/css/main-default.css';

App::handleRequest(APPLICATION_ROOT . '/public');
