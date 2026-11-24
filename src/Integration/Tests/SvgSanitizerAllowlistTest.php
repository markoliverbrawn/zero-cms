<?php
// src/Integration/Tests/SvgSanitizerAllowlistTest.php
// Uploaded SVGs are served from the CMS's own origin, so Security::sanitizeSvg() must leave nothing
// scriptable: animation elements that retarget href to javascript:, whitespace-split schemes,
// non-SVG roots and foreign-namespace content -- while keeping ordinary icon markup intact.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Support\Security;

echo "=== SVG Sanitizer Allow-list Tests ===\n";

/**
 * Run sanitizeSvg over a string; returns the cleaned markup, or null if it was rejected.
 */
$clean = function (string $svg): ?string {
    $file = \tempnam(\sys_get_temp_dir(), 'svgtest');
    \file_put_contents($file, $svg);
    $ok = Security::sanitizeSvg($file);
    $out = \file_get_contents($file);
    \unlink($file);
    return $ok ? $out : null;
};

$ns = 'xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"';

echo "Testing known bypasses are neutralised...\n";

$tab = $clean("<svg {$ns}><a href=\"java&#x09;script:alert(1)\"><text y=\"20\">x</text></a></svg>");
assert_test($tab !== null && \stripos($tab, 'script:') === false, "Tab-split javascript: link is removed");
assert_test($tab !== null && \strpos($tab, '<text') !== false, "Text inside a removed link is kept");

$animate = $clean("<svg {$ns}><a><animate attributeName=\"href\" values=\"x;javascript:alert(1)\"/><text>x</text></a></svg>");
assert_test($animate !== null && \stripos($animate, 'animate') === false && \stripos($animate, 'javascript') === false, "<animate> retargeting href is removed");

$set = $clean("<svg {$ns}><a><set attributeName=\"href\" to=\"javascript:alert(1)\"/><text>x</text></a></svg>");
assert_test($set !== null && \stripos($set, '<set') === false, "<set> is removed");

$xlink = $clean("<svg {$ns}><a xlink:href=\"javascript:alert(1)\"><circle r=\"5\"/></a></svg>");
assert_test($xlink !== null && \stripos($xlink, 'javascript') === false, "xlink:href javascript: is removed");

$useExternal = $clean("<svg {$ns}><use href=\"https://evil.example/x.svg#a\"/></svg>");
assert_test($useExternal !== null && \stripos($useExternal, 'evil.example') === false, "<use> may not reference an external document");

$imageData = $clean("<svg {$ns}><image href=\"data:image/svg+xml;base64,PHN2Zz4=\"/></svg>");
assert_test($imageData !== null && \stripos($imageData, 'data:') === false, "<image> data: URI is removed");

$xhtml = $clean("<svg {$ns}><g><iframe xmlns=\"http://www.w3.org/1999/xhtml\" src=\"javascript:alert(1)\"/></g></svg>");
assert_test($xhtml !== null && \stripos($xhtml, 'iframe') === false, "Foreign-namespace (xhtml) elements are removed");

$script = $clean("<svg {$ns}><script>alert(1)</script><foreignObject><div>x</div></foreignObject><style>*{}</style></svg>");
assert_test($script !== null && \stripos($script, 'script') === false && \stripos($script, 'foreignObject') === false && \stripos($script, '<style') === false, "script, foreignObject and style are removed");

$handlers = $clean("<svg {$ns} OnLoad=\"alert(1)\"><rect onclick=\"alert(1)\" style=\"x\" width=\"1\"/></svg>");
assert_test($handlers !== null && \stripos($handlers, 'alert') === false && \stripos($handlers, 'style=') === false, "Event handlers (any case) and style attributes are removed");

assert_test($clean('<html xmlns="http://www.w3.org/1999/xhtml"><body><script>alert(1)</script></body></html>') === null, "A non-SVG root element is rejected");
assert_test($clean("<!DOCTYPE svg [<!ENTITY x SYSTEM \"file:///etc/passwd\">]><svg {$ns}>&x;</svg>") === null, "DOCTYPE/ENTITY (XXE) is rejected");

echo "Testing ordinary icon markup survives...\n";

$icon = $clean("<svg {$ns} viewBox=\"0 0 24 24\" width=\"24\" height=\"24\"><title>Icon</title><defs><linearGradient id=\"g\"><stop offset=\"0\" stop-color=\"#fff\"/></linearGradient></defs><g fill=\"url(#g)\" stroke=\"currentColor\"><path d=\"M0 0L24 24\"/><circle cx=\"12\" cy=\"12\" r=\"4\"/></g><use href=\"#g\"/><a href=\"https://example.test\"><rect width=\"2\" height=\"2\"/></a></svg>");
assert_test($icon !== null, "A normal icon is accepted");
foreach (['viewBox="0 0 24 24"', '<title>Icon</title>', '<linearGradient id="g">', 'fill="url(#g)"', '<path d="M0 0L24 24"/>', '<use href="#g"/>', '<rect width="2" height="2"/>'] as $needle) {
    assert_test(\strpos($icon ?? '', $needle) !== false, "Icon keeps {$needle}");
}

echo "SVG sanitizer allow-list tests completed successfully!\n";
