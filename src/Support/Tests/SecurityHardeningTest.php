<?php
// src/Support/Tests/SecurityHardeningTest.php
// Regression tests for the remaining 2026-11-25 security review fixes: server-side rate limiting,
// the shared password policy, current-password and session revocation on password change, the
// OAuth state nonce, SVG/CSS sanitiser tightening, where() validation and the JSON highlighter.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Database\DB;
use Zero\Models\Page;
use Zero\Support\Security;
use Zero\Support\Str;
use Zero\Support\TestRequest;

echo "=== Security Hardening Tests ===\n";

$adminModules = ['security'];

echo "Testing server-side rate limiting...\n";
DB::query("DELETE FROM rate_limits");
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$_SESSION = [];
assert_test(Security::rateLimit('hardening_test', 60), "The first action in a window is allowed");
$_SESSION = []; // a client that drops its session cookie gets a fresh session
assert_test(!Security::rateLimit('hardening_test', 60), "A second action is refused even with a fresh session");
$_SERVER['REMOTE_ADDR'] = '203.0.113.11';
assert_test(Security::rateLimit('hardening_test', 60), "Another client IP has its own window");
assert_test(Security::rateLimit('hardening_test_other', 60), "Another action has its own window");
DB::query("UPDATE rate_limits SET last_hit = last_hit - 120");
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
assert_test(Security::rateLimit('hardening_test', 60), "The action is allowed again once the window has passed");
DB::query("DELETE FROM rate_limits");

echo "Testing the shared password policy...\n";
assert_test(Security::passwordPolicyError('CorrectHorse123') === null, "A long mixed-case password with a digit passes");
assert_test(Security::passwordPolicyError('short1A') !== null, "A short password is refused");
assert_test(Security::passwordPolicyError('alllowercase123') !== null, "A password without upper case is refused");
assert_test(Security::passwordPolicyError('change_me') !== null, "The seeded change_me password is refused");

echo "Testing the OAuth state nonce...\n";
$_SESSION = [];
$state = Security::oauthState();
assert_test($state !== '' && Security::oauthState() === $state, "The nonce is stable across renders until used");
assert_test(Security::consumeOAuthState($state), "The issued nonce is accepted once");
assert_test(!Security::consumeOAuthState($state), "A replayed nonce is refused");
Security::oauthState();
assert_test(!Security::consumeOAuthState('forged'), "A forged state is refused");
$_SESSION = [];
assert_test(!Security::consumeOAuthState(''), "A missing nonce is refused");

echo "Testing the SVG sanitiser drops processing instructions...\n";
$svgPath = tempnam(sys_get_temp_dir(), 'svg');
file_put_contents($svgPath, '<?xml-stylesheet href="https://evil.example/x.xsl" type="text/xsl"?><svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>');
assert_test(Security::sanitizeSvg($svgPath), "The SVG is accepted");
$svgOut = (string) file_get_contents($svgPath);
unlink($svgPath);
assert_test(strpos($svgOut, 'xml-stylesheet') === false && strpos($svgOut, '<rect') !== false, "The stylesheet instruction is removed and the drawing kept");

echo "Testing style attribute filtering...\n";
assert_test(strpos(Security::sanitizeHtml('<div style="position: fixed; top: 0">x</div>'), 'style=') === false, "position:fixed overlays are stripped");
assert_test(strpos(Security::sanitizeHtml('<div style="background: image-set(\'https://evil.example/a.png\' 1x)">x</div>'), 'style=') === false, "image-set() fetches are stripped");
assert_test(strpos(Security::sanitizeHtml('<div style="width: expre/**/ssion(alert(1))">x</div>'), 'style=') === false, "CSS comments are stripped");
assert_test(strpos(Security::sanitizeHtml('<p style="text-align: center; color: #333">x</p>'), 'style="text-align: center; color: #333"') !== false, "Ordinary styling is kept");

echo "Testing IsModel::where() validation...\n";
$threw = static function (callable $call): bool {
    try {
        $call();
    } catch (\InvalidArgumentException $e) {
        return true;
    }
    return false;
};
assert_test($threw(fn () => Page::where('slug = slug OR 1', 'x')), "A column expression is refused");
assert_test($threw(fn () => Page::where('slug', 'x', 'OR 1=1')), "Free-form options are refused");
assert_test(!$threw(fn () => Page::where('slug', 'no-such-page', 'ORDER BY created_at DESC LIMIT 1')), "An ORDER BY / LIMIT tail is accepted");

echo "Testing the JSON highlighter leaves entities intact...\n";
$highlighted = Str::highlightCode('{"name": "O\'Brien", "age": 42}', 'json');
assert_test(strpos($highlighted, '&#039;') !== false && strpos($highlighted, '&#<span') === false, "Escaped quotes are not split by number highlighting");
assert_test(strpos($highlighted, '<span class="tok-num">42</span>') !== false, "Real numbers are still highlighted");

echo "Testing password change requires the current password...\n";
$wrongCurrent = TestRequest::post('/admin/change-password', ['current_password' => 'NotMyPassword1', 'password' => 'BrandNewPass123', 'confirm_password' => 'BrandNewPass123'])
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'change_pw_user', 'password' => 'CorrectHorse123', 'role' => 'admin'])
    ->withCsrf()
    ->send();
assert_test(stripos($wrongCurrent['stdout'], 'current password is incorrect') !== false, "A wrong current password is refused");
$weakNew = TestRequest::post('/admin/change-password', ['current_password' => 'CorrectHorse123', 'password' => 'weakpass', 'confirm_password' => 'weakpass'])
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'change_pw_user2', 'password' => 'CorrectHorse123', 'role' => 'admin'])
    ->withCsrf()
    ->send();
assert_test(stripos($weakNew['stdout'], 'too weak') !== false, "A new password below the shared policy is refused");
$goodChange = TestRequest::post('/admin/change-password', ['current_password' => 'CorrectHorse123', 'password' => 'BrandNewPass123', 'confirm_password' => 'BrandNewPass123'])
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'change_pw_user3', 'password' => 'CorrectHorse123', 'role' => 'admin'])
    ->withCsrf()
    ->send();
$changed = DB::query("SELECT password_hash, password_changed_at FROM users WHERE username = 'change_pw_user3'")->fetch();
assert_test($changed && password_verify('BrandNewPass123', $changed['password_hash']) && $changed['password_changed_at'] !== null, "The correct current password changes it and records the change time");

echo "Testing a password change ends earlier sessions...\n";
$staleSession = TestRequest::get('/admin/dashboard')
    ->onSite(['enabled_modules' => $adminModules])
    ->asUser(['username' => 'stale_session_admin', 'role' => 'super_admin', 'password_changed_at' => gmdate('Y-m-d H:i:s', time() + 60)])
    ->send();
assert_test(stripos($staleSession['stdout'], 'dashboard-card') === false, "A session that signed in before the password changed no longer reaches the dashboard");

echo "Security hardening tests completed successfully!\n";
