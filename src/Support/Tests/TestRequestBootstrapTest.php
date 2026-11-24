<?php
// src/Support/Tests/TestRequestBootstrapTest.php
// TestRequest::useBootstrap() lets a host project boot each child request with its own test
// bootstrap, so routes registered there (a sub-project's modules) are reachable from TestRequest.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Support\TestRequest;

echo "=== TestRequest Host Bootstrap Tests ===\n";

// A stand-in for a sub-project's tests/bootstrap.php: core's bootstrap plus one extra route whose
// controller only exists once this file has run.
$dir = \sys_get_temp_dir() . '/zero-host-bootstrap-' . \bin2hex(\random_bytes(4));
\mkdir($dir);
$bootstrap = $dir . '/bootstrap.php';
\file_put_contents($bootstrap, '<?php
require_once ' . \var_export(APPLICATION_ROOT . '/src/Support/TestBootstrap.php', true) . ';
final class HostBootstrapProbeController implements \Zero\Interfaces\Controller
{
    public function handle($param): void
    {
        echo "host-bootstrap-route-reached";
    }
}
\Zero\Http\Router::register("#^/host-bootstrap-probe$#", HostBootstrapProbeController::class);
');

echo "Testing the default bootstrap does not know host routes...\n";
$response = TestRequest::get('/host-bootstrap-probe')->onSite()->send();
assert_test(\strpos($response['stdout'], 'host-bootstrap-route-reached') === false, "Without useBootstrap() the host route is not registered");

echo "Testing useBootstrap() boots children with the host bootstrap...\n";
TestRequest::useBootstrap($bootstrap);
$response = TestRequest::get('/host-bootstrap-probe')->onSite()->send();
assert_test(\strpos($response['stdout'], 'host-bootstrap-route-reached') !== false, "With useBootstrap() the host route is reached");

echo "Testing useBootstrap(null) restores the default...\n";
TestRequest::useBootstrap(null);
$response = TestRequest::get('/host-bootstrap-probe')->onSite()->send();
assert_test(\strpos($response['stdout'], 'host-bootstrap-route-reached') === false, "useBootstrap(null) restores core's bootstrap");

echo "Testing a missing bootstrap file is refused...\n";
$threw = false;
try {
    TestRequest::useBootstrap($dir . '/missing.php');
} catch (\InvalidArgumentException $e) {
    $threw = true;
}
assert_test($threw, "useBootstrap() rejects a path that does not exist");

\unlink($bootstrap);
\rmdir($dir);

echo "TestRequest host bootstrap tests completed successfully!\n";
