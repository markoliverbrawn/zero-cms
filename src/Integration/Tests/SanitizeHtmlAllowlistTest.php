<?php
// src/Integration/Tests/SanitizeHtmlAllowlistTest.php
// Regression tests for Security::sanitizeHtml()'s allow-list: payloads that slipped through the old
// deny-list (the ZeroCMS analogue of Silverstripe's HTML-sanitiser XSS fixes CVE-2026-91110 /
// CVE-2026-88987), plus the editor markup that must keep surviving.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Support\Security;

echo "=== sanitizeHtml Allow-list Tests ===\n";

// 1. Payloads that bypassed the old deny-list.
echo "Testing previously-bypassing XSS payloads are neutralised...\n";

$svgLink = Security::sanitizeHtml('<svg><a xlink:href="javascript:alert(1)"><text>x</text></a></svg>after');
assert_test(stripos($svgLink, 'javascript') === false && stripos($svgLink, '<svg') === false, "SVG xlink:href javascript: link is removed");
assert_test(strpos($svgLink, 'after') !== false, "Text following a dropped SVG is kept");

$mathLink = Security::sanitizeHtml('<math><maction actiontype="statusline" xlink:href="javascript:alert(1)">x</maction></math>');
assert_test(stripos($mathLink, 'javascript') === false && stripos($mathLink, '<math') === false, "MathML xlink:href javascript: link is removed");

$svgAnimate = Security::sanitizeHtml('<svg><animate attributeName="href" values="javascript:alert(1)"/></svg>');
assert_test(stripos($svgAnimate, 'javascript') === false, "SVG animate-to-javascript is removed");

assert_test(stripos(Security::sanitizeHtml('<base href="https://evil.example/">'), '<base') === false, "<base> is removed");
assert_test(stripos(Security::sanitizeHtml('<a href="vbscript:msgbox(1)">x</a>'), 'vbscript') === false, "vbscript: href is removed");
assert_test(stripos(Security::sanitizeHtml("<a href=\"java\tscript:alert(1)\">x</a>"), 'script:') === false, "Tab-split javascript: href is removed");
assert_test(stripos(Security::sanitizeHtml("<a href=\"\x01javascript:alert(1)\">x</a>"), 'javascript') === false, "Control-character-prefixed javascript: href is removed");
assert_test(stripos(Security::sanitizeHtml('<a href="JaVaScRiPt:alert(1)">x</a>'), 'javascript') === false, "Mixed-case javascript: href is removed");
assert_test(stripos(Security::sanitizeHtml('<img src="data:text/html;base64,PHNjcmlwdD4=">'), 'data:') === false, "data: src is removed");
assert_test(stripos(Security::sanitizeHtml('<img srcset="/ok.jpg 1x, javascript:alert(1) 2x">'), 'srcset') === false, "srcset with a javascript: candidate is removed");
assert_test(stripos(Security::sanitizeHtml('<video src="/v.mp4" poster="javascript:alert(1)"></video>'), 'javascript') === false, "javascript: poster is removed");
assert_test(stripos(Security::sanitizeHtml('<div style="background:url(javascript:alert(1))">x</div>'), 'style') === false, "style with url() is removed");
assert_test(stripos(Security::sanitizeHtml('<div style="width:expression(alert(1))">x</div>'), 'style') === false, "style with expression() is removed");
assert_test(stripos(Security::sanitizeHtml('<p formaction="javascript:alert(1)" srcdoc="x">x</p>'), 'formaction') === false, "Unknown attributes are removed");

$mxss = Security::sanitizeHtml('<noscript><p title="</noscript><img src=x onerror=alert(1)>"></noscript>');
assert_test(stripos($mxss, 'onerror') === false && stripos($mxss, '<img') === false, "noscript parser-differential (mXSS) payload is removed");

$template = Security::sanitizeHtml('<template><img src=x onerror=alert(1)></template>');
assert_test(stripos($template, 'onerror') === false, "<template> contents are removed");

assert_test(strpos(Security::sanitizeHtml('a<!-- <script>alert(1)</script> -->b'), '<!--') === false, "HTML comments are removed");

// 2. Legitimate editor markup survives.
echo "Testing legitimate editor markup survives...\n";

$formatted = Security::sanitizeHtml('<p style="text-align:center;color:#fff">Hi <b>bold</b> <i>it</i> <u>u</u> <small>s</small></p>');
assert_test($formatted === '<p style="text-align:center;color:#fff">Hi <b>bold</b> <i>it</i> <u>u</u> <small>s</small></p>', "Inline formatting and safe styles are kept verbatim");

$links = Security::sanitizeHtml('<a href="https://example.test/x?y=1">a</a><a href="/relative">b</a><a href="#frag">c</a><a href="mailto:a@example.test">d</a>');
assert_test(substr_count($links, 'href=') === 4, "http(s), relative, fragment and mailto links are kept");

$blank = Security::sanitizeHtml('<a href="https://example.test" target="_blank">x</a>');
assert_test(strpos($blank, 'rel="noopener noreferrer"') !== false, "target=_blank links get rel=noopener noreferrer");

$lists = Security::sanitizeHtml('<ul><li>one</li></ul><ol start="3"><li>two</li></ol>');
assert_test($lists === '<ul><li>one</li></ul><ol start="3"><li>two</li></ol>', "Lists are kept");

$table = Security::sanitizeHtml('<table><tbody><tr><th scope="col">h</th><td colspan="2">d</td></tr></tbody></table>');
assert_test($table === '<table><tbody><tr><th scope="col">h</th><td colspan="2">d</td></tr></tbody></table>', "Tables are kept");

$image = Security::sanitizeHtml('<img src="/media/a.jpg" alt="A" width="10" srcset="/media/a.jpg 1x, /media/a@2x.jpg 2x">');
assert_test(strpos($image, 'src="/media/a.jpg"') !== false && strpos($image, 'srcset=') !== false, "Images with safe src and srcset are kept");

$data = Security::sanitizeHtml('<div data-block="x" aria-label="y" class="c">z</div>');
assert_test($data === '<div data-block="x" aria-label="y" class="c">z</div>', "data-*, aria-* and class attributes are kept");

$unknown = Security::sanitizeHtml('<font color="red">kept text</font>');
assert_test($unknown === 'kept text', "Unknown harmless tags are unwrapped and their text kept");

$code = Security::sanitizeHtml('<pre><code class="language-php">echo 1;</code></pre>');
assert_test(strpos($code, 'block-code-container') !== false && strpos($code, 'language-php') !== false, "Code blocks still reach the syntax highlighter");

echo "sanitizeHtml allow-list tests completed successfully!\n";
