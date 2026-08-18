<?php
// src/Integration/Tests/HandlesRequestsCoverageTest.php
// Direct sub-process integration tests to maximize code coverage for App::handleRequest() (Zero\Core\Concerns\HandlesRequests)

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Models\Site;
use Zero\Support\Security;

echo "=== HandlesRequests Subprocess Coverage Tests ===\n";

/**
 * Spawns an isolated PHP child process to execute a mock request, capturing output and exit status.
 * Preserves the parent process's environment (including isolated test DB names and tokens).
 */
function exec_mock_request(string $method, string $uri, string $host, string $dbSetup = ''): array
{
    // Note: We do not define APPLICATION_ROOT here as TestBootstrap.php handles it.
    $code = '<?php
    require_once "' . APPLICATION_ROOT . '/src/Support/TestBootstrap.php";
    use Zero\Core\App;
    use Zero\Database\DB;
    use Zero\Models\Site;
    use Zero\Models\Page;
    use Zero\Support\Security;

    // Run custom database setup before request handling (runs after DB truncation in TestBootstrap)
    ' . $dbSetup . '

    $_SERVER["REQUEST_METHOD"] = "' . $method . '";
    $_SERVER["REQUEST_URI"] = "' . $uri . '";
    $_SERVER["HTTP_HOST"] = "' . $host . '";

    App::bootstrap();

    App::handleRequest(APPLICATION_ROOT . "/public");
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

// 1. Branch: Homepage "/" when site has no homepage page registered
echo "Testing Homepage '/' with no homepage page record...\n";

$dbSetup1 = '
$siteId = Security::uuidv7();
DB::query("
    INSERT INTO sites (id, name, domain, theme, enabled_modules, created_at, updated_at)
    VALUES (?, \'Handles Test Site\', \'handles-test.zero\', \'default\', \'[\"security\"]\', NOW(), NOW())
", [$siteId]);
';

$res1 = exec_mock_request('GET', '/', 'handles-test.zero', $dbSetup1);
assert_test(str_contains($res1['stdout'], 'Homepage not found'), "Returns 'Homepage not found' when no home page exists in DB");
assert_test($res1['exit_code'] === 0, "Subprocess exits with code 0 on homepage 404 branch");

// 2. Branch: Homepage "/" with standard homepage page registered (renders default view template)
echo "Testing Homepage '/' with standard homepage page...\n";

$dbSetup2 = '
$siteId = Security::uuidv7();
DB::query("
    INSERT INTO sites (id, name, domain, theme, enabled_modules, created_at, updated_at)
    VALUES (?, \'Handles Test Site\', \'handles-test.zero\', \'default\', \'[\"security\"]\', NOW(), NOW())
", [$siteId]);

$pageId = Security::uuidv7();
DB::query("
    INSERT INTO pages (id, site_id, title, slug, content, status, type, created_at, updated_at)
    VALUES (?, ?, \'Mock Homepage\', \'home-handles-test\', \'[]\', \'published\', \'post\', NOW(), NOW())
", [$pageId, $siteId]);

DB::query("UPDATE sites SET homepage_id = ? WHERE id = ?", [$pageId, $siteId]);
';

$res2 = exec_mock_request('GET', '/', 'handles-test.zero', $dbSetup2);
assert_test(str_contains($res2['stdout'], 'Mock Homepage'), "Renders standard homepage view content successfully");

// 3. Branch: Homepage "/" with a custom controller registered but module is disabled
echo "Testing Homepage '/' with a custom controller belonging to a disabled module...\n";

$dbSetup3 = '
$siteId = Security::uuidv7();
DB::query("
    INSERT INTO sites (id, name, domain, theme, enabled_modules, created_at, updated_at)
    VALUES (?, \'Handles Test Site\', \'handles-test.zero\', \'default\', \'[\"security\"]\', NOW(), NOW())
", [$siteId]);

$pageId = Security::uuidv7();
DB::query("
    INSERT INTO pages (id, site_id, title, slug, content, status, type, controller, created_at, updated_at)
    VALUES (?, ?, \'Mock Homepage\', \'home-handles-test\', \'[]\', \'published\', \'post\', ?, NOW(), NOW())
", [$pageId, $siteId, \'Zero\\\\Modules\\\\Shop\\\\Controllers\\\\ShopHomeController\']);

DB::query("UPDATE sites SET homepage_id = ? WHERE id = ?", [$pageId, $siteId]);
';

$res3 = exec_mock_request('GET', '/', 'handles-test.zero', $dbSetup3);
assert_test(str_contains($res3['stdout'], 'Homepage module is disabled'), "Rejects homepage routing with 'Homepage module is disabled' if module is disabled for tenant");

// 4. Branch: Static file fallback (serving physical assets on disk)
echo "Testing Static File Fallback...\n";

$res4 = exec_mock_request('GET', '/assets/favicons/default.svg', 'handles-test.zero');
assert_test(str_contains($res4['stdout'], '<svg'), "Static file fallback successfully serves requested physical files (SVG content resolved)");

// 5. Branch: Direct 404 fallback (nonexistent file/route)
echo "Testing Direct 404 Fallback...\n";

$res5 = exec_mock_request('GET', '/nonexistent-static-file-handles-test.xyz', 'handles-test.zero');
assert_test(str_contains($res5['stdout'], 'Not found'), "Returns 'Not found' for completely unregistered static assets");

echo "HandlesRequests subprocess coverage tests completed successfully!\n";
