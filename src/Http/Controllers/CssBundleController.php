<?php

declare(strict_types=1);

/**
 * File: src/Http/Controllers/CssBundleController.php
 * Architectural Purpose: HTTP request routing, request filtering middleware, or dynamic content-security controllers.
 * Package: Zero\Http\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Http\Controllers;

use Zero\Core\App;
use Zero\Interfaces\Controller;
use Zero\Support\StyleBundle;

/**
 * Class CssBundleController
 *
 * The cache-miss handler for a theme's compiled stylesheet: compiles the bundle on demand,
 * publishes it at the exact path its own URL describes, and streams it back.
 *
 * Reached only when the requested bundle is not already on disk. Bundle URLs are content-addressed
 * (see StyleBundle, which owns the source list, the cascade order and the fingerprint), so the
 * rewrite rule in public/.htaccess lets the web server satisfy them directly and falls through to
 * the front controller solely when the file is absent -- once per unique stylesheet per host.
 * Previously that rewrite was unconditional, so every single page view's stylesheet request booted
 * the framework, resolved the tenant and discovered every module purely to read a cached file back
 * off disk and echo it, costing roughly twenty times what letting the web server serve it costs.
 *
 * There is deliberately no development-mode branch any more. One used to force a recompile on every
 * request, gated on an APP_ENV value that nothing in the project ever set -- so it never fired, and
 * a stale bundle was served in every environment until someone deleted the file by hand. A
 * fingerprinted filename makes the distinction unnecessary: edited sources produce a different URL,
 * which is by definition a miss, which recompiles.
 */
class CssBundleController implements Controller
{
    /**
     * Matches the content-addressed bundle URL and every earlier shape of it.
     *
     * Current form is main-{theme}.{tenantScope}.{fingerprint}.css. A single hex segment (an
     * earlier fingerprint-only name) and a bare main-{theme}.css are both still accepted, so an
     * externally cached page, or a host project whose own layout has not been updated, keeps
     * receiving a working stylesheet rather than a 404.
     */
    public const ROUTE_PATTERN = '#^/assets/css/main-([a-zA-Z0-9_\-]+?)(?:\.([0-9a-f]{6,32}))?(?:\.([0-9a-f]{6,32}))?\.css$#';

    /**
     * Compile, publish, and serve a theme's stylesheet bundle.
     *
     * @param mixed $param The route's regex match array.
     * @return void
     * @throws \Exception If the bundle cannot be compiled or written to disk.
     */
    public function handle($param)
    {
        // 1. Resolve the target theme from the matched route, falling back to the active site's.
        $theme = $param[1] ?? '';
        if (empty($theme)) {
            $theme = App::getCurrentSite()->theme ?? 'default';
        }

        // With both hex segments present the URL is the current shape: scope then fingerprint.
        // With only one it is an earlier fingerprint-only name, which carries no tenant scope and
        // therefore cannot be confirmed as current.
        $requestedScope = (string)($param[2] ?? '');
        $requestedFingerprint = (string)($param[3] ?? '');
        if ($requestedFingerprint === '') {
            $requestedFingerprint = $requestedScope;
            $requestedScope = '';
        }

        // 2. The bundle may already exist: this instance's disk starts empty, but any deployment
        // whose rewrite rules are not in force -- PHP's built-in development server, for one --
        // routes even a warm request through here.
        $target = StyleBundle::path($theme);
        $bundle = \is_file($target) ? \file_get_contents($target) : false;

        if ($bundle === false) {
            $bundle = StyleBundle::publish($theme);
        }

        // 3. A URL carrying the current fingerprint may be cached forever, because its bytes can
        // never change. Anything else -- a legacy un-fingerprinted URL, or a stale one minted
        // before a stylesheet edit -- is still answered with the current bundle so the page renders
        // normally, but must not be pinned in a cache: that URL's content just changed under it.
        $isImmutable = ($requestedScope !== '' && $requestedScope === StyleBundle::siteScope()
            && $requestedFingerprint !== '' && $requestedFingerprint === StyleBundle::fingerprint($theme));

        \header('Content-Type: text/css; charset=UTF-8');
        \header($isImmutable
            ? 'Cache-Control: public, max-age=31536000, immutable'
            : 'Cache-Control: public, max-age=60');
        \header('Content-Length: ' . \strlen($bundle));

        echo $bundle;
        exit;
    }
}
