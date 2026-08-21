<?php

declare(strict_types=1);

/**
 * File: src/Http/Controllers/MediaVariantController.php
 * Architectural Purpose: HTTP request routing, request filtering middleware, or dynamic content-security controllers.
 * Package: Zero\Http\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Http\Controllers;

use Zero\Core\App;
use Zero\Core\Storage\Storage;
use Zero\Database\DB;
use Zero\Interfaces\Controller;
use Zero\Support\Assets;
use Zero\Support\ImageProcessor;
use Zero\Support\VariantCache;

/**
 * Class MediaVariantController
 *
 * The cache-miss handler for resized image variants: renders one rendition on demand, stores it
 * at the exact path its own URL describes, and streams it back with immutable caching headers.
 *
 * This controller is reached only when the requested variant does not yet exist on disk -- the
 * rewrite rule in public/.htaccess lets the web server satisfy /storage/variants/ requests
 * directly and only falls through to PHP when the file is absent. Every subsequent request for
 * the same variant is therefore served statically, with no PHP process involved, which is what
 * keeps a page full of resized imagery as cheap to serve as one full of plain static files.
 *
 * Generation is gated on the signature embedded in the URL. Only a URL that Assets itself minted
 * will verify, so the endpoint cannot be walked with arbitrary dimensions to burn CPU or fill the
 * disk, and the dimensions it will honour are additionally bounded by Assets::MAX_DIMENSION.
 */
class MediaVariantController implements Controller
{
    /**
     * The signed variant URL pattern. Kept here rather than at the registration site so the
     * capture-group order and this controller's reading of it cannot drift apart.
     */
    public const ROUTE_PATTERN = '#^/storage/variants/([A-Za-z0-9\-]{1,64})/([0-9a-f]{2})/([A-Za-z0-9\-]{1,64})/(\d{1,4})x(\d{1,4})-(cover|contain)-q(\d{1,3})-([0-9a-f]{16})\.webp$#';

    /**
     * Load the media record a variant request refers to, scoped to the owning tenant.
     *
     * @param string $siteId Owning tenant id.
     * @param string $mediaId Media record id.
     * @return array<string, mixed>|null The media row, or null when it does not exist.
     */
    protected function findMedia(string $siteId, string $mediaId): ?array
    {
        try {
            $row = DB::query(
                "SELECT id, site_id, path, mime, focus_x, focus_y, visibility, created_at, updated_at
                 FROM media
                 WHERE id = ? AND site_id = ? AND deleted_at IS NULL
                 LIMIT 1",
                [$mediaId, $siteId]
            )->fetch();
        } catch (\Exception $exception) {
            \error_log('Media variant: media lookup failed: ' . $exception->getMessage());

            return null;
        }

        if (empty($row) || empty($row['path'])) {
            return null;
        }
        // Private media is served through the access-gated download route and must never become
        // reachable as an unauthenticated static variant.
        if (($row['visibility'] ?? 'public') === 'private') {
            return null;
        }

        return $row;
    }

    /**
     * Render the variant, publish it into the cache, and stream it to the client.
     *
     * @param string $relativePath Cache-relative destination path.
     * @param string $sourcePath Stored path of the source image.
     * @param int $width Requested width, 0 for unconstrained.
     * @param int $height Requested height, 0 for unconstrained.
     * @param string $fit Fit mode.
     * @param int $quality Encoder quality.
     * @param int $focusX Horizontal focal point percentage.
     * @param int $focusY Vertical focal point percentage.
     * @return void
     */
    protected function generate(
        string $relativePath,
        string $sourcePath,
        int $width,
        int $height,
        string $fit,
        int $quality,
        int $focusX,
        int $focusY
    ): void {
        $lock = VariantCache::lock($relativePath);

        try {
            // Another request may have rendered this exact variant while this one waited on the
            // lock, in which case the work is already done and there is nothing left to render.
            if ($lock !== null) {
                $cached = VariantCache::fetch($relativePath);
                if ($cached !== null) {
                    VariantCache::release($lock, $relativePath);
                    $this->respond($cached);
                }
            }

            $source = Storage::read($sourcePath);
            if ($source === null || $source === '') {
                VariantCache::release($lock, $relativePath);
                $this->notFound();
            }

            $variant = ImageProcessor::render($source, $width, $height, $fit, $focusX, $focusY, $quality);
            VariantCache::store($relativePath, $variant['bytes']);
            VariantCache::release($lock, $relativePath);

            $this->respond($variant['bytes']);
        } catch (\Exception $exception) {
            \error_log('Media variant: render failed for ' . $relativePath . ': ' . $exception->getMessage());
            VariantCache::release($lock, $relativePath);

            // Degrade to the untouched original rather than a broken image. The redirect is
            // explicitly not cacheable, so a transient failure never gets pinned into a CDN.
            $this->redirectToOriginal($sourcePath);
        }
    }

    /**
     * Render, cache, and serve one signed image variant.
     *
     * @param mixed $param The route's regex match array.
     * @return void
     */
    public function handle($param)
    {
        $siteId = (string)($param[1] ?? '');
        $shard = (string)($param[2] ?? '');
        $mediaId = (string)($param[3] ?? '');
        $width = (int)($param[4] ?? 0);
        $height = (int)($param[5] ?? 0);
        $fit = (string)($param[6] ?? Assets::FIT_COVER);
        $quality = (int)($param[7] ?? Assets::DEFAULT_QUALITY);
        $signature = (string)($param[8] ?? '');

        $relativePath = Assets::CACHE_DIRECTORY
            . "/{$siteId}/{$shard}/{$mediaId}/{$width}x{$height}-{$fit}-q{$quality}-{$signature}."
            . Assets::FORMAT_EXTENSION;

        // A cached variant should normally have been served by the web server before PHP was
        // ever consulted. Checking anyway covers the cloud drivers (where this instance's disk
        // starts empty but the bucket already holds the object) and any deployment whose rewrite
        // rules are not in force, such as PHP's built-in development server.
        $cached = VariantCache::fetch($relativePath);
        if ($cached !== null) {
            $this->respond($cached);
        }

        // Tenant isolation: a variant may only ever be rendered for the site being served, so a
        // signed URL leaked from one tenant cannot be replayed against another.
        if ($siteId === '' || $siteId !== (string)App::getCurrentSiteId()) {
            $this->notFound();
        }

        $media = $this->findMedia($siteId, $mediaId);
        if ($media === null) {
            $this->notFound();
        }

        $version = (string)($media['updated_at'] ?? $media['created_at'] ?? '');
        $expected = Assets::signature($siteId, $mediaId, $width, $height, $fit, $quality, $version);

        // A stale signature means the URL was minted before the image was re-focused or replaced,
        // or else was tampered with. Either way there is nothing legitimate left to render: the
        // current version of the image is published under a different URL entirely.
        if (!\hash_equals($expected, $signature) || $shard !== \substr($expected, 0, 2)) {
            $this->notFound();
        }

        $this->generate(
            $relativePath,
            (string)$media['path'],
            $width,
            $height,
            $fit,
            $quality,
            (int)$media['focus_x'],
            (int)$media['focus_y']
        );
    }

    /**
     * Emit a non-cacheable 404 and terminate the request.
     *
     * @return void
     */
    protected function notFound(): void
    {
        \http_response_code(404);
        \header('Cache-Control: no-store, max-age=0');
        \header('Content-Type: text/plain; charset=UTF-8');
        echo 'Image variant not found';
        exit;
    }

    /**
     * Send the client to the unmodified source image and terminate the request.
     *
     * @param string $sourcePath Stored path of the source image.
     * @return void
     */
    protected function redirectToOriginal(string $sourcePath): void
    {
        $target = '';
        try {
            $target = Storage::getUrl($sourcePath);
        } catch (\Exception $exception) {
            $target = '';
        }

        if ($target === '') {
            $this->notFound();
        }

        \http_response_code(302);
        \header('Cache-Control: no-store, max-age=0');
        \header('Location: ' . $target);
        exit;
    }

    /**
     * Stream variant bytes with far-future immutable caching, and terminate the request.
     *
     * The URL embeds a signature over the source's version stamp, so a given URL's bytes can
     * never change -- which is what makes "immutable" an accurate claim here rather than an
     * optimistic one, and lets browsers and CDNs keep the file indefinitely without revalidating.
     *
     * @param string $bytes The encoded variant.
     * @return void
     */
    protected function respond(string $bytes): void
    {
        $etag = '"' . \md5($bytes) . '"';

        \header('Content-Type: image/' . Assets::FORMAT_EXTENSION);
        \header('Cache-Control: public, max-age=31536000, immutable');
        \header('ETag: ' . $etag);

        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            \http_response_code(304);
            exit;
        }

        \header('Content-Length: ' . \strlen($bytes));
        echo $bytes;
        exit;
    }
}
