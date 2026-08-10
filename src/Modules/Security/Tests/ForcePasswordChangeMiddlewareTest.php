<?php
// tests/ForcePasswordChangeMiddlewareTest.php
// Unit tests for ForcePasswordChangeMiddleware and ChangePasswordController components

require_once __DIR__ . '/bootstrap.php';

use Zero\Modules\Security\Middleware\ForcePasswordChangeMiddleware;
use Zero\Modules\Security\Controllers\ChangePasswordController;
use Zero\Interfaces\Controller;

echo "=== Force Password Change Security Component Tests ===\n";

// 1. Test ChangePasswordController instance
echo "Testing ChangePasswordController instantiation...\n";
$controller = new ChangePasswordController();
assert_test($controller instanceof Controller, "ChangePasswordController class is successfully instantiated and implements Controller interface");

// 2. Test ForcePasswordChangeMiddleware pass-through routes
echo "Testing ForcePasswordChangeMiddleware pass-through on bypass routes...\n";
$middleware = new ForcePasswordChangeMiddleware();

// Mock requested URI
$_SERVER['REQUEST_URI'] = '/admin/change-password';

$nextCalled = false;
$next = function() use (&$nextCalled) {
    $nextCalled = true;
    return true;
};

$middleware->handle($next);
assert_test($nextCalled, "Middleware successfully allows pass-through on the /admin/change-password endpoint");

$nextCalled = false;
$_SERVER['REQUEST_URI'] = '/admin/logout';
$middleware->handle($next);
assert_test($nextCalled, "Middleware successfully allows pass-through on the /admin/logout endpoint");

echo "Force Password Change Security component tests completed successfully!\n";
