<?php
// src/Integration/Tests/VerifyPasswordTest.php
// Security::verifyPassword() refuses the inputs Silverstripe SS-2026-009 showed can slip past a
// bare hash comparison: non-string passwords (password[]=), empty passwords, NUL-byte passwords
// (bcrypt truncates at NUL) and empty stored hashes.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Support\Security;
use Zero\Support\TestRequest;

echo "=== verifyPassword Hardening Tests ===\n";

$hash = \password_hash('CorrectHorse123', PASSWORD_BCRYPT);
$nulHash = \password_hash('abc', PASSWORD_BCRYPT);

echo "Testing verifyPassword accepts only well-formed matches...\n";
assert_test(Security::verifyPassword('CorrectHorse123', $hash) === true, "A correct password verifies");
assert_test(Security::verifyPassword('wrong', $hash) === false, "A wrong password is refused");
assert_test(Security::verifyPassword('', $hash) === false, "An empty password is refused");
assert_test(Security::verifyPassword(['CorrectHorse123'], $hash) === false, "An array password is refused without a TypeError");
assert_test(Security::verifyPassword(null, $hash) === false, "A null password is refused");
assert_test(Security::verifyPassword("abc\0anything", $nulHash) === false, "A password containing NUL is refused");
assert_test(Security::verifyPassword('anything', '') === false, "An empty stored hash is refused");
assert_test(Security::verifyPassword('anything', null) === false, "A null stored hash is refused");

echo "Testing the sign-in forms survive malformed password input...\n";
foreach (['/admin/login', '/login'] as $uri) {
    $arrayResponse = TestRequest::post($uri, ['username' => 'verify_pw_user', 'password' => ['x']])
        ->onSite(['enabled_modules' => ['security']])
        ->withUser(['username' => 'verify_pw_user', 'password' => 'CorrectHorse123', 'role' => 'super_admin'])
        ->withCsrf()
        ->send();

    assert_test(stripos($arrayResponse['stdout'], 'TypeError') === false && stripos($arrayResponse['stderr'], 'TypeError') === false, "POST {$uri} with password[] does not raise a TypeError");
    assert_test($arrayResponse['exit_code'] === 0, "POST {$uri} with password[] completes without a fatal error");

    $emptyResponse = TestRequest::post($uri, ['username' => 'verify_pw_user', 'password' => ''])
        ->onSite(['enabled_modules' => ['security']])
        ->withUser(['username' => 'verify_pw_user', 'password' => 'CorrectHorse123', 'role' => 'super_admin'])
        ->withCsrf()
        ->send();

    assert_test(stripos($emptyResponse['stdout'], 'Location: /admin/dashboard') === false, "POST {$uri} with an empty password does not sign in");
}

echo "verifyPassword hardening tests completed successfully!\n";
