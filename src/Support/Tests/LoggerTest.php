<?php
// tests/LoggerTest.php
// Unit tests for dynamic central Audit Logger (Zero\Support\Logger)

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Support\Logger;
use Zero\Database\DB;

echo "=== Logger Component Tests ===\n";

// 1. Test dispatching an audit log
echo "Dispatching mock audit log entry...\n";
$testUserId = 'user-id-abc-123';
$testAction = 'test_create_record';
$testObjectType = 'pages';
$testObjectId = 'mock-page-id-789';
$testMeta = [
    'title' => 'My Tested Page Title',
    'ip_address' => '127.0.0.1'
];

Logger::log($testUserId, $testAction, $testObjectType, $testObjectId, $testMeta);

// 2. Fetch from database and verify stored values
echo "Retrieving and verifying audit log row...\n";
$logRow = DB::query("
    SELECT * FROM audit_logs 
    WHERE user_id = ? AND action = ? AND object_type = ? AND object_id = ? 
    ORDER BY created_at DESC 
    LIMIT 1
", [$testUserId, $testAction, $testObjectType, $testObjectId])->fetch();

assert_test($logRow !== false, "Logger successfully inserted audit trail record in the database");
assert_test($logRow['user_id'] === $testUserId, "Logs correct triggering user_id");
assert_test($logRow['action'] === $testAction, "Logs correct action string trigger");
assert_test($logRow['object_type'] === $testObjectType, "Logs correct target model table identifier");
assert_test($logRow['object_id'] === $testObjectId, "Logs correct target record database id");

$decodedMeta = json_decode($logRow['meta'] ?? '', true);
assert_test(is_array($decodedMeta), "Successfully encodes and saves meta fields as a valid JSON string");
assert_test($decodedMeta['title'] === 'My Tested Page Title', "Correctly preserves encoded title metadata values");
assert_test($decodedMeta['ip_address'] === '127.0.0.1', "Correctly preserves nested metadata context variables");

// Clean up test logs
DB::query("DELETE FROM audit_logs WHERE user_id = ?", [$testUserId]);

echo "Logger component tests completed.\n\n";
