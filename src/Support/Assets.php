<?php

declare(strict_types=1);

/**
 * File: src/Support/Assets.php
 * Architectural Purpose: Global diagnostic tools, cryptographic security handlers, SMTP email transmitters, and text helpers.
 * Package: Zero\Support
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support;

use Zero\Core\App;
use Zero\Core\Env;
use Zero\Database\DB;

/**
 * Class Assets
 *
 * Mints deterministic, HMAC-signed URLs for focal-point-aware resized image variants, and owns
 * the pure geometry that decides how a source image maps onto a requested box.
 *
 * The defining property of this class is that url() performs no I/O whatsoever: no filesystem
 * stat, no database query, no network call. It derives the variant URL purely from metadata
 * already held in memory (primed in bulk by the block media eager-loaders), so a template can
 * emit a hundred variant URLs for the cost of a hundred hash computations. The image itself is
 * rendered lazily by MediaVariantController the first time a browser actually requests that URL,
 * and thereafter served straight off disk by the web server without PHP being involved at all.
 *
 * Anything this class cannot safely resize -- animated GIFs, SVGs, videos, non-image files,
 * access-gated private media, or a path that does not belong to a known media record -- is
 * returned untouched rather than rewritten, so callers can wrap every image URL unconditionally.
 */
class Assets
{
    /** Crop to completely fill the requested box, positioned on the image's focal point. */
    public const FIT_COVER = 'cover';

    /** Scale to fit entirely inside the requested box, preserving aspect ratio, never cropping. */
    public const FIT_CONTAIN = 'contain';

    /** Hard ceiling on any requested edge length, bounding worst-case generation cost. */
    public const MAX_DIMENSION = 4096;

    /** Default WebP encoder quality, sitting at the usual quality-versus-filesize knee point. */
    public const DEFAULT_QUALITY = 82;

    /** Every generated variant is WebP; the extension is part of the signed URL. */
    public const FORMAT_EXTENSION = 'webp';

    /** Web-root-relative directory holding the variant cache. */
    public const CACHE_DIRECTORY = 'storage/variants';

    /**
     * Image types that are deliberately never rasterized into a variant. GIF is excluded because
     * GD silently flattens an animated GIF to its first frame; SVG because it is resolution
     * independent already and rasterizing it is a downgrade.
     */
    protected const PASSTHROUGH_MIMES = ['image/gif', 'image/svg+xml'];

    /** @var array<string, array<string, mixed>> Request-scoped media metadata, keyed by id and by normalized path. */
    protected static $registry = [];

    /** @var array<string, bool> Memoized negative lookups, so an unresolvable source is queried at most once. */
    protected static $missed = [];

    /** @var string|null Memoized URL signing key. */
    protected static $signingKey = null;

    /** @var bool|null Memoized "can this build actually produce WebP" answer. */
    protected static $supported = null;

    /**
     * Forget every primed media record and memoized lookup.
     *
     * Exists for the test suite and for long-running CLI processes (bin/assets) that walk more
     * than one tenant in a single process and must not carry another site's metadata across.
     *
     * @return void
     */
    public static function clearRegistry(): void
    {
        self::$registry = [];
        self::$missed = [];
    }

    /**
     * Compute the geometry for scaling an image to fit inside a box without cropping.
     *
     * A zero width or height means "unconstrained on that axis". The scale factor is capped at
     * 1.0 so a variant is never upscaled past its source, which would cost bytes and add
     * blur without adding detail.
     *
     * @param int $sourceWidth Intrinsic width of the source image in pixels.
     * @param int $sourceHeight Intrinsic height of the source image in pixels.
     * @param int $targetWidth Requested box width, or 0 for unconstrained.
     * @param int $targetHeight Requested box height, or 0 for unconstrained.
     * @return array{source_x: int, source_y: int, source_width: int, source_height: int, width: int, height: int}
     */
    public static function computeContain(int $sourceWidth, int $sourceHeight, int $targetWidth, int $targetHeight): array
    {
        $scale = 1.0;
        if ($targetWidth > 0) {
            $scale = \min($scale, $targetWidth / $sourceWidth);
        }
        if ($targetHeight > 0) {
            $scale = \min($scale, $targetHeight / $sourceHeight);
        }

        return [
            'source_x' => 0,
            'source_y' => 0,
            'source_width' => $sourceWidth,
            'source_height' => $sourceHeight,
            'width' => \max(1, (int)\round($sourceWidth * $scale)),
            'height' => \max(1, (int)\round($sourceHeight * $scale)),
        ];
    }

    /**
     * Compute the geometry for cropping an image to exactly fill a box, centred on a focal point.
     *
     * Picks the largest rectangle inside the source that matches the target's aspect ratio, then
     * slides that rectangle so the focal point sits at its centre, clamping at the image edges so
     * the window never runs off the canvas. When the resulting crop is already smaller than the
     * requested box the output is emitted at the crop's own size instead of being upscaled --
     * the aspect ratio is identical either way, so callers still get the shape they asked for.
     *
     * @param int $sourceWidth Intrinsic width of the source image in pixels.
     * @param int $sourceHeight Intrinsic height of the source image in pixels.
     * @param int $targetWidth Requested box width in pixels.
     * @param int $targetHeight Requested box height in pixels.
     * @param int $focusX Horizontal focal point as a percentage from the left edge (0-100).
     * @param int $focusY Vertical focal point as a percentage from the top edge (0-100).
     * @return array{source_x: int, source_y: int, source_width: int, source_height: int, width: int, height: int}
     */
    public static function computeCrop(
        int $sourceWidth,
        int $sourceHeight,
        int $targetWidth,
        int $targetHeight,
        int $focusX = 50,
        int $focusY = 50
    ): array {
        $targetRatio = $targetWidth / $targetHeight;
        $sourceRatio = $sourceWidth / $sourceHeight;

        if ($sourceRatio > $targetRatio) {
            // Source is proportionally wider than the target box: full height, trim the sides.
            $cropHeight = $sourceHeight;
            $cropWidth = (int)\round($sourceHeight * $targetRatio);
        } else {
            // Source is proportionally taller than the target box: full width, trim top/bottom.
            $cropWidth = $sourceWidth;
            $cropHeight = (int)\round($sourceWidth / $targetRatio);
        }

        // Rounding can push the derived edge a pixel past the canvas; clamp both to be safe.
        $cropWidth = \max(1, \min($sourceWidth, $cropWidth));
        $cropHeight = \max(1, \min($sourceHeight, $cropHeight));

        $focusX = \max(0, \min(100, $focusX));
        $focusY = \max(0, \min(100, $focusY));

        $cropX = ($focusX / 100) * $sourceWidth - ($cropWidth / 2);
        $cropY = ($focusY / 100) * $sourceHeight - ($cropHeight / 2);
        $cropX = (int)\round(\max(0, \min($sourceWidth - $cropWidth, $cropX)));
        $cropY = (int)\round(\max(0, \min($sourceHeight - $cropHeight, $cropY)));

        // Never upscale: if the available crop is smaller than the box, emit it at native size.
        if ($cropWidth <= $targetWidth) {
            $outputWidth = $cropWidth;
            $outputHeight = $cropHeight;
        } else {
            $outputWidth = $targetWidth;
            $outputHeight = $targetHeight;
        }

        return [
            'source_x' => $cropX,
            'source_y' => $cropY,
            'source_width' => $cropWidth,
            'source_height' => $cropHeight,
            'width' => \max(1, $outputWidth),
            'height' => \max(1, $outputHeight),
        ];
    }

    /**
     * Resolve the full resampling geometry for a variant request against a known source size.
     *
     * @param int $sourceWidth Intrinsic width of the source image in pixels.
     * @param int $sourceHeight Intrinsic height of the source image in pixels.
     * @param int $targetWidth Requested width, or 0 for unconstrained.
     * @param int $targetHeight Requested height, or 0 for unconstrained.
     * @param string $fit Either self::FIT_COVER or self::FIT_CONTAIN.
     * @param int $focusX Horizontal focal point percentage (0-100).
     * @param int $focusY Vertical focal point percentage (0-100).
     * @return array{source_x: int, source_y: int, source_width: int, source_height: int, width: int, height: int}
     */
    public static function geometry(
        int $sourceWidth,
        int $sourceHeight,
        int $targetWidth,
        int $targetHeight,
        string $fit = self::FIT_COVER,
        int $focusX = 50,
        int $focusY = 50
    ): array {
        if ($fit === self::FIT_COVER && $targetWidth > 0 && $targetHeight > 0) {
            return self::computeCrop($sourceWidth, $sourceHeight, $targetWidth, $targetHeight, $focusX, $focusY);
        }

        return self::computeContain($sourceWidth, $sourceHeight, $targetWidth, $targetHeight);
    }

    /**
     * Determine whether this PHP build can actually produce the variant format.
     *
     * Checked before any URL is minted rather than at generation time: pointing markup at a
     * variant URL that the server has no way to render would turn a missing GD feature into a
     * page full of broken images instead of a silent, harmless fallback to the original file.
     *
     * @return bool
     */
    public static function isSupported(): bool
    {
        if (self::$supported === null) {
            self::$supported = \extension_loaded('gd') && \function_exists('imagewebp');
        }

        return self::$supported;
    }

    /**
     * Resolve the stored mime type for a media reference.
     *
     * Lets a template branch on media type -- image versus video versus vector -- without
     * issuing its own query, reading from the registry the eager-loader already primed.
     *
     * @param string $source A media id, stored path, or web path.
     * @return string The stored mime type, or an empty string when unknown.
     */
    public static function mime(string $source): string
    {
        $record = self::resolve($source);

        return $record === null ? '' : (string)($record['mime'] ?? '');
    }

    /**
     * Reduce any spelling of a stored media location to a single canonical registry key.
     *
     * The same underlying object is referred to by several different strings across the codebase:
     * the bare column value, the tenant-prefixed web path returned by the local driver, and the
     * fully-qualified bucket URL returned by the cloud drivers. Collapsing all of them to the
     * "storage/uploads/..." suffix means a caller can pass whichever one it happens to be
     * holding and still hit the primed record.
     *
     * @param string $source A media id, stored path, web path, or absolute object URL.
     * @return string The canonical registry key.
     */
    protected static function normalizeKey(string $source): string
    {
        $position = \strpos($source, 'storage/uploads/');
        if ($position !== false) {
            return \substr($source, $position);
        }

        return \ltrim($source, '/');
    }

    /**
     * Load a batch of media rows into the request-scoped registry.
     *
     * Called by the block media eager-loaders, which already run exactly one batched query per
     * page render; priming from those rows is what allows url() to stay free of I/O. Rows are
     * indexed under both their id and their canonical path so either form of reference resolves.
     *
     * @param array<int, array<string, mixed>> $rows Raw media table rows.
     * @return void
     */
    public static function prime(array $rows): void
    {
        foreach ($rows as $row) {
            if (empty($row['id'])) {
                continue;
            }

            $record = [
                'id' => (string)$row['id'],
                'site_id' => (string)($row['site_id'] ?? ''),
                'path' => (string)($row['path'] ?? ''),
                'mime' => (string)($row['mime'] ?? ''),
                'title' => (string)($row['title'] ?? ''),
                'filename' => (string)($row['filename'] ?? ''),
                'focus_x' => (int)($row['focus_x'] ?? 50),
                'focus_y' => (int)($row['focus_y'] ?? 50),
                'width' => isset($row['width']) ? (int)$row['width'] : 0,
                'height' => isset($row['height']) ? (int)$row['height'] : 0,
                'visibility' => (string)($row['visibility'] ?? 'public'),
                'version' => (string)($row['updated_at'] ?? $row['created_at'] ?? ''),
            ];

            self::$registry[$record['id']] = $record;
            if ($record['path'] !== '') {
                self::$registry[self::normalizeKey($record['path'])] = $record;
            }
        }
    }

    /**
     * Look up the media record backing a source reference.
     *
     * Hits the in-memory registry first. Only if a caller reached us without priming does this
     * fall back to a single lookup, and the result -- including a miss -- is memoized so an
     * unprimed template degrades to one query per distinct image rather than one per call.
     *
     * @param string $source A media id, stored path, or web path.
     * @return array<string, mixed>|null The media record, or null when nothing matches.
     */
    protected static function resolve(string $source): ?array
    {
        $key = self::normalizeKey($source);

        if (isset(self::$registry[$key])) {
            return self::$registry[$key];
        }
        if (isset(self::$missed[$key])) {
            return null;
        }

        self::$missed[$key] = true;

        $siteId = App::getCurrentSiteId();
        if (empty($siteId)) {
            return null;
        }

        try {
            $row = DB::query(
                "SELECT id, site_id, path, mime, focus_x, focus_y, visibility, created_at, updated_at
                 FROM media
                 WHERE (id = ? OR path = ? OR path = ?) AND site_id = ? AND deleted_at IS NULL
                 LIMIT 1",
                [$key, $source, '/' . $key, $siteId]
            )->fetch();
        } catch (\Exception $exception) {
            // A resolution failure must never take a page down: fall back to the original URL.
            return null;
        }

        if (empty($row)) {
            return null;
        }

        self::prime([$row]);

        return self::$registry[$key] ?? self::$registry[(string)$row['id']] ?? null;
    }

    /**
     * Compute the truncated HMAC that authorizes one specific variant of one specific image.
     *
     * This is what makes on-demand generation safe to expose: the renderer can only ever be
     * asked to produce a variant the CMS itself minted a URL for, so an attacker cannot walk
     * arbitrary dimensions to burn CPU or fill the disk. Folding the source's version stamp into
     * the payload doubles the signature as a cache key -- re-focusing or replacing an image
     * changes its version, which changes every URL, which makes the far-future immutable
     * caching headers on the generated files genuinely truthful.
     *
     * @param string $siteId Owning tenant id.
     * @param string $mediaId Media record id.
     * @param int $width Requested width, 0 for unconstrained.
     * @param int $height Requested height, 0 for unconstrained.
     * @param string $fit Fit mode.
     * @param int $quality Encoder quality.
     * @param string $version Source version stamp (the media row's update timestamp).
     * @return string A 16 character hex signature.
     */
    public static function signature(
        string $siteId,
        string $mediaId,
        int $width,
        int $height,
        string $fit,
        int $quality,
        string $version
    ): string {
        $payload = \implode('|', [$siteId, $mediaId, $width, $height, $fit, $quality, $version]);

        return \substr(\hash_hmac('sha256', $payload, self::signingKey()), 0, 16);
    }

    /**
     * Resolve the key used to sign variant URLs.
     *
     * Prefers an explicit APP_KEY. Falling back to a value derived from configuration that is
     * already secret keeps existing installations working without a config change, while still
     * being unguessable to an outsider.
     *
     * The derivation deliberately excludes the database *name*. The key has to be identical in
     * every process that touches an installation -- a URL minted by a web request is verified by
     * another request, and possibly by a CLI process -- and it has to survive a deployment, since
     * a key that rotates discards the entire variant cache with it. The database name is exactly
     * the sort of value that legitimately differs between processes and gets changed over an
     * installation's life, so folding it in would make the key quietly unstable.
     *
     * @return string
     */
    protected static function signingKey(): string
    {
        if (self::$signingKey === null) {
            $configured = (string)Env::get('APP_KEY', '');
            if ($configured !== '') {
                self::$signingKey = $configured;
            } else {
                self::$signingKey = \hash('sha256', \implode('|', [
                    'zero-cms-assets',
                    (string)Env::get('DB_USER', ''),
                    (string)Env::get('DB_PASS', ''),
                    (string)Env::get('BASE_URL', ''),
                ]));
            }
        }

        return self::$signingKey;
    }

    /**
     * Compute the exact pixel dimensions a variant URL will resolve to.
     *
     * Intended for emitting width/height attributes on an <img>, which lets the browser reserve
     * the correct space before the image arrives and eliminates layout shift. Requires the
     * source's intrinsic size to be known (recorded on upload); returns null when it is not,
     * so callers can omit the attributes rather than guess wrong.
     *
     * @param string $source A media id, stored path, or web path.
     * @param int|null $width Requested width.
     * @param int|null $height Requested height.
     * @param string $fit Fit mode.
     * @return array{width: int, height: int}|null
     */
    public static function size(string $source, ?int $width = null, ?int $height = null, string $fit = self::FIT_COVER): ?array
    {
        $record = self::resolve($source);
        if ($record === null || empty($record['width']) || empty($record['height'])) {
            return null;
        }

        $geometry = self::geometry(
            (int)$record['width'],
            (int)$record['height'],
            $width === null ? 0 : \max(1, \min(self::MAX_DIMENSION, $width)),
            $height === null ? 0 : \max(1, \min(self::MAX_DIMENSION, $height)),
            $fit,
            (int)$record['focus_x'],
            (int)$record['focus_y']
        );

        return ['width' => $geometry['width'], 'height' => $geometry['height']];
    }

    /**
     * Build a responsive srcset descriptor listing one variant per candidate width.
     *
     * Lets the browser download the smallest rendition that still covers the space it actually
     * allocated, which is usually the single largest bandwidth saving available on an
     * image-heavy page. Like url(), this is pure computation.
     *
     * Returns an empty string when the source yields no variants at all -- an SVG, an animated
     * GIF, a private file. Listing one unresized URL under several width descriptors would tell
     * the browser three renditions exist when only one file does, so it could pick the "800w"
     * candidate believing it is getting 800px of detail. Better to publish nothing and let the
     * caller's plain src attribute stand alone.
     *
     * @param string $source A media id, stored path, or web path.
     * @param array<int, int> $widths Candidate widths in pixels.
     * @param float|null $aspect Width-to-height ratio to crop each candidate to, or null to scale freely.
     * @param string $fit Fit mode used when $aspect is supplied.
     * @param int|null $quality Encoder quality override.
     * @return string A srcset attribute value, or an empty string when no variants apply.
     */
    public static function srcset(
        string $source,
        array $widths,
        ?float $aspect = null,
        string $fit = self::FIT_COVER,
        ?int $quality = null
    ): string {
        $candidates = [];

        foreach ($widths as $candidateWidth) {
            $candidateWidth = (int)$candidateWidth;
            if ($candidateWidth < 1) {
                continue;
            }

            $candidateHeight = ($aspect !== null && $aspect > 0)
                ? \max(1, (int)\round($candidateWidth / $aspect))
                : null;

            $url = self::url($source, $candidateWidth, $candidateHeight, $candidateHeight === null ? self::FIT_CONTAIN : $fit, $quality);

            // url() hands back its own argument when the source cannot be resized, which is the
            // signal that there is no ladder of renditions to describe here.
            if ($url === '' || $url === $source) {
                continue;
            }

            $candidates[] = $url . ' ' . $candidateWidth . 'w';
        }

        return \implode(', ', $candidates);
    }

    /**
     * Resolve the human-readable caption for a media reference.
     *
     * Reads from the same primed registry that url() uses, so a template can label an image
     * without a per-item lookup. Gallery and lightbox markup previously did this by calling a
     * model finder inside its render loop, which is exactly the N+1 the eager-loader exists to
     * prevent.
     *
     * @param string $source A media id, stored path, or web path.
     * @return string The stored title, falling back to the filename, then an empty string.
     */
    public static function title(string $source): string
    {
        $record = self::resolve($source);
        if ($record === null) {
            return '';
        }

        $title = (string)($record['title'] ?? '');

        return $title !== '' ? $title : (string)($record['filename'] ?? '');
    }

    /**
     * Mint the URL of a resized, focal-point-cropped variant of an image.
     *
     * Performs no I/O. Anything that cannot be resized safely -- an unknown source, a non-image,
     * an animated GIF, an SVG, an access-gated private file, or a build without WebP support --
     * is returned exactly as passed in, so this is safe to wrap around every image URL
     * unconditionally. Supplying only one dimension scales proportionally without cropping;
     * supplying both crops to fill, positioned on the image's stored focal point.
     *
     * @param string $source A media id, stored path, or web path.
     * @param int|null $width Requested width in pixels, or null to derive it from the height.
     * @param int|null $height Requested height in pixels, or null to derive it from the width.
     * @param string $fit Either self::FIT_COVER (default) or self::FIT_CONTAIN.
     * @param int|null $quality Encoder quality 1-100, defaulting to self::DEFAULT_QUALITY.
     * @return string The variant URL, or the original source when no variant applies.
     */
    public static function url(
        string $source,
        ?int $width = null,
        ?int $height = null,
        string $fit = self::FIT_COVER,
        ?int $quality = null
    ): string {
        if ($source === '') {
            return '';
        }
        if ($width === null && $height === null) {
            return $source;
        }
        if (!self::isSupported()) {
            return $source;
        }

        $record = self::resolve($source);
        if ($record === null) {
            return $source;
        }

        $mime = (string)$record['mime'];
        if (\strpos($mime, 'image/') !== 0 || \in_array($mime, self::PASSTHROUGH_MIMES, true)) {
            return $source;
        }
        if (($record['visibility'] ?? 'public') === 'private') {
            return $source;
        }

        $width = $width === null ? 0 : \max(1, \min(self::MAX_DIMENSION, $width));
        $height = $height === null ? 0 : \max(1, \min(self::MAX_DIMENSION, $height));

        if ($width === 0 || $height === 0) {
            // A single dimension cannot describe a crop window, so this is always a plain scale.
            $fit = self::FIT_CONTAIN;
        } elseif ($fit !== self::FIT_COVER && $fit !== self::FIT_CONTAIN) {
            $fit = self::FIT_COVER;
        }

        $quality = $quality === null ? self::DEFAULT_QUALITY : \max(1, \min(100, $quality));

        $siteId = $record['site_id'] !== '' ? (string)$record['site_id'] : (string)App::getCurrentSiteId();
        if ($siteId === '') {
            return $source;
        }

        return '/' . self::variantPath(
            $siteId,
            (string)$record['id'],
            $width,
            $height,
            $fit,
            $quality,
            (string)$record['version']
        );
    }

    /**
     * Build the web-root-relative cache path for one variant.
     *
     * The path doubles as the URL, which is the whole point: once the file exists the web server
     * satisfies the request off disk and PHP is never invoked. Files are sharded by the first
     * two signature characters so a busy tenant does not accumulate a single directory with
     * hundreds of thousands of entries.
     *
     * @param string $siteId Owning tenant id.
     * @param string $mediaId Media record id.
     * @param int $width Requested width, 0 for unconstrained.
     * @param int $height Requested height, 0 for unconstrained.
     * @param string $fit Fit mode.
     * @param int $quality Encoder quality.
     * @param string $version Source version stamp.
     * @return string Relative path with no leading slash.
     */
    public static function variantPath(
        string $siteId,
        string $mediaId,
        int $width,
        int $height,
        string $fit,
        int $quality,
        string $version
    ): string {
        $signature = self::signature($siteId, $mediaId, $width, $height, $fit, $quality, $version);
        $shard = \substr($signature, 0, 2);
        $filename = "{$width}x{$height}-{$fit}-q{$quality}-{$signature}." . self::FORMAT_EXTENSION;

        return self::CACHE_DIRECTORY . "/{$siteId}/{$shard}/{$mediaId}/{$filename}";
    }
}
