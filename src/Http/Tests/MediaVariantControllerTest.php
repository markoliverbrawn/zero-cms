<?php
// src/Http/Tests/MediaVariantControllerTest.php
// Drives the on-demand image variant endpoint through the real request pipeline: renders a
// rendition on a cache miss, publishes it into the variant cache, and refuses anything the CMS
// did not sign.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Support\Assets;
use Zero\Support\ImageProcessor;
use Zero\Support\Security;
use Zero\Support\TestRequest;
use Zero\Support\VariantCache;

echo "=== Media Variant Endpoint Tests ===\n";

if (!Assets::isSupported()) {
    echo "  Skipping: this PHP build has no GD WebP support, so no variants are ever rendered.\n";
    exit(0);
}

// --- Fixtures -----------------------------------------------------------------------------
// A signed variant URL embeds the tenant id, so the id has to be chosen here, before the request
// is dispatched -- hence the explicit id handed to onSite(). The tenant itself is still created
// inside the request's own process, because that process truncates the sites table on its first
// database access and would otherwise wipe a tenant inserted out here.
$siteId = Security::uuidv7();
$otherSiteId = Security::uuidv7();
$mediaId = Security::uuidv7();
$deletedMediaId = Security::uuidv7();
$domain = 'variant-' . bin2hex(random_bytes(6)) . '.zero';
$otherDomain = 'variant-other-' . bin2hex(random_bytes(6)) . '.zero';
$now = gmdate('Y-m-d H:i:s');

$uploadsDir = APPLICATION_ROOT . '/public/storage/uploads/' . $siteId;
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0775, true);
}

// An 800x400 landscape source split into two solid halves, so a focal-point crop is verifiable
// from the output pixels rather than only from the geometry unit tests.
$sourceFilename = 'variant-source.jpg';
$sourcePath = confine_test_path($uploadsDir . '/' . $sourceFilename, $uploadsDir);

/**
 * Write (or rewrite) the source fixture image.
 *
 * Called before each request rather than once up front, because public/storage/uploads is shared
 * global state across the parallel worker slots: SeederScriptTest shells out to bin/seed, which
 * recursively cleans the entire uploads root, so a fixture written once can be deleted underneath
 * a concurrently running suite. Rewriting it is cheap and removes the race entirely.
 *
 * @param string $path Absolute destination path.
 * @return void
 */
function writeVariantSource(string $path): void
{
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $canvas = imagecreatetruecolor(800, 400);
    imagefilledrectangle($canvas, 0, 0, 399, 399, imagecolorallocate($canvas, 255, 0, 0));
    imagefilledrectangle($canvas, 400, 0, 799, 399, imagecolorallocate($canvas, 0, 0, 255));
    imagejpeg($canvas, $path, 95);
    imagedestroy($canvas);
}

writeVariantSource($sourcePath);
assert_critical(file_exists($sourcePath), 'Source fixture image was created on disk');

$mediaPath = '/storage/uploads/' . $siteId . '/' . $sourceFilename;

// focus_x of 90 puts the subject in the right-hand half, which is what makes the crop position
// observable in the rendered pixels below.
$mediaFixture = [
    'id' => $mediaId,
    'site_id' => $siteId,
    'filename' => $sourceFilename,
    'path' => $mediaPath,
    'mime' => 'image/jpeg',
    'title' => 'Variant Source',
    'focus_x' => 90,
    'focus_y' => 50,
    'width' => 800,
    'height' => 400,
    'visibility' => 'public',
    'created_at' => $now,
    'updated_at' => $now,
];

$deletedFixture = [
    'id' => $deletedMediaId,
    'site_id' => $siteId,
    'filename' => 'gone.jpg',
    'path' => '/storage/uploads/' . $siteId . '/gone.jpg',
    'mime' => 'image/jpeg',
    'focus_x' => 50,
    'focus_y' => 50,
    'width' => 800,
    'height' => 400,
    'created_at' => $now,
    'updated_at' => $now,
    'deleted_at' => $now,
];

Assets::clearRegistry();
Assets::prime([$mediaFixture]);

/**
 * Dispatch a GET for a variant URL against a tenant that the request itself creates.
 *
 * @param string $url The variant URL to request.
 * @param string $requestSiteId Tenant id to create and resolve the request against.
 * @param string $requestDomain Domain for that tenant.
 * @param array<int, array<string, mixed>> $mediaRows Media fixtures the request needs.
 * @return array{stdout: string, stderr: string, exit_code: int}
 */
function requestVariant(string $url, string $requestSiteId, string $requestDomain, array $mediaRows): array
{
    global $sourcePath;
    writeVariantSource($sourcePath);

    $request = TestRequest::get($url)->onSite(['id' => $requestSiteId, 'domain' => $requestDomain]);
    foreach ($mediaRows as $row) {
        $request->withMedia($row);
    }

    return $request->send();
}

$createdPaths = [];

// --- 1. A cache miss renders, caches, and streams the variant ------------------------------
echo "  Testing cold cache miss rendering...\n";
$variantUrl = Assets::url($mediaPath, width: 200, height: 200);
$absolutePath = VariantCache::absolutePath(ltrim($variantUrl, '/'));
$createdPaths[] = $absolutePath;

assert_test(!file_exists($absolutePath), 'Variant does not exist on disk before the first request');

$response = requestVariant($variantUrl, $siteId, $domain, [$mediaFixture]);

$probe = ImageProcessor::probe($response['stdout']);
assert_critical($probe !== null, 'Response body is a decodable image (stderr: ' . trim(substr($response['stderr'], 0, 400)) . ')');
assert_test($probe['mime'] === 'image/webp', "Response is WebP, got {$probe['mime']}");
assert_test($probe['width'] === 200 && $probe['height'] === 200, "Response is exactly 200x200, got {$probe['width']}x{$probe['height']}");

assert_test(file_exists($absolutePath), 'Variant was published into the cache at the exact path its URL describes');
assert_test(
    file_exists($absolutePath) && file_get_contents($absolutePath) === $response['stdout'],
    'Cached file is byte-identical to what was streamed, so later static hits serve the same image'
);

// The cache must sit outside the uploads tree: contaminating a user's own media folder with
// generated renditions is the specific failure mode this layout exists to avoid.
assert_test(strpos($absolutePath, '/storage/variants/') !== false, 'Variant is cached under storage/variants');
assert_test(strpos($absolutePath, '/storage/uploads/') === false, 'Variant is never written inside the uploads tree');
assert_test(!is_dir($uploadsDir . '/_crops'), 'No crop folder is created alongside the original upload');
assert_test(
    count(glob($uploadsDir . '/*')) === 1,
    'The upload directory still holds exactly one file: the original'
);

// --- 2. The crop honours the focal point ---------------------------------------------------
echo "  Testing focal-point-aware cropping...\n";
$cropped = imagecreatefromstring($response['stdout']);
assert_critical($cropped !== false, 'Rendered variant can be decoded for inspection');

$centre = imagecolorat($cropped, 100, 100);
$red = ($centre >> 16) & 0xFF;
$blue = $centre & 0xFF;
assert_test($blue > 200 && $red < 60, "A focal point at 90% crops to the right-hand (blue) half; sampled r={$red} b={$blue}");
imagedestroy($cropped);

// --- 3. A warm cache is served without re-rendering ----------------------------------------
echo "  Testing warm cache hit...\n";
$modifiedBefore = filemtime($absolutePath);
$warm = requestVariant($variantUrl, $siteId, $domain, [$mediaFixture]);

assert_test($warm['stdout'] === $response['stdout'], 'A second request returns the identical bytes');
assert_test(filemtime($absolutePath) === $modifiedBefore, 'A second request did not re-render or rewrite the cached file');

// --- 4. Unsigned and tampered URLs are refused before anything is rendered ------------------
echo "  Testing rejection of unsigned and tampered URLs...\n";
$tamperedUrl = str_replace('200x200', '1999x1999', $variantUrl);
$tamperedAbsolute = VariantCache::absolutePath(ltrim($tamperedUrl, '/'));
$createdPaths[] = $tamperedAbsolute;
$tampered = requestVariant($tamperedUrl, $siteId, $domain, [$mediaFixture]);

assert_test(strpos($tampered['stdout'], 'Image variant not found') !== false, 'Resizing to unsigned dimensions is refused');
assert_test(!file_exists($tamperedAbsolute), 'A refused request renders nothing and writes nothing to disk');

$forgedUrl = preg_replace('/-[0-9a-f]{16}\.webp$/', '-00112233445566ff.webp', $variantUrl);
$forged = requestVariant($forgedUrl, $siteId, $domain, [$mediaFixture]);
assert_test(strpos($forged['stdout'], 'Image variant not found') !== false, 'A forged signature is refused');

// --- 5. Cross-tenant replay is refused -----------------------------------------------------
echo "  Testing tenant isolation...\n";
// A genuinely signed URL, requested through a different tenant's host. The signature verifies
// against its own site id, but the request is not being served for that site.
$replayUrl = Assets::url($mediaPath, width: 128, height: 128);
$replayAbsolute = VariantCache::absolutePath(ltrim($replayUrl, '/'));
$createdPaths[] = $replayAbsolute;
$replay = requestVariant($replayUrl, $otherSiteId, $otherDomain, [$mediaFixture]);

assert_test(strpos($replay['stdout'], 'Image variant not found') !== false, 'A signed URL replayed against another tenant is refused');
assert_test(!file_exists($replayAbsolute), 'A cross-tenant replay renders nothing');

// --- 6. A soft-deleted source yields a clean refusal ---------------------------------------
echo "  Testing a variant of soft-deleted media...\n";
$deletedSignature = Assets::signature($siteId, $deletedMediaId, 200, 200, Assets::FIT_COVER, Assets::DEFAULT_QUALITY, $now);
$deletedUrl = '/' . Assets::CACHE_DIRECTORY . "/{$siteId}/" . substr($deletedSignature, 0, 2)
    . "/{$deletedMediaId}/200x200-cover-q82-{$deletedSignature}.webp";
$deleted = requestVariant($deletedUrl, $siteId, $domain, [$mediaFixture, $deletedFixture]);

assert_test(strpos($deleted['stdout'], 'Image variant not found') !== false, 'A soft-deleted media record renders no variant');

// --- 7. Scaled renditions and the no-upscale guarantee -------------------------------------
echo "  Testing scaled (contain) renditions through the endpoint...\n";
$scaledUrl = Assets::url($mediaPath, width: 400);
$createdPaths[] = VariantCache::absolutePath(ltrim($scaledUrl, '/'));
$scaled = requestVariant($scaledUrl, $siteId, $domain, [$mediaFixture]);
$scaledProbe = ImageProcessor::probe($scaled['stdout']);

assert_test($scaledProbe !== null, 'Scaled rendition returned a decodable image');
if ($scaledProbe !== null) {
    assert_test(
        $scaledProbe['width'] === 400 && $scaledProbe['height'] === 200,
        "Width-only request preserves the 2:1 aspect ratio, got {$scaledProbe['width']}x{$scaledProbe['height']}"
    );
}

$upscaleUrl = Assets::url($mediaPath, width: 3000);
$createdPaths[] = VariantCache::absolutePath(ltrim($upscaleUrl, '/'));
$upscaled = requestVariant($upscaleUrl, $siteId, $domain, [$mediaFixture]);
$upscaleProbe = ImageProcessor::probe($upscaled['stdout']);

assert_test($upscaleProbe !== null, 'Oversized request returned a decodable image');
if ($upscaleProbe !== null) {
    assert_test($upscaleProbe['width'] === 800, "A request larger than the source is served at the source's own width, got {$upscaleProbe['width']}");
}

// --- 8. Invalidation reclaims one media item's renditions -----------------------------------
echo "  Testing variant cache invalidation...\n";
writeVariantSource($sourcePath);
assert_test(file_exists($absolutePath), 'Cached variants exist before invalidation');
$purged = VariantCache::forget($siteId, $mediaId);

assert_test($purged > 0, "Invalidation removed {$purged} cached rendition(s) for this media item");
assert_test(!file_exists($absolutePath), 'The cached variant is gone after invalidation');
assert_test(file_exists($sourcePath), 'Invalidating renditions never touches the original upload');

// --- 9. Path confinement -------------------------------------------------------------------
echo "  Testing variant cache path confinement...\n";
$rejected = 0;
foreach (['storage/uploads/evil.webp', 'storage/variants/../../evil.webp', 'etc/passwd'] as $hostilePath) {
    try {
        VariantCache::absolutePath($hostilePath);
    } catch (\InvalidArgumentException $exception) {
        $rejected++;
    }
}
assert_test($rejected === 3, "Every path outside the variant cache is rejected ({$rejected} of 3)");

// --- Cleanup -------------------------------------------------------------------------------
VariantCache::clear($siteId);
VariantCache::clear($otherSiteId);
foreach ($createdPaths as $path) {
    if (file_exists($path)) {
        unlink($path);
    }
}
if (file_exists($sourcePath)) {
    unlink($sourcePath);
}
if (is_dir($uploadsDir)) {
    rmdir($uploadsDir);
}

Assets::clearRegistry();

echo "Media variant endpoint tests completed successfully.\n\n";
