<?php
// src/Modules/Admin/Tests/AdminApiBackofficeAccessTest.php
// The /api/v1/admin/* JSON endpoints are staff-only. A signed-in account without backoffice.access
// (a self-registered 'member') has a session and a CSRF token, but must be refused by every one of
// them -- previously AdminApiControllerBase only checked that a session existed.

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Database\DB;
use Zero\Support\TestRequest;

echo "=== Admin API Back-office Access Tests ===\n";

$adminModules = ['security'];
$pageFields = ['omit_title' => 0, 'precedence' => 0, 'show_in_nav' => 0, 'exclude_from_search' => 0, 'status' => 'published'];

$pageCount = function (string $title): int {
    return (int) DB::query('SELECT COUNT(*) AS c FROM pages WHERE title = ?', [$title])->fetch()['c'];
};

// 1. A member is refused by the model API and nothing is written.
echo "Testing a member cannot create pages through the admin API...\n";

$response = TestRequest::post('/api/v1/admin/models/pages', $pageFields + ['title' => 'Member Api Page'])
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'api_member_1', 'role' => 'member'])
    ->withCsrf()
    ->send();

assert_test(strpos($response['stdout'], 'Back-office access required') !== false, "The admin model API refuses a member");
assert_test($pageCount('Member Api Page') === 0, "No page is written by a member");

// 2. Every other admin API controller is behind the same gate.
echo "Testing other admin APIs refuse a member...\n";

foreach (['/api/v1/admin/files', '/api/v1/admin/preferences', '/api/v1/admin/ai/generate-summary', '/api/v1/admin/block-preview'] as $uri) {
    $response = TestRequest::post($uri, [])
        ->onSite(['enabled_modules' => $adminModules])
        ->asUser(['username' => 'api_member_' . \md5($uri), 'role' => 'member'])
        ->withCsrf()
        ->send();

    assert_test(strpos($response['stdout'], 'Back-office access required') !== false, "POST {$uri} refuses a member");
}

// 3. Send-welcome requires user management.
echo "Testing send-welcome requires users.manage...\n";

$response = TestRequest::post('/api/v1/user/send-welcome', [])
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'api_editor_welcome', 'role' => 'editor'])
    ->send();

assert_test(strpos($response['stdout'], 'user management permission required') !== false, "An editor cannot trigger welcome emails");

// 4. Staff still get through.
echo "Testing an editor can still create pages through the admin API...\n";

TestRequest::post('/api/v1/admin/models/pages', $pageFields + ['title' => 'Editor Api Page'])
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'api_editor_1', 'role' => 'editor'])
    ->withCsrf()
    ->send();

assert_test($pageCount('Editor Api Page') === 1, "An editor can still create a page through the admin API");

echo "Admin API back-office access tests completed successfully!\n";
