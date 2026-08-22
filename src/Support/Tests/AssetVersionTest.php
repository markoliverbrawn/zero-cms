<?php
// src/Support/Tests/AssetVersionTest.php
// Tests content-digest asset URLs: the mechanism that makes the far-future immutable cache header
// on scripts and stylesheets honest, so a deployed fix actually reaches a returning visitor.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Support\AssetVersion;
use Zero\Support\TestRequest;

echo "=== Versioned Static Asset URL Tests ===\n";

// Fixtures live under public/ because that is the only tree AssetVersion will resolve, and carry a
// unique suffix so parallel worker slots sharing the directory cannot collide.
$token = bin2hex(random_bytes(5));
$publicDir = APPLICATION_ROOT . '/public/assets';
$assetA = '/assets/zz-av-a-' . $token . '.js';
$assetB = '/assets/zz-av-b-' . $token . '.js';
$assetC = '/assets/zz-av-c-' . $token . '.css';

file_put_contents(APPLICATION_ROOT . '/public' . $assetA, "console.log('one');\n");
file_put_contents(APPLICATION_ROOT . '/public' . $assetB, "console.log('one');\n"); // identical content
file_put_contents(APPLICATION_ROOT . '/public' . $assetC, ".probe { color: red; }\n");

// --- 1. URL shape ---
echo "  Testing versioned URL shape...\n";
$urlA = AssetVersion::url($assetA);
$digestA = AssetVersion::digest($assetA);

assert_critical($digestA !== null, 'A readable asset yields a digest');
assert_test(
    preg_match('/^[0-9a-f]{' . AssetVersion::DIGEST_LENGTH . '}$/', $digestA) === 1,
    "Digest is a fixed-length hex string, got '{$digestA}'"
);
assert_test($urlA === "/assets/zz-av-a-{$token}.{$digestA}.js", "Digest is inserted ahead of the extension: {$urlA}");
assert_test(substr($urlA, -3) === '.js', 'The original extension is preserved, so content-type sniffing is unaffected');
assert_test(strpos($urlA, '?') === false, 'No query string is used, so proxies that ignore query strings still cache it');

$urlC = AssetVersion::url($assetC);
assert_test(substr($urlC, -4) === '.css', "Stylesheets are versioned the same way: {$urlC}");

// --- 2. The digest follows content, not modification time ---
// This is the property that distinguishes it from a mtime stamp: a git checkout or docker build
// restamps every file, so an mtime-based digest would invalidate every asset on every deploy even
// when nothing changed. Content is the honest signal.
echo "  Testing that the digest follows content rather than mtime...\n";
assert_test($urlA === AssetVersion::url($assetA), 'Repeated calls return an identical URL');

AssetVersion::clearCache();
touch(APPLICATION_ROOT . '/public' . $assetA, time() + 500);
assert_test(AssetVersion::url($assetA) === $urlA, 'Touching a file without changing it does not change its URL');

assert_test(
    AssetVersion::digest($assetB) === $digestA,
    'Two files with identical content share a digest, confirming the digest is over content alone'
);

AssetVersion::clearCache();
file_put_contents(APPLICATION_ROOT . '/public' . $assetA, "console.log('two');\n");
$urlAfterEdit = AssetVersion::url($assetA);

assert_test($urlAfterEdit !== $urlA, "Editing an asset changes its URL ({$urlA} -> {$urlAfterEdit})");

AssetVersion::clearCache();
file_put_contents(APPLICATION_ROOT . '/public' . $assetA, "console.log('one');\n");
assert_test(
    AssetVersion::url($assetA) === $urlA,
    'Reverting an edit restores the original URL, so a rollback reuses the cached copy'
);

// --- 3. Anything unversionable passes through untouched ---
echo "  Testing pass-through of unversionable references...\n";
assert_test(
    AssetVersion::url('/assets/js/definitely-not-here.js') === '/assets/js/definitely-not-here.js',
    'A missing file is returned unchanged rather than throwing'
);
assert_test(
    AssetVersion::url('https://cdn.example.com/lib.js') === 'https://cdn.example.com/lib.js',
    'An absolute URL to another host is left alone'
);
assert_test(AssetVersion::url('') === '', 'An empty path stays empty');

// Path confinement: nothing outside public/ may be read, digested, or have its existence probed.
echo "  Testing path confinement...\n";
foreach (['/assets/../../.env', '/../.env', '/assets/js/../../../composer.json'] as $hostile) {
    assert_test(
        AssetVersion::url($hostile) === $hostile && AssetVersion::digest($hostile) === null,
        "A traversal attempt is refused and returned unchanged: {$hostile}"
    );
}

// --- 4. The rendered page carries versioned scripts, hero.js among them ---
// hero.js was previously absent from the layout entirely. It is the only thing that copies a hero
// video's data-src onto its <source>, so background videos never started on a published page --
// a defect invisible from the markup alone, which is why it is pinned here.
echo "  Testing the rendered layout's script tags...\n";
$response = TestRequest::get('/')
    ->onSite(['theme' => 'default'])
    ->withHomepage(['title' => 'Asset Version Home', 'content' => '[]'])
    ->send();

$html = $response['stdout'];
assert_critical($html !== '', 'The homepage rendered');

$expectedScripts = ['hero', 'testimonials', 'accordion', 'gallery', 'masonry', 'sub_pages', 'form_builder'];
foreach ($expectedScripts as $script) {
    assert_test(
        preg_match('#/assets/js/blocks/' . $script . '\.[0-9a-f]{' . AssetVersion::DIGEST_LENGTH . '}\.js#', $html) === 1,
        "The layout loads a content-versioned {$script}.js"
    );
}

assert_test(
    preg_match('#src="/assets/js/blocks/[a-z_]+\.js"#', $html) !== 1,
    'No block script is referenced without a digest, which would be cached immutably under a name that never changes'
);
assert_test(strpos($html, '?v=') === false, 'No hand-maintained query-string cache buster survives in the rendered page');

// Cleanup
foreach ([$assetA, $assetB, $assetC] as $fixture) {
    $path = APPLICATION_ROOT . '/public' . $fixture;
    if (file_exists($path)) {
        unlink($path);
    }
}
AssetVersion::clearCache();

echo "Versioned static asset URL tests completed successfully.\n\n";
