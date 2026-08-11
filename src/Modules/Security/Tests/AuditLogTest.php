<?php
// tests/AuditLogTest.php
// Unit tests for the AuditLog active record model

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Modules\Security\Models\AuditLog;
use Zero\Interfaces\Model as ModelInterface;

echo "=== AuditLog Model Component Tests ===\n";

// 1. Test Instantiation
echo "Testing AuditLog model instantiation...\n";
$log = new AuditLog();
assert_test($log instanceof ModelInterface, "AuditLog class successfully implements Model interface");

// 2. Test Configuration Scheme
echo "Testing model configuration rules...\n";
$config = AuditLog::getConfig();
assert_test(is_array($config), "getConfig returns a valid configuration array");
assert_test(isset($config['action']), "Configuration scheme contains 'action' field");
assert_test(isset($config['user_id']), "Configuration scheme contains 'user_id' field");
assert_test(isset($config['meta']), "Configuration scheme contains 'meta' field");

// 3. Test Custom Link Label Override
echo "Testing custom action link label overrides...\n";
assert_test(method_exists(AuditLog::class, 'getEditLabel'), "AuditLog class implements getEditLabel method");
assert_test(AuditLog::getEditLabel() === 'View', "AuditLog::getEditLabel returns 'View' label");

echo "AuditLog model component tests completed successfully!\n";
