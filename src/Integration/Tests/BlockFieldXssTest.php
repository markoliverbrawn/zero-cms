<?php
// src/Integration/Tests/BlockFieldXssTest.php
// Block JSON is editor-controlled and rendered both to visitors and inside the admin block editor,
// so stored payloads in block fields must never reach the page as live markup: testimonial text,
// card link URLs, media URLs echoed into src/style attributes, and titles/content placed in the
// editor's contenteditable areas.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Support\Security;
use Zero\Support\TestRequest;

echo "=== Block Field XSS Tests ===\n";

// Assertions look for *live* markup -- a real tag or attribute -- because the same strings also
// appear harmlessly HTML-escaped elsewhere (the hidden editor input, the BENCHMARKING query dump).
$live = function (string $html, string $pattern): bool {
    return \preg_match($pattern, $html) === 1;
};

$blocks = [
    ['type' => 'testimonials', 'duration' => '5000" onmouseover="alert(1)', 'items' => [['content' => 'Great <img src=x onerror=alert(1)> service', 'person' => 'P']]],
    ['type' => 'grid', 'items' => [['title' => 'Card', 'link_url' => 'javascript:alert(2)']]],
    ['type' => 'text_image', 'title' => 'TI', 'content' => '<p>x</p>', 'media_id' => '/x.jpg" onerror="alert(3)'],
    ['type' => 'hero', 'title' => 'Hero', 'media_id' => "/x.jpg');\" onmouseover=\"alert(4)"],
];

echo "Testing stored block payloads render inert on the frontend...\n";
$response = TestRequest::get('/block-xss-page')
    ->onSite()
    ->withPage(['slug' => 'block-xss-page', 'title' => 'Block Xss Page', 'type' => 'page', 'content' => \json_encode($blocks)])
    ->send();

$html = $response['stdout'];
assert_test(strpos($html, 'Block Xss Page') !== false, "The page renders");
assert_test(!$live($html, '/<img[^>]*\sonerror=alert\(1\)/i'), "Testimonial content is sanitised");
assert_test(strpos($html, 'Great') !== false, "Testimonial text itself survives");
assert_test(!$live($html, '/\sonmouseover="alert\(1\)/i'), "Testimonial duration cannot break out of its attribute");
assert_test(!$live($html, '/href="\s*javascript:/i'), "A javascript: card link is not rendered");
assert_test(!$live($html, '/\sonerror="alert\(3\)/i'), "A text-image media URL cannot break out of src");
assert_test(!$live($html, '/\sonmouseover="alert\(4\)/i'), "A hero media URL cannot break out of the style attribute");

echo "Testing stored titles/content render inert in the admin block editor...\n";
$pageId = Security::uuidv7();
$response = TestRequest::get('/admin/edit/pages/' . $pageId)
    ->onSite(['enabled_modules' => ['security']])
    ->withPage([
        'id' => $pageId,
        'title' => 'Editor Xss Page',
        'type' => 'page',
        'content' => \json_encode([
            ['type' => 'hero', 'title' => 'T <img src=x onerror=alert(5)>', 'content' => '<p>C</p><svg onload=alert(6)>'],
        ]),
    ])
    ->asUser(['username' => 'block_xss_root', 'role' => 'super_admin'])
    ->send();

$html = $response['stdout'];
assert_test(strpos($html, 'block-editor-area') !== false, "The admin editor renders the block");
assert_test(!$live($html, '/<img[^>]*\sonerror=alert\(5\)/i'), "A block title is sanitised in the admin editor");
assert_test(!$live($html, '/<svg[^>]*onload/i'), "Block content is sanitised in the admin editor");

echo "Testing the URL safety helper...\n";
assert_test(Security::isSafeUrl('https://example.test/x'), "https URLs are safe");
assert_test(Security::isSafeUrl('/relative/path'), "Relative URLs are safe");
assert_test(!Security::isSafeUrl("java\tscript:alert(1)"), "Tab-split javascript: is unsafe");
assert_test(!Security::isSafeUrl('data:text/html,x'), "data: URLs are unsafe");

echo "Block field XSS tests completed successfully!\n";
