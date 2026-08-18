<?php
// src/Modules/Admin/Tests/LoginControllerTest.php
// Drives real routed HTTP requests through LoginController to cover its authentication branches --
// form render, CSRF rejection, the four credential failure reasons, and a successful sign-in --
// using Zero\Support\TestRequest so each case is a declaration rather than hand-built scaffolding.

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Support\TestRequest;

echo "=== LoginController Routed Request Tests ===\n";

$adminModules = ['security'];

// 1. GET /admin/login renders the login form, including a CSRF field for the eventual POST.
echo "Testing GET /admin/login renders the sign-in form...\n";

$formResponse = TestRequest::get('/admin/login')
    ->onSite(['enabled_modules' => $adminModules])
    ->send();

assert_test($formResponse['exit_code'] === 0, "GET /admin/login completes without a fatal error");
assert_test(
    stripos($formResponse['stdout'], '<form') !== false,
    "GET /admin/login renders a form element"
);
assert_test(
    strpos($formResponse['stdout'], 'name="csrf"') !== false,
    "Rendered sign-in form embeds a CSRF token field"
);

// 2. A state-changing POST without a CSRF token must be stopped at the middleware, before any
//    credential comparison happens at all.
echo "Testing POST /admin/login without a CSRF token is rejected...\n";

$noCsrfResponse = TestRequest::post('/admin/login', ['username' => 'nobody', 'password' => 'whatever'])
    ->onSite(['enabled_modules' => $adminModules])
    ->send();

assert_test(
    stripos($noCsrfResponse['stdout'], 'Invalid credentials') === false,
    "POST without a CSRF token never reaches the credential check"
);

// 3. Unknown username -> generic invalid-credentials response (no user enumeration).
echo "Testing POST /admin/login with an unknown username...\n";

$unknownUserResponse = TestRequest::post('/admin/login', [
        'username' => 'no-such-admin',
        'password' => 'Secret123',
    ])
    ->onSite(['enabled_modules' => $adminModules])
    ->withCsrf()
    ->send();

assert_test(
    stripos($unknownUserResponse['stdout'], 'Invalid credentials') !== false,
    "Unknown username is refused with a generic 'Invalid credentials' message"
);
assert_test(
    stripos($unknownUserResponse['stdout'], 'not found') === false,
    "Unknown username response does not disclose that the account is absent"
);

// 4. Correct username, wrong password -> the same generic message, so the two failures are
//    indistinguishable to an attacker probing for valid accounts.
echo "Testing POST /admin/login with a valid user but wrong password...\n";

$wrongPasswordResponse = TestRequest::post('/admin/login', [
        'username' => 'branch_admin',
        'password' => 'DefinitelyNotTheRightPassword',
    ])
    ->onSite(['enabled_modules' => $adminModules])
    ->withUser(['username' => 'branch_admin', 'password' => 'CorrectHorse123', 'role' => 'super_admin'])
    ->withCsrf()
    ->send();

assert_test(
    stripos($wrongPasswordResponse['stdout'], 'Invalid credentials') !== false,
    "Wrong password is refused with the same generic message as an unknown user"
);

// 5. A role outside the administrative set is refused with its own distinct message, even when the
//    password is correct.
echo "Testing POST /admin/login with a non-administrative role...\n";

$badRoleResponse = TestRequest::post('/admin/login', [
        'username' => 'subscriber_only',
        'password' => 'CorrectHorse123',
    ])
    ->onSite(['enabled_modules' => $adminModules])
    ->withUser([
        'username' => 'subscriber_only',
        'password' => 'CorrectHorse123',
        'role' => 'subscriber',
    ])
    ->withCsrf()
    ->send();

assert_test(
    stripos($badRoleResponse['stdout'], 'Unauthorized administrative role') !== false,
    "A non-administrative role is refused with the unauthorized-role message"
);

// 6. Valid credentials for an administrative role -> redirect to the dashboard rather than a
//    re-rendered form.
echo "Testing POST /admin/login with valid administrator credentials...\n";

$successResponse = TestRequest::post('/admin/login', [
        'username' => 'good_admin',
        'password' => 'CorrectHorse123',
    ])
    ->onSite(['enabled_modules' => $adminModules])
    ->withUser(['username' => 'good_admin', 'password' => 'CorrectHorse123', 'role' => 'super_admin'])
    ->withCsrf()
    ->send();

assert_test(
    stripos($successResponse['stdout'], 'Invalid credentials') === false,
    "Valid administrator credentials are not refused"
);
assert_test(
    stripos($successResponse['stdout'], 'Unauthorized administrative role') === false,
    "Valid administrator credentials do not trip the role check"
);

// 7. An already-authenticated visitor is bounced to the dashboard before the sign-in form is ever
//    rendered, so a logged-in user cannot be shown a login prompt.
echo "Testing GET /admin/login while already authenticated...\n";

$alreadyAuthedResponse = TestRequest::get('/admin/login')
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'already_signed_in', 'role' => 'super_admin'])
    ->send();

assert_test(
    stripos($alreadyAuthedResponse['stdout'], '<form') === false,
    "An authenticated visitor is not shown the sign-in form again"
);
assert_test(
    $alreadyAuthedResponse['exit_code'] === 0,
    "The authenticated redirect terminates cleanly"
);

echo "LoginController routed request tests completed successfully!\n";
