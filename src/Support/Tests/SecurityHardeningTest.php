<?php
// src/Support/Tests/SecurityHardeningTest.php
// Regression tests for the remaining 2026-11-25 security review fixes: server-side rate limiting,
// the shared password policy, current-password and session revocation on password change, the
// OAuth state nonce, SVG/CSS sanitiser tightening, where() validation and the JSON highlighter.
// Also covers, from the 2026-11-26 review: client IP resolution behind proxies (TRUSTED_PROXY_HOPS),
// overlay positioning and DOM clobbering in sanitizeHtml(), and session revocation on user delete
// and on a password set through the model.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Database\DB;
use Zero\Models\Page;
use Zero\Models\User;
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
assert_test(strpos(Security::sanitizeHtml('<div style="position:absolute;inset:0;width:100vw;height:100vh;z-index:99999">x</div>'), 'style=') === false, "position:absolute overlays are stripped");
assert_test(strpos(Security::sanitizeHtml('<div style="POSITION: Sticky; top: 0">x</div>'), 'style=') === false, "position:sticky is stripped, in any case");
assert_test(strpos(Security::sanitizeHtml('<div style="position: relative; left: 2px">x</div>'), 'style="position: relative; left: 2px"') !== false, "position:relative is kept");

echo "Testing DOM clobbering ids and names...\n";
assert_test(strpos(Security::sanitizeHtml('<a id="getElementById" href="#x">x</a>'), 'id=') === false, "An id that shadows a document method is stripped");
assert_test(strpos(Security::sanitizeHtml('<div id="ADMIN_CSRF_TOKEN">x</div>'), 'id=') === false, "An id that shadows an app global is stripped");
assert_test(strpos(Security::sanitizeHtml('<div id="__proto__">x</div>'), 'id=') === false, "An id starting with __ is stripped");
assert_test(strpos(Security::sanitizeHtml('<a name="cookie" href="#x">x</a>'), 'name=') === false, "The name attribute is no longer kept on links");
assert_test(strpos(Security::sanitizeHtml('<h2 id="installation">Installation</h2>'), 'id="installation"') !== false, "Ordinary ids (in-page anchors) are kept");

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

echo "Testing deleting a user or setting a password through the model revokes sessions...\n";
$revokeUser = new User([
    'username' => 'revoke_sessions_user',
    'email' => 'revoke-sessions@test.zero',
    'role' => 'editor',
    'site_id' => null,
]);
$revokeUser->save();
$revokeId = DB::query("SELECT id FROM users WHERE username = 'revoke_sessions_user'")->fetchColumn();
$changedAt = static fn () => DB::query("SELECT password_changed_at FROM users WHERE id = ?", [$revokeId])->fetchColumn();
assert_test($changedAt() === null, "Creating a user doesn't record a password change");

DB::clearIdentityMap();
$loaded = User::find($revokeId);
$loaded->email = 'revoke-sessions-2@test.zero';
$loaded->save();
assert_test($changedAt() === null, "Saving without changing the password leaves existing sessions alone");

DB::clearIdentityMap();
$loaded = User::find($revokeId);
$loaded->password_hash = password_hash('ModelApiPass123', PASSWORD_DEFAULT);
$loaded->save();
assert_test($changedAt() !== null, "Setting password_hash through the model signs out existing sessions");

DB::query("UPDATE users SET password_changed_at = NULL WHERE id = ?", [$revokeId]);
DB::clearIdentityMap();
User::find($revokeId)->delete();
assert_test($changedAt() !== null, "Deleting a user signs out existing sessions, so a later restore can't revive them");
DB::query("DELETE FROM users WHERE id = ?", [$revokeId]);

echo "Testing client IP resolution behind proxies...\n";
$savedServer = $_SERVER;
putenv('TRUSTED_PROXY_SECRET=hardening-proxy-secret');
$_SERVER['REMOTE_ADDR'] = '169.254.1.1'; // a proxy's address, shared by every visitor
unset($_SERVER['HTTP_X_PROXY_SECRET'], $_SERVER['HTTP_CF_CONNECTING_IP']);
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';

putenv('TRUSTED_PROXY_HOPS=0');
assert_test(Security::getClientIp() === '169.254.1.1', "With TRUSTED_PROXY_HOPS=0, X-Forwarded-For is ignored");

putenv('TRUSTED_PROXY_HOPS=1');
assert_test(Security::getClientIp() === '198.51.100.7', "With one trusted hop, the proxy-appended X-Forwarded-For entry is the client IP");
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 198.51.100.7';
assert_test(Security::getClientIp() === '198.51.100.7', "A client-supplied X-Forwarded-For entry can't override the proxy-appended one");
$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip';
assert_test(Security::getClientIp() === '169.254.1.1', "A malformed X-Forwarded-For entry falls back to REMOTE_ADDR");

putenv('TRUSTED_PROXY_HOPS=2');
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 198.51.100.7, 172.70.0.1';
assert_test(Security::getClientIp() === '198.51.100.7', "With two trusted hops, the entry two from the right is the client IP");
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';
assert_test(Security::getClientIp() === '169.254.1.1', "A chain shorter than the trusted hops falls back to REMOTE_ADDR");

$_SERVER['HTTP_CF_CONNECTING_IP'] = '192.0.2.44';
assert_test(Security::getClientIp() === '169.254.1.1', "CF-Connecting-IP is ignored without the proxy secret");
$_SERVER['HTTP_X_PROXY_SECRET'] = 'hardening-proxy-secret';
assert_test(Security::getClientIp() === '192.0.2.44', "CF-Connecting-IP from a verified proxy takes precedence");
$_SERVER['HTTP_CF_CONNECTING_IP'] = 'garbage';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4, 198.51.100.7, 172.70.0.1';
assert_test(Security::getClientIp() === '198.51.100.7', "A malformed CF-Connecting-IP falls through to X-Forwarded-For");

echo "Testing that two visitors behind the same proxy get separate rate-limit windows...\n";
putenv('TRUSTED_PROXY_HOPS=1');
unset($_SERVER['HTTP_X_PROXY_SECRET'], $_SERVER['HTTP_CF_CONNECTING_IP']);
DB::query("DELETE FROM rate_limits");
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.20';
assert_test(Security::rateLimit('proxy_bucket_test', 60), "The first visitor's action is allowed");
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.21';
assert_test(Security::rateLimit('proxy_bucket_test', 60), "A second visitor behind the same proxy is not blocked by the first");

putenv('TRUSTED_PROXY_HOPS');
putenv('TRUSTED_PROXY_SECRET');
$_SERVER = $savedServer;

echo "Security hardening tests completed successfully!\n";
