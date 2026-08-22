<?php

declare(strict_types=1);

/**
 * File: src/Support/AssetVersion.php
 * Architectural Purpose: Global diagnostic tools, cryptographic security handlers, SMTP email transmitters, and text helpers.
 * Package: Zero\Support
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support;

/**
 * Class AssetVersion
 *
 * Rewrites a static asset's URL to carry a digest of its own contents, so the file can be cached
 * for a year and still be replaceable on deploy.
 *
 *   /assets/js/blocks/gallery.js  ->  /assets/js/blocks/gallery.a1b2c3d4.js
 *
 * Nothing is generated, copied or minified: the bytes served are the authored file, and a rewrite
 * rule in public/.htaccess strips the digest back off before the web server resolves the path. No
 * cache directory, no writable path, no PHP in the request. This is the whole difference from
 * StyleBundle, which genuinely has to compile something.
 *
 * The problem it solves is that public/.htaccess serves every .js and .css with
 * `Cache-Control: immutable, max-age=31536000`. `immutable` tells a browser not merely to cache
 * the file but never to ask about it again -- so with a filename that never changes, a deployed
 * fix simply cannot reach anyone who has already visited, for up to a year. The promise was false;
 * the file did change. Putting the digest in the URL makes it true, because a changed file is
 * requested under a name no browser has seen.
 *
 * It also retires the two hand-rolled workarounds that had grown up around the same problem: a
 * manual `?v=1.3` that someone has to remember to bump, and a `?v=<?= time() ?>` that busts the
 * cache on literally every page load and so throws the caching away entirely.
 *
 * Digests are over file contents rather than modification time. Mtime would be the cheaper stat,
 * but a git checkout or a docker build stamps every file with the build time, so an mtime-based
 * digest would change for every asset on every deploy and discard caches that were still perfectly
 * valid. Content is the honest signal, and the reads are memoized per process.
 */
class AssetVersion
{
    /** Length of the hex digest embedded in a versioned asset filename. */
    public const DIGEST_LENGTH = 8;

    /** @var array<string, string> Memoized versioned URLs, keyed by the original web path. */
    protected static $urls = [];

    /**
     * Forget every memoized URL.
     *
     * For the test suite, and for any long-running process that edits assets while running.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$urls = [];
    }

    /**
     * Compute the digest of an asset's contents.
     *
     * @param string $webPath Web-root-relative path, e.g. "/assets/js/blocks/gallery.js".
     * @return string|null A short hex digest, or null when the file cannot be read.
     */
    public static function digest(string $webPath): ?string
    {
        $absolute = self::resolve($webPath);
        if ($absolute === null) {
            return null;
        }

        $contents = \file_get_contents($absolute);
        if ($contents === false) {
            return null;
        }

        return \substr(\hash('xxh128', $contents), 0, self::DIGEST_LENGTH);
    }

    /**
     * Resolve a web path to an absolute file path inside the public directory.
     *
     * @param string $webPath Web-root-relative path.
     * @return string|null The absolute path, or null if it escapes public/ or does not exist.
     */
    protected static function resolve(string $webPath): ?string
    {
        $relative = \ltrim(\parse_url($webPath, PHP_URL_PATH) ?? '', '/');
        if ($relative === '' || \strpos($relative, '..') !== false || \strpos($relative, "\0") !== false) {
            return null;
        }

        $absolute = APPLICATION_ROOT . '/public/' . $relative;

        return \is_file($absolute) ? $absolute : null;
    }

    /**
     * Rewrite an asset URL to include a digest of its contents.
     *
     * Anything that cannot be digested -- a path outside public/, a file that is not there, an
     * absolute URL to another host -- is returned exactly as given, so this is safe to wrap around
     * every asset reference unconditionally.
     *
     * @param string $webPath Web-root-relative path, e.g. "/assets/js/blocks/gallery.js".
     * @return string The versioned URL, or the original path when it cannot be versioned.
     */
    public static function url(string $webPath): string
    {
        if (isset(self::$urls[$webPath])) {
            return self::$urls[$webPath];
        }

        $digest = self::digest($webPath);
        if ($digest === null) {
            return $webPath;
        }

        $extension = \pathinfo($webPath, PATHINFO_EXTENSION);
        if ($extension === '') {
            return $webPath;
        }

        // Insert the digest ahead of the extension, which is the form public/.htaccess knows how
        // to strip back off: "…/gallery.js" becomes "…/gallery.a1b2c3d4.js".
        $stem = \substr($webPath, 0, -(\strlen($extension) + 1));
        self::$urls[$webPath] = $stem . '.' . $digest . '.' . $extension;

        return self::$urls[$webPath];
    }
}
