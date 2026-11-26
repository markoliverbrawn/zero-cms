<?php
// src/Modules/Admin/Tests/PasswordResetPoisoningTest.php
// Password-reset links must always point at the tenant's own registered domain (never a
// request-supplied Host / X-Forwarded-Host), reset requests must only reach accounts that belong to
// the tenant, and a reset token must only redeem on the tenant that issued it -- and /reset (which
// signs the user straight in) must never redeem a back-office account's token.

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Database\DB;
use Zero\Modules\Admin\Controllers\FrontendForgotController;
use Zero\Modules\Admin\Controllers\FrontendResetController;
use Zero\Support\Security;
use Zero\Support\TestRequest;

echo "=== Password Reset Poisoning Tests ===\n";

$siteDomain = 'reset-' . \bin2hex(\random_bytes(4)) . '.zero';

/**
 * Whether a user's stored password is still the fixture default.
 */
$passwordUnchanged = function (string $userId): bool {
    $row = DB::query('SELECT password_hash FROM users WHERE id = ?', [$userId])->fetch();
    return $row && \password_verify('Secret123', $row['password_hash']);
};

// 1. A forged X-Forwarded-Host never reaches the emailed link.
echo "Testing the admin reset link ignores a forged X-Forwarded-Host...\n";

$response = TestRequest::post('/admin/forgot', ['username' => 'reset_poison_admin'])
    ->onSite(['domain' => $siteDomain])
    ->withUser(['username' => 'reset_poison_admin', 'email' => 'reset_poison_admin@example.test', 'role' => 'admin'])
    // The tenant still resolves (host matching retries on the part before ':'), while a browser
    // would treat everything before '@' as userinfo and send the token to evil.example.
    ->withServer(['HTTP_X_FORWARDED_HOST' => $siteDomain . ':@evil.example'])
    ->withCsrf()
    ->captureEmails()
    ->send();

$body = $response['emails'][0]['html_body'] ?? '';
assert_test(\count($response['emails']) === 1, "Exactly one reset email is sent to a valid admin");
assert_test(\strpos($body, 'evil.example') === false, "The forged X-Forwarded-Host does not appear in the reset email");
assert_test(\strpos($body, '://' . $siteDomain . '/admin/reset?token=') !== false, "The reset link is built on the site's registered domain");

echo "Testing the frontend reset link ignores a forged X-Forwarded-Host...\n";

$response = TestRequest::post('/forgot', ['username' => 'reset_poison_member'])
    ->withRoute('#^/forgot$#', FrontendForgotController::class)
    ->onSite(['domain' => $siteDomain . 'm'])
    ->withUser(['username' => 'reset_poison_member', 'email' => 'reset_poison_member@example.test', 'role' => 'member'])
    ->withServer(['HTTP_X_FORWARDED_HOST' => $siteDomain . 'm:@evil.example'])
    ->withCsrf()
    ->captureEmails()
    ->send();

$body = $response['emails'][0]['html_body'] ?? '';
assert_test(\count($response['emails']) === 1, "Exactly one reset email is sent to a valid member");
assert_test(\strpos($body, 'evil.example') === false, "The forged X-Forwarded-Host does not appear in the frontend reset email");

// 2. A tenant's admin forgot form cannot reach another tenant's (non-super) accounts.
echo "Testing /admin/forgot does not reach another tenant's accounts...\n";

$response = TestRequest::post('/admin/forgot', ['username' => 'reset_foreign_admin'])
    ->onSite()
    ->withAccount(['username' => 'reset_foreign_admin', 'email' => 'reset_foreign_admin@example.test', 'role' => 'admin', 'site_id' => Security::uuidv7()])
    ->withCsrf()
    ->captureEmails()
    ->send();

assert_test($response['emails'] === [], "No reset email is sent for another tenant's admin");

// 3. A token issued by another tenant does not redeem here.
echo "Testing a reset token does not redeem on another tenant...\n";

$foreignSite = Security::uuidv7();
$foreignUser = Security::uuidv7();
$token = \bin2hex(\random_bytes(16));

TestRequest::post('/admin/reset', ['token' => $token, 'password' => 'NewPassword123', 'confirm_password' => 'NewPassword123'])
    ->onSite()
    ->withAccount(['id' => $foreignUser, 'username' => 'reset_foreign_target', 'role' => 'admin', 'site_id' => $foreignSite])
    ->withPasswordReset(['token' => $token, 'user_id' => $foreignUser, 'site_id' => $foreignSite])
    ->withCsrf()
    ->send();

assert_test($passwordUnchanged($foreignUser), "A foreign tenant's token does not change the password via /admin/reset");

// 4. /reset (auto sign-in) refuses back-office tokens even on the issuing tenant.
echo "Testing /reset refuses back-office accounts...\n";

$staffUser = Security::uuidv7();
$token = \bin2hex(\random_bytes(16));

TestRequest::post('/reset', ['token' => $token, 'password' => 'NewPassword123', 'confirm_password' => 'NewPassword123'])
    ->withRoute('#^/reset$#', FrontendResetController::class)
    ->onSite()
    ->withAccount(['id' => $staffUser, 'username' => 'reset_staff_target', 'role' => 'super_admin'])
    ->withPasswordReset(['token' => $token, 'user_id' => $staffUser])
    ->withCsrf()
    ->send();

assert_test($passwordUnchanged($staffUser), "/reset does not redeem a super_admin's token");

// 5. Same-tenant member reset still works.
echo "Testing a same-tenant member reset still works...\n";

$memberUser = Security::uuidv7();
$token = \bin2hex(\random_bytes(16));

TestRequest::post('/reset', ['token' => $token, 'password' => 'NewPassword123', 'confirm_password' => 'NewPassword123'])
    ->withRoute('#^/reset$#', FrontendResetController::class)
    ->onSite()
    ->withAccount(['id' => $memberUser, 'username' => 'reset_member_target', 'role' => 'member'])
    ->withPasswordReset(['token' => $token, 'user_id' => $memberUser])
    ->withCsrf()
    ->send();

assert_test(!$passwordUnchanged($memberUser), "A member's own-tenant token resets their password via /reset");

// 6. A site's email_override must never receive a global super_admin's reset link.
echo "Testing /admin/forgot skips super_admins on a site with email_override...\n";

$response = TestRequest::post('/admin/forgot', ['username' => 'reset_override_root'])
    ->onSite(['email_override' => 'shared-demo-inbox@example.test'])
    ->withAccount(['username' => 'reset_override_root', 'email' => 'root@example.test', 'role' => 'super_admin', 'site_id' => null])
    ->withCsrf()
    ->captureEmails()
    ->send();

assert_test($response['emails'] === [], "No super_admin reset email is sent from a site with email_override");

$response = TestRequest::post('/admin/forgot', ['username' => 'reset_override_admin'])
    ->onSite(['email_override' => 'shared-demo-inbox@example.test'])
    ->withUser(['username' => 'reset_override_admin', 'email' => 'reset_override_admin@example.test', 'role' => 'admin'])
    ->withCsrf()
    ->captureEmails()
    ->send();

assert_test(\count($response['emails']) === 1, "The site's own admin still gets a reset email on a site with email_override");

// 7. Member reset applies the password policy.
echo "Testing /reset rejects a weak password...\n";

$weakUser = Security::uuidv7();
$token = \bin2hex(\random_bytes(16));

TestRequest::post('/reset', ['token' => $token, 'password' => 'abcdef', 'confirm_password' => 'abcdef'])
    ->withRoute('#^/reset$#', FrontendResetController::class)
    ->onSite()
    ->withAccount(['id' => $weakUser, 'username' => 'reset_weak_target', 'role' => 'member'])
    ->withPasswordReset(['token' => $token, 'user_id' => $weakUser])
    ->withCsrf()
    ->send();

assert_test($passwordUnchanged($weakUser), "/reset does not accept a 6-character password");

echo "Password reset poisoning tests completed successfully!\n";
