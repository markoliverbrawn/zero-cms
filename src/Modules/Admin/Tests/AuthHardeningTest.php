<?php
// src/Modules/Admin/Tests/AuthHardeningTest.php
// Regression tests for the 2026-11-25 security review: deleted users can no longer sign in or keep
// a session, post-login redirects stay on the site, and Google sign-in requires a verified email.

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Modules\Admin\Controllers\GoogleAuthController;
use Zero\Support\Security;
use Zero\Support\TestRequest;

echo "=== Auth Hardening Tests ===\n";

$adminModules = ['security'];

echo "Testing post-login redirect targets stay on the site...\n";
$fallback = '/admin/dashboard';
assert_test(Security::localRedirectTarget('/admin/pages?status=draft', $fallback) === '/admin/pages?status=draft', "A local path with a query string is kept");
assert_test(Security::localRedirectTarget('//evil.example/admin/dashboard', $fallback) === $fallback, "A scheme-relative URL is replaced by the fallback");
assert_test(Security::localRedirectTarget('/\\evil.example/admin', $fallback) === $fallback, "A backslash path (read by browsers as //host) is replaced");
assert_test(Security::localRedirectTarget('https://evil.example/', $fallback) === $fallback, "An absolute URL is replaced");
assert_test(Security::localRedirectTarget("/admin\r\nSet-Cookie: x=1", $fallback) === $fallback, "A path with control characters is replaced");
assert_test(Security::localRedirectTarget('admin/dashboard', $fallback) === $fallback, "A relative path without a leading slash is replaced");
assert_test(Security::localRedirectTarget(null, $fallback) === $fallback && Security::localRedirectTarget(['/x'], $fallback) === $fallback, "Missing or non-string targets fall back");

echo "Testing Google profiles must carry a verified email...\n";
assert_test(GoogleAuthController::isVerifiedProfile(['email' => 'a@example.test', 'email_verified' => true]), "email_verified true is accepted");
assert_test(GoogleAuthController::isVerifiedProfile(['email' => 'a@example.test', 'email_verified' => 'true']), "email_verified \"true\" is accepted");
assert_test(!GoogleAuthController::isVerifiedProfile(['email' => 'a@example.test', 'email_verified' => false]), "email_verified false is refused");
assert_test(!GoogleAuthController::isVerifiedProfile(['email' => 'a@example.test']), "A profile without email_verified is refused");
assert_test(!GoogleAuthController::isVerifiedProfile(['email' => 'a@example.test', 'email_verified' => 1]), "Loose truthy values are refused");

echo "Testing a deleted user cannot sign in...\n";
$deletedLogin = TestRequest::post('/admin/login', ['username' => 'deleted_admin', 'password' => 'CorrectHorse123'])
    ->onSite(['enabled_modules' => $adminModules])
    ->withUser(['username' => 'deleted_admin', 'password' => 'CorrectHorse123', 'role' => 'super_admin', 'deleted_at' => gmdate('Y-m-d H:i:s')])
    ->withCsrf()
    ->send();
assert_test(stripos($deletedLogin['stdout'], 'Invalid credentials') !== false, "A deleted user's correct password is refused like an unknown account");

$activeLogin = TestRequest::post('/admin/login', ['username' => 'active_admin', 'password' => 'CorrectHorse123'])
    ->onSite(['enabled_modules' => $adminModules])
    ->withUser(['username' => 'active_admin', 'password' => 'CorrectHorse123', 'role' => 'super_admin'])
    ->withCsrf()
    ->send();
assert_test(stripos($activeLogin['stdout'], 'Invalid credentials') === false, "An active user with the same credentials still signs in");

echo "Testing a deleted user's existing session stops working...\n";
$activeSession = TestRequest::get('/admin/dashboard')
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'active_session_admin', 'role' => 'super_admin'])
    ->send();
$deletedSession = TestRequest::get('/admin/dashboard')
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'deleted_session_admin', 'role' => 'super_admin', 'deleted_at' => gmdate('Y-m-d H:i:s')])
    ->send();
assert_test(stripos($activeSession['stdout'], 'dashboard-card') !== false, "An active user's session reaches the dashboard");
assert_test(stripos($deletedSession['stdout'], 'dashboard-card') === false, "A deleted user's session no longer reaches the dashboard");

echo "Auth hardening tests completed successfully!\n";
