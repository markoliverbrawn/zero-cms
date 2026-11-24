<?php
// src/Models/Tests/UserSuperAdminEscalationTest.php
// Routed requests proving a site 'admin' (who holds users.manage) cannot reach platform-wide
// super_admin rights: not by creating a super_admin, not by promoting themselves, and not by
// editing or deleting an existing super_admin -- the ZeroCMS analogue of Silverstripe
// CVE-2026-91112 (delegated Security Admin escalating to full ADMIN). A super_admin keeps the
// ability to do all of these.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Database\DB;
use Zero\Models\User;
use Zero\Support\Security;
use Zero\Support\TestRequest;

echo "=== User super_admin Escalation Guard Tests ===\n";

$adminModules = ['security'];

/**
 * Fetch a user's stored role (null when no live row exists).
 */
$roleOf = function (string $column, string $value): ?string {
    $row = DB::query("SELECT role FROM users WHERE {$column} = ? AND deleted_at IS NULL LIMIT 1", [$value])->fetch();
    return $row ? $row['role'] : null;
};

// 1. An admin creating a brand-new super_admin is refused. The JSON API passes the submitted role
//    straight to the model, so it is the model-level guard that must stop it; the HTML form's
//    select field additionally coerces an option it did not offer, so it must never store one.
echo "Testing an admin cannot create a super_admin account...\n";

$response = TestRequest::post('/api/v1/admin/models/users', [
        'username' => 'escalation_api_super',
        'email' => 'escalation_api_super@example.test',
        'role' => 'super_admin',
    ])
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'escalation_admin_1', 'role' => 'admin'])
    ->withCsrf()
    ->send();

assert_test(
    strpos($response['stdout'], User::SUPER_ADMIN_PERMISSION) !== false,
    "Creating a super_admin through the admin API renders the access-denied page"
);
assert_test(
    $roleOf('username', 'escalation_api_super') === null,
    "No row is written when an admin creates a super_admin through the admin API"
);

TestRequest::post('/admin/edit/users/new', [
        'username' => 'escalation_form_super',
        'email' => 'escalation_form_super@example.test',
        'role' => 'super_admin',
    ])
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'escalation_admin_1b', 'role' => 'admin'])
    ->withCsrf()
    ->send();

assert_test(
    $roleOf('username', 'escalation_form_super') !== 'super_admin',
    "The HTML edit form never stores super_admin when submitted by an admin"
);

// 2. An admin may still create ordinary back-office accounts.
echo "Testing an admin can still create an editor account...\n";

TestRequest::post('/admin/edit/users/new', [
        'username' => 'escalation_new_editor',
        'email' => 'escalation_new_editor@example.test',
        'role' => 'editor',
    ])
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'escalation_admin_2', 'role' => 'admin'])
    ->withCsrf()
    ->send();

assert_test(
    $roleOf('username', 'escalation_new_editor') === 'editor',
    "An admin can create an editor account"
);

// 3. An admin promoting themselves is refused, and their stored role is unchanged.
echo "Testing an admin cannot promote themselves to super_admin...\n";

$selfId = Security::uuidv7();
$response = TestRequest::post('/api/v1/admin/models/users/' . $selfId, [
        'username' => 'escalation_admin_self',
        'email' => 'escalation_admin_self@example.test',
        'role' => 'super_admin',
    ])
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['id' => $selfId, 'username' => 'escalation_admin_self', 'role' => 'admin'])
    ->withCsrf()
    ->send();

assert_test(
    strpos($response['stdout'], User::SUPER_ADMIN_PERMISSION) !== false,
    "Self-promotion through the admin API renders the access-denied page"
);
assert_test(
    $roleOf('id', $selfId) === 'admin',
    "The admin's stored role is still 'admin' after the attempted self-promotion"
);

// 4. An admin editing an existing super_admin (e.g. to swap in their own email and then reset
//    the password) is refused.
echo "Testing an admin cannot edit an existing super_admin...\n";

$superId = Security::uuidv7();
$response = TestRequest::post('/admin/edit/users/' . $superId, [
        'username' => 'escalation_target_super',
        'email' => 'attacker@example.test',
        'role' => 'editor',
    ])
    ->onSite(['enabled_modules' => $adminModules])
    ->withAccount(['id' => $superId, 'username' => 'escalation_target_super', 'email' => 'escalation_target_super@example.test', 'role' => 'super_admin'])
    ->asUser(['username' => 'escalation_admin_3', 'role' => 'admin'])
    ->withCsrf()
    ->send();

$superRow = DB::query('SELECT role, email FROM users WHERE id = ?', [$superId])->fetch();
assert_test(
    strpos($response['stdout'], User::SUPER_ADMIN_PERMISSION) !== false,
    "Editing a super_admin as an admin renders the access-denied page"
);
assert_test(
    $superRow && $superRow['role'] === 'super_admin' && $superRow['email'] === 'escalation_target_super@example.test',
    "The super_admin's role and email are unchanged"
);

// 5. An admin deleting an existing super_admin is refused.
echo "Testing an admin cannot delete an existing super_admin...\n";

$superDeleteId = Security::uuidv7();
TestRequest::post('/admin/delete/users', ['id' => $superDeleteId])
    ->onSite(['enabled_modules' => $adminModules])
    ->withAccount(['id' => $superDeleteId, 'username' => 'escalation_delete_super', 'role' => 'super_admin'])
    ->asUser(['username' => 'escalation_admin_4', 'role' => 'admin'])
    ->withCsrf()
    ->send();

assert_test(
    $roleOf('id', $superDeleteId) === 'super_admin',
    "The super_admin row is not soft-deleted by an admin"
);

// 6. A super_admin can still create another super_admin.
echo "Testing a super_admin can create a super_admin...\n";

TestRequest::post('/admin/edit/users/new', [
        'username' => 'escalation_granted_super',
        'email' => 'escalation_granted_super@example.test',
        'role' => 'super_admin',
    ])
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'escalation_root', 'role' => 'super_admin'])
    ->withCsrf()
    ->send();

assert_test(
    $roleOf('username', 'escalation_granted_super') === 'super_admin',
    "A super_admin can grant the super_admin role"
);

// 7. The role picker hides super_admin from admins but offers it to super_admins.
echo "Testing the role picker only offers super_admin to super_admins...\n";

$adminForm = TestRequest::get('/admin/edit/users/new')
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'escalation_admin_5', 'role' => 'admin'])
    ->send();
$superForm = TestRequest::get('/admin/edit/users/new')
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'escalation_root_2', 'role' => 'super_admin'])
    ->send();

assert_test(
    strpos($adminForm['stdout'], 'value="super_admin"') === false,
    "An admin's user form does not offer the super_admin role"
);
assert_test(
    strpos($superForm['stdout'], 'value="super_admin"') !== false,
    "A super_admin's user form offers the super_admin role"
);

echo "User super_admin escalation guard tests completed successfully!\n";
