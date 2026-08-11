<?php
// tests/SecurityTest.php
// Unit tests for Security utilities (Zero\Support\Security)

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Support\Logger;
use Zero\Support\Security;

echo "=== Security Component Tests ===\n";

// 1. Test CSRF Verification (csrfToken, csrfVerify)
echo "Testing CSRF Token generation and verification...\n";
App::ensureSession();

$token1 = Security::csrfToken();
$token2 = Security::csrfToken();

assert_test(strlen($token1) === 32, "CSRF token generated is a valid 32-character hex string");
assert_test($token1 === $token2, "Subsequent CSRF calls return the same cached token from session");

$inputField = Security::csrfInput();
assert_test(strpos($inputField, 'type="hidden"') !== false, "csrfInput contains hidden input field type");
assert_test(strpos($inputField, $token1) !== false, "csrfInput contains correct token value");

assert_test(Security::csrfVerify($token1) === true, "Verify returns true for exact correct token");
assert_test(Security::csrfVerify('incorrect') === false, "Verify returns false for incorrect token");
assert_test(Security::csrfVerify('') === false, "Verify returns false for empty token");

// 2. Test Input Sanitizer (sanitizeInput)
echo "Testing Input Sanitizer...\n";
$dirtyInput = [
    'title' => "  My Page Title\0With Null Byte  ",
    'raw_content' => "<p>Keep me intact</p>",
    'password' => "  SecretRaw123  ", // Key is in exceptKeys, should not trim/strip
    'nested' => [
        'info' => "  Trim Me  ",
        'scripted' => "<script>alert(1)</script>Safe Text"
    ]
];

$sanitized = Security::sanitizeInput($dirtyInput, true);

assert_test($sanitized['title'] === 'My Page TitleWith Null Byte', "Strips null bytes and trims surrounding whitespaces");
assert_test($sanitized['raw_content'] === 'Keep me intact', "Strips HTML tags if requested and not in exceptKeys");
assert_test($sanitized['password'] === 'SecretRaw123', "Trims passwords as standard strings but preserves special characters");
assert_test($sanitized['nested']['info'] === 'Trim Me', "Sanitizes nested array keys recursively");

$cleanContentOnly = Security::sanitizeInput(" <p>Test</p> ", true);
assert_test($cleanContentOnly === 'Test', "Strips HTML tags but keeps inner text content");

// 3. Test UUIDv7 Compliance (uuidv7)
echo "Testing UUIDv7 compliance...\n";
$uuid1 = Security::uuidv7();
$uuid2 = Security::uuidv7();

assert_test(strlen($uuid1) === 36, "UUIDv7 has standard 36-character length");
assert_test($uuid1 !== $uuid2, "Subsequent UUIDv7 generations are unique");
assert_test(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid1), "Conforms strictly with RFC 9562 UUIDv7 layout (version bit = 7, variant = 8, 9, a, or b)");

// 4. Test HTML Sanitizer (sanitizeHtml)
echo "Testing HTML Sanitizer XSS mitigation...\n";
$maliciousHtml = '
<div>
    <h1>Welcome</h1>
    <script>alert("XSS")</script>
    <iframe src="malicious.com"></iframe>
    <p onclick="executeCode()">Click Me</p>
    <a href="javascript:doEvil()">Malicious Link</a>
    <img src="data:text/html;base64,evil" />
    <img src="good-image.jpg" onmouseover="evil()" />
</div>';

$cleanHtml = Security::sanitizeHtml($maliciousHtml);

assert_test(strpos($cleanHtml, 'Welcome') !== false, "Preserves valid HTML content structure");
assert_test(strpos($cleanHtml, '<script>') === false, "Strips out <script> elements completely");
assert_test(strpos($cleanHtml, '<iframe>') === false, "Strips out <iframe> elements completely");
assert_test(strpos($cleanHtml, 'onclick') === false, "Strips out on* inline event handlers");
assert_test(strpos($cleanHtml, 'onmouseover') === false, "Strips out inline hover event handlers");
assert_test(strpos($cleanHtml, 'javascript:') === false, "Removes links containing javascript: protocol schemas");
assert_test(strpos($cleanHtml, 'data:') === false, "Removes elements containing data: protocol schemas");
assert_test(strpos($cleanHtml, 'good-image.jpg') !== false, "Preserves safe elements and safe attribute URIs");

// 5. Test Escape HTML (escape)
echo "Testing HTML escaping...\n";
$dirtyHtml = '<script>alert("XSS")</script> & "quotes"';
$escaped = Security::escape($dirtyHtml);
assert_test($escaped === '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt; &amp; &quot;quotes&quot;', "Security::escape correctly encodes HTML tags, double quotes, and ampersands");
assert_test(Security::escape(null) === '', "Security::escape handles null values gracefully");

// 6. Test Authentication Throttling / Rate Limiting (checkAuthRateLimit)
echo "Testing Authentication Throttling...\n";
DB::query("DELETE FROM audit_logs WHERE action IN ('login_failed', 'frontend_login_failed')");

$username = 'test_brute_force_user';
assert_test(Security::checkAuthRateLimit('login', $username, 3, 10) === true, "First checkAuthRateLimit attempt is allowed");

for ($i = 0; $i < 3; $i++) {
    Logger::log(null, 'login_failed', 'user', null, [
        'username' => $username,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);
}

assert_test(Security::checkAuthRateLimit('login', $username, 3, 10) === false, "checkAuthRateLimit correctly blocks attempt after 3 failed logs (lockout active)");

DB::query("DELETE FROM audit_logs WHERE action = 'login_failed'");

// 7. Test Path Traversal and Escape rejection (LocalStorageDriver resolvePath)
echo "Testing Path Traversal and Escape protection...\n";
$driver = new \Zero\Core\Storage\LocalStorageDriver();
$traversalCaught = false;
try {
    $driver->exists('storage/../../etc/passwd');
} catch (\InvalidArgumentException $e) {
    $traversalCaught = true;
}
assert_test($traversalCaught === true, "LocalStorageDriver correctly detects and rejects malformed path traversal sequence '..'");

// 8. Test API Session Rate Limiting (Security::rateLimit)
echo "Testing API Key Rate Limiting...\n";
$rateKey = 'test_api_abuse_' . uniqid();
assert_test(Security::rateLimit($rateKey, 2) === true, "First Security::rateLimit check is allowed");
assert_test(Security::rateLimit($rateKey, 2) === false, "Immediate subsequent Security::rateLimit check is rejected (rate limit triggers)");

// 9. Test secure path boundary helper
echo "Testing secure path boundary helper...\n";
$storageRoot = APPLICATION_ROOT . '/storage';
assert_test(
    \Zero\Http\Middleware\SecurePathMiddleware::isPathWithinStorageRoot($storageRoot . '/uploads/file.txt', $storageRoot) === true,
    "Boundary helper accepts content inside the storage root"
);
assert_test(
    \Zero\Http\Middleware\SecurePathMiddleware::isPathWithinStorageRoot($storageRoot . '2/uploads/file.txt', $storageRoot) === false,
    "Boundary helper rejects sibling paths that only share a prefix"
);

// 10. Test SVG Sanitization (Security::sanitizeSvg)
echo "Testing SVG Sanitization...\n";
$tempSvgFile = tempnam(sys_get_temp_dir(), 'test_svg_') . '.svg';
$maliciousSvg = '<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 100 100">
    <circle cx="50" cy="50" r="40" fill="red" onmouseover="alert(1)" style="stroke: blue; stroke-width: 5px;"/>
    <script type="text/javascript">alert("XSS")</script>
    <foreignObject x="20" y="20" width="50" height="50">
        <div xmlns="http://www.w3.org/1999/xhtml">
            <iframe src="javascript:alert(2)"></iframe>
        </div>
    </foreignObject>
    <style>
        circle { fill: expression(alert(3)); }
    </style>
    <a xlink:href="javascript:alert(4)">Click here</a>
    <image xlink:href="data:image/png;base64,evil" x="0" y="0" height="10" width="10"/>
    <text x="10" y="10" fill="black">Safe text</text>
</svg>';

file_put_contents($tempSvgFile, $maliciousSvg);

$sanitizedResult = Security::sanitizeSvg($tempSvgFile);
assert_test($sanitizedResult === true, "sanitizeSvg successfully processed and wrote the sanitized file");

$sanitizedContent = file_get_contents($tempSvgFile);

assert_test(strpos($sanitizedContent, '<script>') === false, "Strips script tags from SVG");
assert_test(strpos($sanitizedContent, 'foreignObject') === false, "Strips foreignObject elements from SVG");
assert_test(strpos($sanitizedContent, '<style>') === false, "Strips style tags from SVG");
assert_test(strpos($sanitizedContent, 'style=') === false, "Strips style attributes from circle element");
assert_test(strpos($sanitizedContent, 'onmouseover') === false, "Strips inline event handlers from SVG elements");
assert_test(strpos($sanitizedContent, 'javascript:') === false, "Strips dangerous javascript: URI schemes from href");
assert_test(strpos($sanitizedContent, 'data:') === false, "Strips dangerous data: URI schemes from image href");
assert_test(strpos($sanitizedContent, 'Safe text') !== false, "Preserves safe elements and safe plain text");

unlink($tempSvgFile);

echo "Security component tests completed.\n\n";
