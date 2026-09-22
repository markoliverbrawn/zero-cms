<?php
// tests/ContentSecurityPolicyMiddlewareTest.php
// Unit tests for ContentSecurityPolicyMiddleware component

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Modules\Security\Middleware\ContentSecurityPolicyMiddleware;

echo "=== Content Security Policy Middleware Component Tests ===\n";

// 1. Test Instantiation
echo "Testing middleware instantiation...\n";
$middleware = new ContentSecurityPolicyMiddleware();
assert_test($middleware instanceof ContentSecurityPolicyMiddleware, "ContentSecurityPolicyMiddleware class is successfully instantiated");

// 2. Test headers delegation completes
echo "Testing middleware delegate execution...\n";
$nextCalled = false;
$next = function() use (&$nextCalled) {
    $nextCalled = true;
    return true;
};

$middleware->handle($next);
assert_test($nextCalled, "Middleware handle execution successfully completes and calls next delegate");

// 3. Test core baseline directives are present with no module additions
echo "Testing core baseline CSP directives...\n";
$baselineCsp = $middleware->buildCsp('test-nonce', false);
assert_test(str_contains($baselineCsp, "default-src 'self';"), "Baseline CSP includes default-src 'self'");
assert_test(str_contains($baselineCsp, "'nonce-test-nonce'"), "Baseline CSP includes the request nonce");
assert_test(str_contains($baselineCsp, "object-src 'none';"), "Baseline CSP includes object-src 'none'");
assert_test(!str_contains($baselineCsp, 'upgrade-insecure-requests'), "Baseline CSP omits upgrade-insecure-requests over plain HTTP");
assert_test(str_contains($middleware->buildCsp('test-nonce', true), 'upgrade-insecure-requests;'), "CSP includes upgrade-insecure-requests over HTTPS");

// 4. Test modules can widen a directive via App::registerCspSource()
echo "Testing module-registered CSP source additions...\n";
App::registerCspSource('script-src', 'https://js.stripe.com');
App::registerCspSource('connect-src', 'https://api.stripe.com');

$extendedCsp = $middleware->buildCsp('test-nonce', false);
assert_test(str_contains($extendedCsp, 'script-src') && str_contains($extendedCsp, 'https://js.stripe.com'), "Module-registered script-src source is present in the CSP header");
assert_test(str_contains($extendedCsp, 'connect-src') && str_contains($extendedCsp, 'https://api.stripe.com'), "Module-registered connect-src source is present in the CSP header");
assert_test(str_contains($extendedCsp, "script-src 'self' 'nonce-test-nonce' 'unsafe-inline' https://js.stripe.com;"), "Core baseline script-src sources are preserved alongside the module addition");

echo "Content Security Policy Middleware component tests completed successfully!\n";
