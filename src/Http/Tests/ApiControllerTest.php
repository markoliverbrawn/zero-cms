<?php
// src/Http/Tests/ApiControllerTest.php
// Unit and integration tests for the base abstract ApiController class and API authentication mechanisms (Zero\Http\Controllers\ApiController)

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Models\Site;
use Zero\Models\User;
use Zero\Support\Security;

echo "=== ApiController Component Tests ===\n";

/**
 * Spawns an isolated PHP child process to execute API request branches, capturing output and exit codes.
 * Ensures parent DB context (including dynamic test db and tokens) are perfectly preserved.
 */
function exec_api_test(array $server, array $get = [], string $dbSetup = ''): array
{
    $code = '<?php
    require_once "' . APPLICATION_ROOT . '/src/Support/TestBootstrap.php";
    use Zero\Core\App;
    use Zero\Database\DB;
    use Zero\Http\Controllers\ApiController;
    use Zero\Support\Security;

    class ConcreteApiController extends ApiController {
        public function execute() {
            $userRow = $this->authenticate();
            echo "AUTH_SUCCESS: " . $userRow["username"];
        }
        public function executeRespond() {
            $this->respond(["msg" => "custom response"], 202);
        }
    }

    ' . $dbSetup . '

    $_SERVER = array_merge($_SERVER, ' . var_export($server, true) . ');
    $_GET = ' . var_export($get, true) . ';

    App::bootstrap();

    $action = $_GET["action"] ?? "auth";
    $controller = new ConcreteApiController();
    if ($action === "auth") {
        $controller->execute();
    } else {
        $controller->executeRespond();
    }
    ';

    $descriptorspec = [
        0 => ["pipe", "r"], // stdin
        1 => ["pipe", "w"], // stdout
        2 => ["pipe", "w"]  // stderr
    ];

    $process = proc_open("php", $descriptorspec, $pipes, APPLICATION_ROOT, $_ENV);

    if (is_resource($process)) {
        fwrite($pipes[0], $code);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'stdout' => $stdout,
            'stderr' => $stderr,
            'exit_code' => $exitCode
        ];
    }

    return ['stdout' => '', 'stderr' => 'Spawn failed', 'exit_code' => 1];
}

// Ensure database clean-slate for testing
App::bootstrap();
DB::query("DELETE FROM users WHERE username = 'api_tester_user'");
DB::query("DELETE FROM sites WHERE domain IN ('api-test.zero', 'another-api.zero')");

// 1. Setup raw database scripts for different test boundaries
$rawToken = 'test_secret_token_123';
$hashedToken = hash('sha256', $rawToken);

// Standard Database setup (inserts Site and active User with API Token)
$dbSetupValidUser = '
DB::query("DELETE FROM users WHERE username = \'api_tester_user\'");
DB::query("DELETE FROM sites WHERE domain = \'api-test.zero\'");

$siteId = Security::uuidv7();
DB::query("
    INSERT INTO sites (id, name, domain, theme, enabled_modules, created_at, updated_at)
    VALUES (?, \'API Test Site\', \'api-test.zero\', \'default\', \'[\"security\"]\', NOW(), NOW())
", [$siteId]);

$userId = Security::uuidv7();
DB::query("
    INSERT INTO users (id, site_id, username, email, password_hash, role, api_token, created_at, updated_at)
    VALUES (?, ?, \'api_tester_user\', \'api-user@test.zero\', \'hash\', \'editor\', ?, NOW(), NOW())
", [$userId, $siteId, "' . $hashedToken . '"]);
';

// Multi-tenant isolation Database setup (User belongs to Site B, requests Site A)
$dbSetupMultiTenantForbidden = '
DB::query("DELETE FROM users WHERE username = \'api_tester_user\'");
DB::query("DELETE FROM sites WHERE domain IN (\'api-test.zero\', \'another-api.zero\')");

$siteAId = Security::uuidv7();
DB::query("
    INSERT INTO sites (id, name, domain, theme, enabled_modules, created_at, updated_at)
    VALUES (?, \'API Test Site A\', \'api-test.zero\', \'default\', \'[\"security\"]\', NOW(), NOW())
", [$siteAId]);

$siteBId = Security::uuidv7();
DB::query("
    INSERT INTO sites (id, name, domain, theme, enabled_modules, created_at, updated_at)
    VALUES (?, \'API Test Site B\', \'another-api.zero\', \'default\', \'[\"security\"]\', NOW(), NOW())
", [$siteBId]);

$userId = Security::uuidv7();
DB::query("
    INSERT INTO users (id, site_id, username, email, password_hash, role, api_token, created_at, updated_at)
    VALUES (?, ?, \'api_tester_user\', \'api-user@test.zero\', \'hash\', \'editor\', ?, NOW(), NOW())
", [$userId, $siteBId, "' . $hashedToken . '"]);
';


// 2. Test authenticate() missing token
echo "Testing authenticate() with missing token (401)...\n";
$server1 = [
    'REQUEST_METHOD' => 'GET',
    'HTTP_HOST' => 'api-test.zero',
    'REMOTE_ADDR' => '127.0.0.1'
];
$res1 = exec_api_test($server1, ['action' => 'auth']);
assert_test(str_contains($res1['stdout'], 'Unauthorized: Missing API Key'), "Correctly rejects with 401 when no token header/parameter is passed");

// 3. Test authenticate() invalid token
echo "Testing authenticate() with invalid token (401)...\n";
$server2 = [
    'REQUEST_METHOD' => 'GET',
    'HTTP_HOST' => 'api-test.zero',
    'REMOTE_ADDR' => '127.0.0.1',
    'HTTP_AUTHORIZATION' => 'Bearer invalid_token_xyz'
];
// Site exists but token is incorrect
$res2 = exec_api_test($server2, ['action' => 'auth'], $dbSetupValidUser);
assert_test(str_contains($res2['stdout'], 'Unauthorized: Invalid API Key'), "Correctly rejects with 401 when invalid Bearer token is provided");

// 4. Test authenticate() successful authentication with Bearer Token
echo "Testing authenticate() with valid Bearer Token (200)...\n";
$server4 = [
    'REQUEST_METHOD' => 'GET',
    'HTTP_HOST' => 'api-test.zero',
    'REMOTE_ADDR' => '127.0.0.1',
    'HTTP_AUTHORIZATION' => 'Bearer ' . $rawToken
];
$res4 = exec_api_test($server4, ['action' => 'auth'], $dbSetupValidUser);

if (!str_contains($res4['stdout'], 'AUTH_SUCCESS: api_tester_user')) {
    echo "DEBUG INFO FOR TEST 4:\n";
    echo "STDOUT: " . $res4['stdout'] . "\n";
    echo "STDERR: " . $res4['stderr'] . "\n";
    echo "EXIT CODE: " . $res4['exit_code'] . "\n";
}

assert_test(str_contains($res4['stdout'], 'AUTH_SUCCESS: api_tester_user'), "Successfully authenticates and loads user details via Authorization Bearer token header");

// 5. Test authenticate() successful authentication with X-API-Key Header
echo "Testing authenticate() with valid X-API-Key Header (200)...\n";
$server5 = [
    'REQUEST_METHOD' => 'GET',
    'HTTP_HOST' => 'api-test.zero',
    'REMOTE_ADDR' => '127.0.0.1',
    'HTTP_X_API_KEY' => $rawToken
];
$res5 = exec_api_test($server5, ['action' => 'auth'], $dbSetupValidUser);
assert_test(str_contains($res5['stdout'], 'AUTH_SUCCESS: api_tester_user'), "Successfully authenticates and loads user details via HTTP_X_API_KEY header");

// 6. Test authenticate() successful authentication with Query String parameter
echo "Testing authenticate() with valid api_key Query Parameter (200)...\n";
$server6 = [
    'REQUEST_METHOD' => 'GET',
    'HTTP_HOST' => 'api-test.zero',
    'REMOTE_ADDR' => '127.0.0.1'
];
$res6 = exec_api_test($server6, ['action' => 'auth', 'api_key' => $rawToken], $dbSetupValidUser);
assert_test(str_contains($res6['stdout'], 'AUTH_SUCCESS: api_tester_user'), "Successfully authenticates and loads user details via api_key query string parameters");

// 7. Test authenticate() multi-tenant boundary checks (403 Forbidden)
echo "Testing authenticate() multi-tenant scoping boundary rejection (403)...\n";
$server7 = [
    'REQUEST_METHOD' => 'GET',
    'HTTP_HOST' => 'api-test.zero', // Request targeting Site A!
    'REMOTE_ADDR' => '127.0.0.1',
    'HTTP_AUTHORIZATION' => 'Bearer ' . $rawToken
];
$res7 = exec_api_test($server7, ['action' => 'auth'], $dbSetupMultiTenantForbidden);
assert_test(str_contains($res7['stdout'], 'Forbidden: API Key does not match active site tenant'), "Correctly rejects with 403 when User's site_id is not matching requested Site");

// 8. Test respond() JSON format output
echo "Testing respond() JSON output (202)...\n";
$res8 = exec_api_test($server1, ['action' => 'respond']);
assert_test(str_contains($res8['stdout'], '"msg": "custom response"'), "respond() correctly encodes data array into formatted JSON output string");
assert_test($res8['exit_code'] === 0, "respond() terminates execution cleanly with exit code 0");

// Clean up DB tables
DB::query("DELETE FROM users WHERE username = 'api_tester_user'");
DB::query("DELETE FROM sites WHERE domain IN ('api-test.zero', 'another-api.zero')");

echo "ApiController component tests completed successfully!\n";
