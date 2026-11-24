<?php
// src/Models/Tests/AdminTenantScopingTest.php
// The generic admin model endpoints must not reach records owned by another tenant: a guessed or
// leaked primary key from site B, submitted while signed in to site A, is treated as not found by
// every edit/delete/restore/force-delete path (HTML form and JSON API alike).

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Database\DB;
use Zero\Models\Page;
use Zero\Support\Security;
use Zero\Support\TestRequest;

echo "=== Admin Tenant Scoping Tests ===\n";

$adminModules = ['security'];
$otherSiteId = Security::uuidv7();

/**
 * Fetch a page row regardless of soft-delete state.
 */
$pageRow = function (string $id): ?array {
    $row = DB::query('SELECT title, deleted_at FROM pages WHERE id = ?', [$id])->fetch();
    return $row ?: null;
};

/**
 * A page owned by some other tenant, planted alongside the request.
 */
$foreignPage = function (string $id, string $title) use ($otherSiteId): array {
    return [
        'id' => $id,
        'site_id' => $otherSiteId,
        'title' => $title,
        'slug' => 'foreign-' . \substr($id, -12),
        'type' => 'page',
    ];
};

// 1. Pure helper.
echo "Testing belongsToCurrentSite / findInCurrentSite...\n";
assert_test(Page::findInCurrentSite('') === null, "An empty id finds nothing");
assert_test(Page::findInCurrentSite(null) === null, "A null id finds nothing");

// 2. Edit through the HTML form.
echo "Testing a foreign page cannot be edited through the edit form...\n";
$id = Security::uuidv7();
TestRequest::post('/admin/edit/pages/' . $id, ['title' => 'Hijacked By Form'])
    ->onSite(['enabled_modules' => $adminModules])
    ->withPage($foreignPage($id, 'Foreign Form Original'))
    ->asUser(['username' => 'tenant_admin_1', 'role' => 'admin'])
    ->withCsrf()
    ->send();
assert_test(($pageRow($id)['title'] ?? null) === 'Foreign Form Original', "Foreign page title is unchanged after a form edit");

// 3. Edit through the JSON API.
echo "Testing a foreign page cannot be edited through the admin API...\n";
$id = Security::uuidv7();
$response = TestRequest::post('/api/v1/admin/models/pages/' . $id, ['title' => 'Hijacked By Api'])
    ->onSite(['enabled_modules' => $adminModules])
    ->withPage($foreignPage($id, 'Foreign Api Original'))
    ->asUser(['username' => 'tenant_admin_2', 'role' => 'admin'])
    ->withCsrf()
    ->send();
assert_test(strpos($response['stdout'], 'Record not found') !== false, "Admin API reports a foreign page as not found");
assert_test(($pageRow($id)['title'] ?? null) === 'Foreign Api Original', "Foreign page title is unchanged after an API edit");

// 4. Soft delete through the HTML form and the API.
echo "Testing a foreign page cannot be deleted...\n";
$id = Security::uuidv7();
TestRequest::post('/admin/delete/pages', ['id' => $id])
    ->onSite(['enabled_modules' => $adminModules])
    ->withPage($foreignPage($id, 'Foreign Delete Form'))
    ->asUser(['username' => 'tenant_admin_3', 'role' => 'admin'])
    ->withCsrf()
    ->send();
assert_test(($row = $pageRow($id)) !== null && $row['deleted_at'] === null, "Foreign page is not soft-deleted through the form");

$id = Security::uuidv7();
$response = TestRequest::post('/api/v1/admin/models/pages/' . $id)
    ->withServer(['REQUEST_METHOD' => 'DELETE'])
    ->onSite(['enabled_modules' => $adminModules])
    ->withPage($foreignPage($id, 'Foreign Delete Api'))
    ->asUser(['username' => 'tenant_admin_4', 'role' => 'admin'])
    ->withCsrf()
    ->send();
assert_test(($row = $pageRow($id)) !== null && $row['deleted_at'] === null, "Foreign page is not soft-deleted through the API");

// 5. Force delete (super_admin holds content.destructive) still cannot cross tenants.
echo "Testing a foreign page cannot be force-deleted...\n";
$id = Security::uuidv7();
TestRequest::post('/admin/force-delete/pages', ['id' => $id])
    ->onSite(['enabled_modules' => $adminModules])
    ->withPage($foreignPage($id, 'Foreign Force Delete'))
    ->asUser(['username' => 'tenant_root_1', 'role' => 'super_admin'])
    ->withCsrf()
    ->send();
assert_test($pageRow($id) !== null, "Foreign page row survives a force-delete attempt");

// 6. Same-tenant records still work.
echo "Testing same-tenant pages can still be edited...\n";
$id = Security::uuidv7();
$response = TestRequest::post('/api/v1/admin/models/pages/' . $id, [
        'title' => 'Local Edited',
        'omit_title' => 0, 'precedence' => 0, 'show_in_nav' => 0, 'exclude_from_search' => 0, 'status' => 'published',
    ])
    ->onSite(['enabled_modules' => $adminModules])
    ->withPage(['id' => $id, 'title' => 'Local Original', 'type' => 'page'])
    ->asUser(['username' => 'tenant_admin_5', 'role' => 'admin'])
    ->withCsrf()
    ->send();
assert_test(($pageRow($id)['title'] ?? null) === 'Local Edited', "A same-tenant page can still be edited through the API");

echo "Admin tenant scoping tests completed successfully!\n";
