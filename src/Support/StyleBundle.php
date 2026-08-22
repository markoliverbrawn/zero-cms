<?php

declare(strict_types=1);

/**
 * File: src/Support/StyleBundle.php
 * Architectural Purpose: Global diagnostic tools, cryptographic security handlers, SMTP email transmitters, and text helpers.
 * Package: Zero\Support
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support;

use Zero\Core\App;

/**
 * Class StyleBundle
 *
 * Owns the compiled theme stylesheet: which source files make it up, in what order, what its
 * content-addressed URL is, and how it is minified and published to disk.
 *
 * The bundle's filename embeds a fingerprint of its own inputs -- every source file's path,
 * modification time and size. That single decision resolves what was previously a three-way bind:
 *
 *  - **Staleness.** The compiler used to write a fixed `main-{theme}.css` and serve it forever
 *    once it existed, with no way to notice its inputs had changed. Editing theme CSS did nothing
 *    until someone deleted the file by hand, and a deployment could serve the previous release's
 *    stylesheet indefinitely. A fingerprinted name cannot go stale: different inputs are a
 *    different URL.
 *  - **Cacheability.** Because a given URL's bytes can now never change, the far-future
 *    `immutable` response header is truthful rather than optimistic, and the hand-maintained
 *    `?v=1.0` query string it replaced becomes unnecessary.
 *  - **Ephemeral hosts.** A container that starts with an empty disk simply compiles once. That is
 *    cheap -- concatenating and minifying the whole set measures well under a millisecond -- and
 *    the inputs ship inside the image, so nothing needs to be fetched or persisted remotely. What
 *    made recompilation feel expensive was never the work; it was that every request for the
 *    stylesheet booted the framework to hand back a file the web server could have served itself.
 *
 * The name is also scoped to the requesting tenant. Two sites are already capable of compiling
 * different bytes from the same theme -- the source set includes the stylesheets of whichever
 * modules each site enables -- so a shared name would have them serving each other's stylesheet
 * from cache, and would leak one tenant's styling into another the moment anything else about a
 * bundle becomes site-specific. The scope is a short digest of the site id rather than the id
 * itself, to keep filenames short and to avoid publishing tenant identifiers into asset URLs.
 *
 * Kept out of the controller so the render path can ask for the URL without touching HTTP
 * plumbing, and so the definition of the bundle's inputs lives in exactly one place. That last
 * point is load-bearing: if the URL builder and the compiler disagreed about which files belong
 * in the bundle, the fingerprint in the name would not describe the bytes at that name.
 */
class StyleBundle
{
    /** Length of the hex fingerprint embedded in a bundle filename. */
    public const FINGERPRINT_LENGTH = 12;

    /** Length of the hex tenant scope embedded in a bundle filename. */
    public const SCOPE_LENGTH = 8;

    /** Web-root-relative directory the compiled bundles are published to. */
    public const OUTPUT_DIRECTORY = 'assets/css';

    /**
     * Core block stylesheets, available to every theme regardless of which modules are enabled.
     */
    protected const CORE_BLOCK_STYLESHEETS = [
        '/public/assets/css/blocks/hero.css',
        '/public/assets/css/blocks/text.css',
        '/public/assets/css/blocks/text_image.css',
        '/public/assets/css/blocks/gallery.css',
        '/public/assets/css/blocks/masonry.css',
        '/public/assets/css/blocks/testimonials.css',
        '/public/assets/css/blocks/code.css',
        '/public/assets/css/blocks/chart.css',
        '/public/assets/css/blocks/grid.css',
        '/public/assets/css/blocks/sub_pages.css',
    ];

    /** @var array<string, string> Memoized fingerprints, keyed by theme name and tenant scope. */
    protected static $fingerprints = [];

    /**
     * Forget every memoized fingerprint.
     *
     * The fingerprint depends on which modules are enabled for the active site, so any process
     * that changes tenant mid-run -- the test suite, or a CLI task walking several sites -- must
     * clear it rather than carry another site's answer across.
     *
     * @return void
     */
    public static function clearFingerprintCache(): void
    {
        self::$fingerprints = [];
    }

    /**
     * Concatenate and minify a theme's source stylesheets into one bundle.
     *
     * @param string $theme Theme name.
     * @return string The minified bundle.
     * @throws \Exception If a source stylesheet exists but cannot be read.
     */
    public static function compile(string $theme): string
    {
        $combined = '';

        foreach (self::sourceFiles($theme) as $sourceFile) {
            $content = \file_get_contents($sourceFile);
            if ($content === false) {
                throw new \Exception("Failed to read source stylesheet file from disk path: {$sourceFile}");
            }
            $combined .= $content . "\n\n";
        }

        $header = '/* --- Compiled & Minified Theme Asset Bundle: ' . \gmdate('Y-m-d H:i:s')
            . " UTC [Theme: {$theme}] --- */\n";

        return $header . self::minify($combined);
    }

    /**
     * Build the content-addressed filename for a theme's bundle.
     *
     * @param string $theme Theme name.
     * @return string e.g. "main-default.3f8a1c04.9f2c1ab40e7d.css"
     */
    public static function filename(string $theme): string
    {
        return 'main-' . $theme . '.' . self::siteScope() . '.' . self::fingerprint($theme) . '.css';
    }

    /**
     * Fingerprint a theme's bundle inputs.
     *
     * Hashes each source file's path, modification time and size rather than its contents: it
     * gives the same "did anything change" answer for a fraction of the work, since a stat is
     * cheaper than a read, and the read would otherwise happen on every page render just to
     * decide a URL. Editing any source file, adding one, or enabling a module that contributes
     * one all move the fingerprint.
     *
     * @param string $theme Theme name.
     * @return string A short hex digest.
     */
    public static function fingerprint(string $theme): string
    {
        $memoKey = $theme . '|' . self::siteScope();
        if (isset(self::$fingerprints[$memoKey])) {
            return self::$fingerprints[$memoKey];
        }

        $material = '';
        foreach (self::sourceFiles($theme) as $sourceFile) {
            $modified = \filemtime($sourceFile);
            $size = \filesize($sourceFile);
            $material .= $sourceFile . ':' . ($modified === false ? '0' : $modified)
                . ':' . ($size === false ? '0' : $size) . '|';
        }

        self::$fingerprints[$memoKey] = \substr(\hash('xxh128', $material), 0, self::FINGERPRINT_LENGTH);

        return self::$fingerprints[$memoKey];
    }

    /**
     * Clean and minify raw CSS natively, with no library dependency.
     *
     * @param string $css The raw combined CSS content.
     * @return string The minified CSS content.
     */
    public static function minify(string $css): string
    {
        // 1. Strip all CSS comments
        $css = \preg_replace('!/\*[^*]*\*+([^/*][^*]*\*+)*/!', '', $css);

        // 2. Strip tabs, carriage returns, and newlines
        $css = \str_replace(["\r\n", "\r", "\n", "\t"], '', $css);

        // 3. Strip redundant multiple spaces
        $css = \preg_replace('/\s+/', ' ', $css);

        // 4. Strip spaces around structural delimiters and braces
        $css = \preg_replace('/\s*([{}|:;,])\s*/', '$1', $css);

        // 5. Remove trailing semi-colons inside braces
        $css = \str_replace(';}', '}', $css);

        return \trim($css);
    }

    /**
     * Absolute local path a theme's compiled bundle is published to.
     *
     * @param string $theme Theme name.
     * @return string
     */
    public static function path(string $theme): string
    {
        return APPLICATION_ROOT . '/public/' . self::OUTPUT_DIRECTORY . '/' . self::filename($theme);
    }

    /**
     * Delete stale compiled bundles for a theme, leaving the current one and any recent sibling.
     *
     * Content-addressed names mean a changed stylesheet publishes a new file rather than replacing
     * one, so without this every edit and every deployment would leave another orphan behind.
     *
     * Pruning is tenant-aware, which is the whole reason the name carries a tenant scope:
     *
     *  - Another fingerprint **within this site's scope** is unambiguously superseded, since this
     *    site just published the current one. It goes immediately.
     *  - A bundle belonging to **another tenant's scope** may well be live, so it is only reclaimed
     *    once it has gone untouched for the grace period. Without that distinction each tenant
     *    would evict the others' bundles on every publish and all of them would recompile forever.
     *
     * @param string $theme Theme name.
     * @param int $graceSeconds How long another tenant's bundle must be untouched before removal.
     * @return int Number of bundles removed.
     */
    public static function prune(string $theme, int $graceSeconds = 86400): int
    {
        $current = self::path($theme);
        $ownPrefix = APPLICATION_ROOT . '/public/' . self::OUTPUT_DIRECTORY
            . '/main-' . $theme . '.' . self::siteScope() . '.';
        $pattern = APPLICATION_ROOT . '/public/' . self::OUTPUT_DIRECTORY . '/main-' . $theme . '.*.css';
        $cutoff = \time() - \max(0, $graceSeconds);

        $matches = \glob($pattern);
        if (!\is_array($matches)) {
            return 0;
        }

        $removed = 0;
        foreach ($matches as $candidate) {
            if ($candidate === $current || !\is_file($candidate)) {
                continue;
            }

            if (\strpos($candidate, $ownPrefix) !== 0) {
                // Another tenant's bundle: reclaim only once it looks abandoned.
                $modified = \filemtime($candidate);
                if ($modified !== false && $modified >= $cutoff) {
                    continue;
                }
            }

            if (\unlink($candidate)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Compile a theme's bundle, publish it to disk, and return its content.
     *
     * The write lands in a temporary sibling and is renamed into place, because the web server may
     * serve this path directly: a rename is atomic within a filesystem, so a concurrent request
     * can never open a half-written stylesheet.
     *
     * @param string $theme Theme name.
     * @return string The compiled bundle.
     * @throws \Exception If the bundle cannot be compiled or written.
     */
    public static function publish(string $theme): string
    {
        $compiled = self::compile($theme);
        $target = self::path($theme);
        $directory = \dirname($target);

        if (!\is_dir($directory) && !\mkdir($directory, 0775, true) && !\is_dir($directory)) {
            throw new \Exception("Failed to create the compiled stylesheet directory: {$directory}");
        }

        $temporary = $target . '.' . \getmypid() . '.tmp';
        if (\file_put_contents($temporary, $compiled) === false) {
            throw new \Exception("Failed to write compiled CSS bundle to disk path: {$target}");
        }
        if (!\rename($temporary, $target)) {
            \unlink($temporary);
            throw new \Exception("Failed to publish compiled CSS bundle to disk path: {$target}");
        }

        self::prune($theme);

        return $compiled;
    }

    /**
     * Short digest identifying the requesting tenant, embedded in the bundle filename.
     *
     * Hashed rather than used verbatim so an asset URL does not publish a tenant identifier and
     * filenames stay short. Falls back to a fixed literal when no site is resolved -- a CLI
     * process, for instance -- so the name is always well formed.
     *
     * @return string
     */
    public static function siteScope(): string
    {
        $siteId = (string)App::getCurrentSiteId();
        if ($siteId === '') {
            return \str_pad('0', self::SCOPE_LENGTH, '0');
        }

        return \substr(\hash('xxh128', 'zero-style-bundle|' . $siteId), 0, self::SCOPE_LENGTH);
    }

    /**
     * Resolve the ordered list of source stylesheets making up a theme's bundle.
     *
     * The order IS the cascade, because this is a plain concatenation and block rules share the
     * same selector specificity as the theme rules that restyle them: fonts, then core block base
     * styles, then the enabled modules' block styles, then the active theme last so it has the
     * final say. Reversing the last two makes every theme's block customization silently inert.
     *
     * @param string $theme Theme name.
     * @return array<int, string> Absolute paths, in load order, existing files only.
     */
    public static function sourceFiles(string $theme): array
    {
        $candidates = [APPLICATION_ROOT . '/public/assets/css/fonts.css'];

        $site = App::getCurrentSite();
        if ($site) {
            foreach (self::CORE_BLOCK_STYLESHEETS as $blockStylesheet) {
                $candidates[] = APPLICATION_ROOT . $blockStylesheet;
            }

            // Module-contributed block styles, registered via App::registerModuleStylesheet() and
            // only included when that module is actually enabled for the requesting site. Still
            // base styles, so they stay ahead of the theme and remain theme-overridable.
            foreach (App::getRegisteredModuleStylesheets() as $moduleStylesheet) {
                if ($site->isModuleEnabled($moduleStylesheet['module'])) {
                    $candidates[] = $moduleStylesheet['path'];
                }
            }
        }

        // The active theme, last. Resolved through the registry so a host project can register a
        // theme's stylesheet from outside this repo.
        $themeStylesheet = App::resolveThemeStylesheetFile($theme);
        if ($themeStylesheet !== null) {
            $candidates[] = $themeStylesheet;
        }

        return \array_values(\array_filter($candidates, 'is_file'));
    }

    /**
     * Public URL of a theme's compiled bundle, for a stylesheet link.
     *
     * @param string $theme Theme name.
     * @return string
     */
    public static function url(string $theme): string
    {
        return '/' . self::OUTPUT_DIRECTORY . '/' . self::filename($theme);
    }
}
