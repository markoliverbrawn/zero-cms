<?php
// tests/ContentSecurityPolicyMiddlewareTest.php
// Unit tests for ContentSecurityPolicyMiddleware component

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

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

echo "Content Security Policy Middleware component tests completed successfully!\n";
