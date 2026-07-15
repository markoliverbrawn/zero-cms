<?php
// tests/SecurityTest.php
// Unit tests for Security utilities (Zero\Support\Security)

require_once __DIR__ . '/bootstrap.php';

use Zero\Support\Security;

echo "=== Security Component Tests ===\n";

// 1. Test CSRF Protection
echo "Testing CSRF Token generation and verification...\n";
$_SESSION = []; // Reset session
$token1 = Security::csrfToken();
assert_test(!empty($token1) && strlen($token1) === 32, "CSRF token generated is a valid 32-character hex string");

$token2 = Security::csrfToken();
assert_test($token1 === $token2, "Subsequent CSRF calls return the same cached token from session");

$inputField = Security::csrfInput();
assert_test(strpos($inputField, 'type="hidden"') !== false, "csrfInput contains hidden input field type");
assert_test(strpos($inputField, 'value="' . $token1 . '"') !== false, "csrfInput contains correct token value");

assert_test(Security::csrfVerify($token1), "Verify returns true for exact correct token");
assert_test(!Security::csrfVerify('wrong_token'), "Verify returns false for incorrect token");
assert_test(!Security::csrfVerify(''), "Verify returns false for empty token");

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
assert_test($sanitized['nested']['scripted'] === 'alert(1)Safe Text', "Strips HTML tags but keeps inner text content");

// 3. Test UUIDv7 Generation (RFC 9562)
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

echo "Security component tests completed.\n\n";
