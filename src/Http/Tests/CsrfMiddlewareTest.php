<?php
// src/Http/Tests/CsrfMiddlewareTest.php
// Unit tests for CsrfMiddleware's pass-through behavior. Its failure branches call exit() after
// writing the response, which would kill this test subprocess -- like every other exit()-based
// middleware/controller test in this suite, this only exercises the paths that call $next().

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Http\Middleware\CsrfMiddleware;
use Zero\Support\Security;

echo "=== CSRF Middleware Component Tests ===\n";

App::ensureSession();
$middleware = new CsrfMiddleware();
$validToken = Security::csrfToken();

// 1. Non-state-changing methods skip the CSRF check entirely
echo "Testing GET requests bypass CSRF verification...\n";
$_SERVER['REQUEST_METHOD'] = 'GET';
$nextCalled = false;
$next = function () use (&$nextCalled) {
    $nextCalled = true;
    return true;
};
$middleware->handle($next);
assert_test($nextCalled, "GET request passes through without a CSRF token");

// 2. A valid token in the POST body (plain <form> submission) passes through
echo "Testing POST requests with a valid form-body token pass through...\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['csrf'] = $validToken;
$nextCalled = false;
$middleware->handle($next);
assert_test($nextCalled, 'POST request with a valid $_POST[csrf] token passes through');
unset($_POST['csrf']);

// 3. A valid token in the X-CSRF-Token header (AJAX/fetch call) also passes through
echo "Testing POST requests with a valid X-CSRF-Token header pass through...\n";
$_SERVER['HTTP_X_CSRF_TOKEN'] = $validToken;
$nextCalled = false;
$middleware->handle($next);
assert_test($nextCalled, "POST request with a valid X-CSRF-Token header passes through");
unset($_SERVER['HTTP_X_CSRF_TOKEN']);

echo "CSRF Middleware component tests completed successfully!\n";
