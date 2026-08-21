<?php

declare(strict_types=1);

/**
 * File: src/Support/VariantCache.php
 * Architectural Purpose: Global diagnostic tools, cryptographic security handlers, SMTP email transmitters, and text helpers.
 * Package: Zero\Support
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support;

use Zero\Core\Storage\Storage;

/**
 * Class VariantCache
 *
 * Owns the on-disk lifecycle of generated image variants: where they live, how they are written
 * without a concurrent request ever observing a half-written file, and how they are reclaimed.
 *
 * The cache lives under public/storage/variants, deliberately a sibling of the uploads tree
 * rather than a folder inside it. That single decision is what keeps derived renditions from
 * contaminating a user's own media: the file manager only ever lists uploads, a media record's
 * force-delete only ever touches its own object, and the entire cache can be thrown away with
 * one recursive delete without risking an original.
 *
 * Because the cache path is also the public URL, a variant that exists is served directly by the
 * web server and never reaches PHP. This class is therefore only exercised on a cache miss, on
 * an invalidation, or from the bin/assets maintenance CLI.
 *
 * Under the cloud storage drivers the local filesystem is ephemeral -- a container can be
 * replaced at any moment -- so a variant is written to both the local disk (a hot per-instance
 * cache the web server can serve statically) and the configured bucket (the durable copy a
 * freshly started instance rehydrates from instead of re-rendering).
 */
class VariantCache
{
    /**
     * Resolve a cache-relative variant path to an absolute local filesystem path.
     *
     * Rejects anything that would escape the cache directory. The callers all pass paths built
     * by Assets or matched by a strict route pattern, so this is defence in depth rather than
     * the primary control -- but it is the check that makes the class safe to hand a path that
     * originated in a URL.
     *
     * @param string $relativePath Path relative to the web root, e.g. "storage/variants/...".
     * @return string The absolute local path.
     * @throws \InvalidArgumentException If the path escapes the variant cache directory.
     */
    public static function absolutePath(string $relativePath): string
    {
        $relativePath = \ltrim($relativePath, '/');

        if (\strpos($relativePath, Assets::CACHE_DIRECTORY . '/') !== 0) {
            throw new \InvalidArgumentException('Security exception: path is not inside the variant cache.');
        }
        if (\strpos($relativePath, '..') !== false || \strpos($relativePath, "\0") !== false || \strpos($relativePath, '\\') !== false) {
            throw new \InvalidArgumentException('Security exception: malformed variant cache path.');
        }

        $suffix = \substr($relativePath, \strlen(Assets::CACHE_DIRECTORY));

        return Storage::getVariantsRoot() . $suffix;
    }

    /**
     * Delete every cached variant, optionally limited to one tenant.
     *
     * @param string|null $siteId Tenant to clear, or null for every tenant.
     * @return int The number of files deleted locally.
     */
    public static function clear(?string $siteId = null): int
    {
        $root = Storage::getVariantsRoot();
        if ($siteId !== null) {
            if (\preg_match('/^[A-Za-z0-9\-]{1,64}$/', $siteId) !== 1) {
                throw new \InvalidArgumentException('Security exception: malformed site identifier.');
            }
            $root .= '/' . $siteId;
        }

        $deleted = self::deleteTree($root, 0);

        if (!Storage::isLocalDriver()) {
            $remotePrefix = 'public/' . Assets::CACHE_DIRECTORY . ($siteId !== null ? '/' . $siteId : '');
            try {
                Storage::cleanDirectory($remotePrefix);
            } catch (\Exception $exception) {
                \error_log('Variant cache: remote clear failed: ' . $exception->getMessage());
            }
        }

        return $deleted;
    }

    /**
     * Recursively delete files under a directory, keeping only those newer than a cutoff.
     *
     * @param string $directory Absolute directory to walk.
     * @param int $cutoff Unix timestamp; files modified at or after it are kept. 0 deletes all.
     * @return int The number of files deleted.
     */
    protected static function deleteTree(string $directory, int $cutoff): int
    {
        if (!\is_dir($directory)) {
            return 0;
        }

        $deleted = 0;
        $entries = \scandir($directory);
        if ($entries === false) {
            return 0;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (\is_dir($path)) {
                $deleted += self::deleteTree($path, $cutoff);
                // Drop the directory once its last variant is gone, so a long-lived cache does
                // not accumulate hundreds of thousands of empty shard folders over time.
                $remaining = \scandir($path);
                if (\is_array($remaining) && \count($remaining) <= 2) {
                    \rmdir($path);
                }
                continue;
            }

            if ($cutoff > 0) {
                $modified = \filemtime($path);
                if ($modified !== false && $modified >= $cutoff) {
                    continue;
                }
            }

            if (\unlink($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Retrieve a cached variant's bytes, rehydrating the local copy from the bucket if needed.
     *
     * @param string $relativePath Cache-relative variant path.
     * @return string|null The variant bytes, or null when it has not been generated yet.
     */
    public static function fetch(string $relativePath): ?string
    {
        $absolute = self::absolutePath($relativePath);

        if (\is_file($absolute)) {
            $bytes = \file_get_contents($absolute);
            if ($bytes !== false && $bytes !== '') {
                return $bytes;
            }
        }

        if (Storage::isLocalDriver()) {
            return null;
        }

        // Cloud driver: this instance's disk is empty but another instance may already have
        // rendered the variant into the bucket. Pulling it back is far cheaper than re-rendering,
        // and writing it locally means the web server serves every later hit on this instance.
        try {
            $bytes = Storage::read('public/' . \ltrim($relativePath, '/'));
        } catch (\Exception $exception) {
            \error_log('Variant cache: remote fetch failed: ' . $exception->getMessage());

            return null;
        }

        if ($bytes === null || $bytes === '') {
            return null;
        }

        self::writeLocal($absolute, $bytes);

        return $bytes;
    }

    /**
     * Discard every cached variant belonging to one media record.
     *
     * Called when an image is replaced or its focal point moves. Strictly speaking this is a
     * housekeeping measure rather than a correctness one: a variant URL embeds the source's
     * version stamp, so editing an image already changes every URL that points at it and the
     * superseded files simply stop being referenced. Deleting them promptly is what stops the
     * cache growing without bound as editors iterate on a crop.
     *
     * @param string $siteId Owning tenant id.
     * @param string $mediaId Media record id.
     * @return int The number of files deleted locally.
     */
    public static function forget(string $siteId, string $mediaId): int
    {
        if (\preg_match('/^[A-Za-z0-9\-]{1,64}$/', $siteId) !== 1 || \preg_match('/^[A-Za-z0-9\-]{1,64}$/', $mediaId) !== 1) {
            return 0;
        }

        // A variant's shard directory is derived from its signature, so the shard holding any
        // given media id is not knowable up front -- but the media id is its own path segment,
        // which turns the search into one cheap glob across the tenant's shard directories.
        $pattern = Storage::getVariantsRoot() . "/{$siteId}/*/{$mediaId}";
        $matches = \glob($pattern, GLOB_ONLYDIR);
        if (!\is_array($matches)) {
            return 0;
        }

        $deleted = 0;
        foreach ($matches as $directory) {
            $deleted += self::deleteTree($directory, 0);
            $remaining = \scandir($directory);
            if (\is_array($remaining) && \count($remaining) <= 2) {
                \rmdir($directory);
            }
        }

        return $deleted;
    }

    /**
     * Acquire an exclusive generation lock for one variant.
     *
     * The first page view of an image-heavy gallery fires every variant request at once, and
     * without a lock each duplicate request would run its own full GD render of the same image.
     * Holding a per-variant lock means one request renders while its duplicates wait and then
     * read the finished file, which bounds the cost of a cold cache to one render per variant
     * instead of one per concurrent viewer.
     *
     * @param string $relativePath Cache-relative variant path.
     * @return resource|null An acquired lock handle to pass to release(), or null if unavailable.
     */
    public static function lock(string $relativePath)
    {
        $absolute = self::absolutePath($relativePath);
        $directory = \dirname($absolute);

        if (!\is_dir($directory) && !\mkdir($directory, 0775, true) && !\is_dir($directory)) {
            return null;
        }

        $handle = \fopen($absolute . '.lock', 'c');
        if ($handle === false) {
            return null;
        }
        if (!\flock($handle, LOCK_EX)) {
            \fclose($handle);

            return null;
        }

        return $handle;
    }

    /**
     * Delete cached variants that have not been read recently.
     *
     * @param int $olderThanSeconds Age threshold in seconds.
     * @param string|null $siteId Tenant to prune, or null for every tenant.
     * @return int The number of files deleted locally.
     */
    public static function prune(int $olderThanSeconds, ?string $siteId = null): int
    {
        $root = Storage::getVariantsRoot();
        if ($siteId !== null) {
            if (\preg_match('/^[A-Za-z0-9\-]{1,64}$/', $siteId) !== 1) {
                throw new \InvalidArgumentException('Security exception: malformed site identifier.');
            }
            $root .= '/' . $siteId;
        }

        return self::deleteTree($root, \time() - \max(0, $olderThanSeconds));
    }

    /**
     * Release a generation lock acquired through lock(), and remove its lock file.
     *
     * Deleting the lock file keeps the cache directory to one file per variant rather than two,
     * and stops an empty .lock sibling from being publicly fetchable next to every rendition.
     * The delete is racy by nature -- another process may already have opened the same file and
     * will end up holding a lock on an unlinked inode -- but the consequence is only that two
     * requests may render the same variant concurrently, which is harmless: renders are
     * deterministic and published by atomic rename. The lock is a cost optimization, not a
     * correctness mechanism, so trading a rare duplicate render for a tidy cache is worthwhile.
     *
     * @param resource|null $handle The handle returned by lock().
     * @param string $relativePath The same path passed to lock(), whose lock file is removed.
     * @return void
     */
    public static function release($handle, string $relativePath = ''): void
    {
        if (!\is_resource($handle)) {
            return;
        }

        \flock($handle, LOCK_UN);
        \fclose($handle);

        if ($relativePath !== '') {
            $lockFile = self::absolutePath($relativePath) . '.lock';
            if (\is_file($lockFile)) {
                \unlink($lockFile);
            }
        }
    }

    /**
     * Persist a freshly rendered variant into the cache.
     *
     * @param string $relativePath Cache-relative variant path.
     * @param string $bytes The encoded variant.
     * @return void
     * @throws \Exception If the variant cannot be written to local disk.
     */
    public static function store(string $relativePath, string $bytes): void
    {
        self::writeLocal(self::absolutePath($relativePath), $bytes);

        if (!Storage::isLocalDriver()) {
            // The durable copy. writeRaw() rather than write(), because the bytes are already an
            // optimized derivative and the standard write path would decode and re-compress them.
            try {
                Storage::writeRaw('public/' . \ltrim($relativePath, '/'), $bytes);
            } catch (\Exception $exception) {
                // A failed durable write costs a re-render on another instance, nothing more,
                // so it must not fail the request that already has the bytes in hand.
                \error_log('Variant cache: remote store failed: ' . $exception->getMessage());
            }
        }
    }

    /**
     * Write bytes to an absolute local path atomically.
     *
     * The write lands in a temporary sibling file and is then renamed into place. rename() is
     * atomic within a filesystem, which is what guarantees the web server can never open a
     * partially written variant and hand a truncated image to a browser.
     *
     * @param string $absolutePath Destination path.
     * @param string $bytes Content to write.
     * @return void
     * @throws \Exception If the destination directory or file cannot be written.
     */
    protected static function writeLocal(string $absolutePath, string $bytes): void
    {
        $directory = \dirname($absolutePath);
        if (!\is_dir($directory) && !\mkdir($directory, 0775, true) && !\is_dir($directory)) {
            throw new \Exception("Variant cache: could not create the cache directory ({$directory}).");
        }

        $temporary = $absolutePath . '.' . \getmypid() . '.tmp';
        if (\file_put_contents($temporary, $bytes) === false) {
            throw new \Exception("Variant cache: could not write the variant ({$absolutePath}).");
        }

        if (!\rename($temporary, $absolutePath)) {
            \unlink($temporary);
            throw new \Exception("Variant cache: could not publish the variant ({$absolutePath}).");
        }

        \chmod($absolutePath, 0664);
    }
}
