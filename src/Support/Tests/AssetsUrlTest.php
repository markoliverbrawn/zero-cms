<?php
// src/Support/Tests/AssetsUrlTest.php
// Tests the signed variant URL builder: determinism, signature integrity, version-based cache
// invalidation, pass-through of anything unresizable, and the guarantee that minting a URL
// performs no I/O at all.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Database\DB;
use Zero\Support\Assets;
use Zero\Support\VariantCache;

echo "=== Assets Variant URL Tests ===\n";

if (!Assets::isSupported()) {
    echo "  Skipping: this PHP build has no GD WebP support, so no variants are ever minted.\n";
    exit(0);
}

$siteId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
$mediaId = '11111111-2222-3333-4444-555555555555';
$mediaPath = "/storage/uploads/{$siteId}/subject.jpg";

$baseRow = [
    'id' => $mediaId,
    'site_id' => $siteId,
    'path' => $mediaPath,
    'mime' => 'image/jpeg',
    'title' => 'A Photographed Subject',
    'filename' => 'subject.jpg',
    'focus_x' => 25,
    'focus_y' => 75,
    'width' => 2000,
    'height' => 1000,
    'visibility' => 'public',
    'created_at' => '2026-01-01 00:00:00',
    'updated_at' => '2026-02-02 12:00:00',
];

Assets::clearRegistry();
Assets::prime([$baseRow]);

// --- 1. A primed record mints a signed cache path ---
echo "  Testing variant URL structure...\n";
$url = Assets::url($mediaPath, width: 600, height: 400);

assert_test(strpos($url, '/storage/variants/') === 0, "Variant URL lives under the dedicated variants cache directory: {$url}");
assert_test(strpos($url, '/storage/uploads/') === false, "Variant URL never points inside the uploads tree");
assert_test(strpos($url, "/{$siteId}/") !== false, "Variant URL is scoped to the owning tenant");
assert_test(strpos($url, "/{$mediaId}/") !== false, "Variant URL carries the media id so the renderer can resolve the source");
assert_test(substr($url, -5) === '.webp', "Variant URL ends in the .webp output extension");
assert_test(
    preg_match('#^/storage/variants/[A-Za-z0-9\-]+/[0-9a-f]{2}/[A-Za-z0-9\-]+/600x400-cover-q82-[0-9a-f]{16}\.webp$#', $url) === 1,
    "Variant URL matches the signed route pattern exactly"
);

// --- 2. Resolution works by id as well as by path, and by bucket-style absolute URL ---
echo "  Testing source reference forms...\n";
$byId = Assets::url($mediaId, width: 600, height: 400);
$byBucketUrl = Assets::url("https://storage.googleapis.com/some-bucket/storage/uploads/{$siteId}/subject.jpg", width: 600, height: 400);

assert_test($byId === $url, "Passing a media id yields the same URL as passing its path");
assert_test($byBucketUrl === $url, "A fully-qualified bucket URL resolves to the same variant");

// --- 3. Determinism: the same request always yields the same URL ---
echo "  Testing determinism and cache-key separation...\n";
assert_test(Assets::url($mediaPath, width: 600, height: 400) === $url, "Repeated calls are byte-identical, so the cache actually hits");
assert_test(Assets::url($mediaPath, width: 601, height: 400) !== $url, "A different width yields a different URL");
assert_test(Assets::url($mediaPath, width: 600, height: 401) !== $url, "A different height yields a different URL");
assert_test(Assets::url($mediaPath, width: 600, height: 400, fit: Assets::FIT_CONTAIN) !== $url, "A different fit mode yields a different URL");
assert_test(Assets::url($mediaPath, width: 600, height: 400, quality: 60) !== $url, "A different quality yields a different URL");

// --- 4. Minting a URL performs no database and no filesystem work ---
// This is the property the whole design rests on: a page emitting a hundred variant URLs must
// cost a hundred hash computations, not a hundred stats or queries.
echo "  Testing that URL minting performs no I/O...\n";
$queriesBefore = DB::getQueryCount();
for ($i = 0; $i < 200; $i++) {
    Assets::url($mediaPath, width: 100 + $i, height: 100);
}
$queriesAfter = DB::getQueryCount();

assert_test($queriesAfter === $queriesBefore, "200 primed url() calls issued zero database queries");

$sampleAbsolute = VariantCache::absolutePath(ltrim($url, '/'));
assert_test(!file_exists($sampleAbsolute), "url() did not render or write any image; generation is deferred to the first request");

// --- 5. Signature integrity ---
echo "  Testing signature integrity...\n";
$expected = Assets::signature($siteId, $mediaId, 600, 400, Assets::FIT_COVER, 82, $baseRow['updated_at']);

assert_test(strlen($expected) === 16, "Signature is a 16 character truncated HMAC");
assert_test(strpos($url, $expected) !== false, "The minted URL embeds exactly the signature the renderer will recompute");
assert_test(
    Assets::signature($siteId, $mediaId, 600, 400, Assets::FIT_COVER, 82, $baseRow['updated_at']) === $expected,
    "Signature computation is stable across calls"
);
assert_test(
    Assets::signature($siteId, $mediaId, 600, 401, Assets::FIT_COVER, 82, $baseRow['updated_at']) !== $expected,
    "Tampering with a dimension invalidates the signature"
);
assert_test(
    Assets::signature('other-site', $mediaId, 600, 400, Assets::FIT_COVER, 82, $baseRow['updated_at']) !== $expected,
    "Replaying a signature against another tenant invalidates it"
);

// The shard directory has to be derivable from the signature, or the renderer cannot confirm the
// path it was asked for is the path the signature authorizes.
$segments = explode('/', ltrim($url, '/'));
assert_test($segments[3] === substr($expected, 0, 2), "Shard directory is the signature's first two characters");

// --- 6. Editing the source rotates every URL (version-stamped cache invalidation) ---
echo "  Testing version-stamped invalidation...\n";
$refocused = $baseRow;
$refocused['focus_x'] = 80;
$refocused['updated_at'] = '2026-03-03 09:30:00';
Assets::clearRegistry();
Assets::prime([$refocused]);

$urlAfterEdit = Assets::url($mediaPath, width: 600, height: 400);
assert_test($urlAfterEdit !== $url, "Re-focusing an image publishes it under a brand new URL");
assert_test(strpos($urlAfterEdit, '600x400-cover-q82-') !== false, "The new URL still describes the same requested rendition");

// --- 7. A missing update stamp falls back to the creation stamp ---
echo "  Testing version fallback for never-updated records...\n";
$neverUpdated = $baseRow;
$neverUpdated['updated_at'] = null;
Assets::clearRegistry();
Assets::prime([$neverUpdated]);

$fallbackUrl = Assets::url($mediaPath, width: 600, height: 400);
$fallbackSignature = Assets::signature($siteId, $mediaId, 600, 400, Assets::FIT_COVER, 82, $baseRow['created_at']);
assert_test(strpos($fallbackUrl, $fallbackSignature) !== false, "A row with no updated_at is versioned by created_at instead");

Assets::clearRegistry();
Assets::prime([$baseRow]);

// --- 8. Single-dimension requests normalize to a plain scale ---
echo "  Testing single-dimension requests...\n";
$widthOnly = Assets::url($mediaPath, width: 1600);
$heightOnly = Assets::url($mediaPath, height: 300);

assert_test(strpos($widthOnly, '1600x0-contain-') !== false, "Width-only requests record an unconstrained height and force contain: {$widthOnly}");
assert_test(strpos($heightOnly, '0x300-contain-') !== false, "Height-only requests record an unconstrained width and force contain: {$heightOnly}");
assert_test(
    Assets::url($mediaPath, width: 1600, fit: Assets::FIT_COVER) === $widthOnly,
    "Requesting cover with one dimension is normalized rather than producing a second cache entry"
);

// --- 9. Dimensions are clamped to the processing ceiling ---
echo "  Testing dimension clamping...\n";
$oversized = Assets::url($mediaPath, width: 99999, height: 99999);
$ceiling = Assets::MAX_DIMENSION;

assert_test(strpos($oversized, "{$ceiling}x{$ceiling}-") !== false, "An absurd request is clamped to the processing ceiling, not honoured");
assert_test(preg_match('/\d{5,}x/', $oversized) !== 1, "No five-digit dimension survives into the URL");

// --- 10. Everything unresizable passes straight through untouched ---
echo "  Testing pass-through of unresizable sources...\n";
Assets::clearRegistry();
Assets::prime([
    ['id' => 'gif-id', 'site_id' => $siteId, 'path' => '/storage/uploads/x/anim.gif', 'mime' => 'image/gif', 'updated_at' => '2026-01-01 00:00:00'],
    ['id' => 'svg-id', 'site_id' => $siteId, 'path' => '/storage/uploads/x/logo.svg', 'mime' => 'image/svg+xml', 'updated_at' => '2026-01-01 00:00:00'],
    ['id' => 'vid-id', 'site_id' => $siteId, 'path' => '/storage/uploads/x/clip.mp4', 'mime' => 'video/mp4', 'updated_at' => '2026-01-01 00:00:00'],
    ['id' => 'pdf-id', 'site_id' => $siteId, 'path' => '/storage/uploads/x/doc.pdf', 'mime' => 'application/pdf', 'updated_at' => '2026-01-01 00:00:00'],
    ['id' => 'prv-id', 'site_id' => $siteId, 'path' => '/storage/uploads/x/secret.jpg', 'mime' => 'image/jpeg', 'visibility' => 'private', 'updated_at' => '2026-01-01 00:00:00'],
]);

assert_test(Assets::url('gif-id', width: 300, height: 300) === 'gif-id', "An animated-capable GIF is never rasterized");
assert_test(Assets::url('svg-id', width: 300, height: 300) === 'svg-id', "An SVG is never rasterized");
assert_test(Assets::url('vid-id', width: 300, height: 300) === 'vid-id', "A video is passed through untouched");
assert_test(Assets::url('pdf-id', width: 300, height: 300) === 'pdf-id', "A non-image file is passed through untouched");
assert_test(Assets::url('prv-id', width: 300, height: 300) === 'prv-id', "Private media keeps its access-gated URL and never becomes a public variant");

assert_test(Assets::url('', width: 300) === '', "An empty source stays empty");
assert_test(Assets::url('/assets/img/theme-decoration.png', width: 300) === '/assets/img/theme-decoration.png', "A path with no media record behind it is returned unchanged");

Assets::clearRegistry();
Assets::prime([$baseRow]);
assert_test(Assets::url($mediaPath) === $mediaPath, "Requesting no dimensions returns the original URL rather than a pointless variant");

// --- 11. size() reports the dimensions the rendition will actually have ---
echo "  Testing computed variant dimensions...\n";
$coverSize = Assets::size($mediaPath, 600, 400);
$containSize = Assets::size($mediaPath, 600, null, Assets::FIT_CONTAIN);

assert_test($coverSize !== null && $coverSize['width'] === 600 && $coverSize['height'] === 400, "A cover variant reports the requested box");
assert_test($containSize !== null && $containSize['width'] === 600 && $containSize['height'] === 300, "A contain variant reports the aspect-preserved size for a 2000x1000 source");

$noDimensions = $baseRow;
$noDimensions['width'] = 0;
$noDimensions['height'] = 0;
Assets::clearRegistry();
Assets::prime([$noDimensions]);
assert_test(Assets::size($mediaPath, 600, 400) === null, "An unmeasured source reports null rather than guessing, so callers omit the attributes");

Assets::clearRegistry();
Assets::prime([$baseRow]);

// --- 12. srcset ---
echo "  Testing srcset generation...\n";
$srcset = Assets::srcset($mediaPath, [400, 800, 1200], 4 / 3);
$parts = explode(', ', $srcset);

assert_test(count($parts) === 3, "One srcset candidate per requested width");
assert_test(str_ends_with($parts[0], ' 400w'), "Each candidate carries its width descriptor");
assert_test(strpos($parts[0], '400x300-cover-') !== false, "An aspect ratio derives each candidate's height: {$parts[0]}");
assert_test(strpos($parts[2], '1200x900-cover-') !== false, "The largest candidate keeps the same aspect ratio");

$freeSrcset = Assets::srcset($mediaPath, [400, 800]);
assert_test(strpos($freeSrcset, '400x0-contain-') !== false, "Omitting the aspect ratio scales freely instead of cropping");

assert_test(Assets::srcset('gif-id', [400, 800]) === '', "srcset over an unresizable source is empty rather than listing one file under several widths");
assert_test(Assets::srcset('svg-id', [400, 800]) === '', "An SVG advertises no responsive ladder; it is already resolution independent");
assert_test(Assets::srcset($mediaPath, []) === '', "No candidate widths yields an empty srcset");

// --- 13. Metadata helpers read from the same primed registry ---
echo "  Testing metadata helpers...\n";
assert_test(Assets::title($mediaPath) === 'A Photographed Subject', "title() returns the stored title");
assert_test(Assets::mime($mediaPath) === 'image/jpeg', "mime() returns the stored mime type");

$untitled = $baseRow;
$untitled['title'] = '';
Assets::clearRegistry();
Assets::prime([$untitled]);
assert_test(Assets::title($mediaPath) === 'subject.jpg', "title() falls back to the filename when no title is set");
assert_test(Assets::title('/storage/uploads/x/unknown.jpg') === '', "title() of an unknown source is empty rather than an error");

Assets::clearRegistry();

echo "Assets variant URL tests completed successfully.\n\n";
