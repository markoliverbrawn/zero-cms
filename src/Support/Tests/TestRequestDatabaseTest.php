<?php
// src/Support/Tests/TestRequestDatabaseTest.php
// Regression test: a TestRequest child process must use the same isolated test database as the test
// that spawned it, so fixture rows and anything the request writes are visible to the parent's
// assertions (previously the child re-suffixed DB_NAME, e.g. cms_test_1 -> cms_test_1_test_1).

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Database\DB;
use Zero\Support\TestRequest;

echo "=== TestRequest Database Isolation Tests ===\n";

echo "Testing the child request shares the parent's test database...\n";
$domain = 'shared-db-' . \bin2hex(\random_bytes(4)) . '.zero';

$response = TestRequest::get('/admin/login')
    ->onSite(['domain' => $domain])
    ->send();

assert_test($response['exit_code'] === 0, "Child request completes without a fatal error");

$row = DB::query('SELECT id FROM sites WHERE domain = ?', [$domain])->fetch(\PDO::FETCH_ASSOC);
assert_test($row !== false, "Site fixture created by the child request is visible to the parent");

assert_test(
    \substr_count((string)\getenv('DB_NAME'), '_test') === 1,
    "DB_NAME carries the _test suffix exactly once"
);

DB::query('DELETE FROM sites WHERE domain = ?', [$domain]);
