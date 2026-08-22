<?php
// src/Http/Tests/CssBundleOrderTest.php
// Guards the compiled stylesheet bundle's concatenation order, which is what decides whether a
// theme can restyle a core block.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Models\Site;
use Zero\Support\StyleBundle;
use Zero\Support\TestRequest;

echo "=== Theme CSS Bundle Tests ===\n";

/**
 * Compile a theme's bundle through the real request pipeline and return it.
 *
 * Requested by the legacy un-fingerprinted URL on purpose. The fingerprint is derived from the
 * active site's source set -- module stylesheets are included only for enabled modules -- so it can
 * only be computed inside the request that resolved that site, not out here. The legacy form is
 * answered with the current bundle either way, which is also what keeps an externally cached page
 * working, so this exercises that compatibility path at the same time.
 *
 * @param string $theme Theme name.
 * @return string The compiled, minified bundle.
 */
function compileThemeBundle(string $theme): string
{
    $response = TestRequest::get('/assets/css/main-' . $theme . '.css')
        ->onSite(['theme' => $theme])
        ->send();

    return $response['stdout'];
}

$bundle = compileThemeBundle('default');
assert_critical($bundle !== '', 'The bundler returned a compiled stylesheet');

// Markers, each unique to one source file and surviving minification (comments are stripped, so
// these are all real declarations).
$fontMarker = "font-family:'Inter'";                 // fonts.css
$heroMarker = '--hero-min-height';                   // blocks/hero.css   (core block base styles)
$galleryMarker = '.gallery-lightbox-content';        // blocks/gallery.css
$themeMarker = '--accent-hover';                     // themes/default/default.css

$fontAt = strpos($bundle, $fontMarker);
$heroAt = strpos($bundle, $heroMarker);
$galleryAt = strpos($bundle, $galleryMarker);
$themeAt = strpos($bundle, $themeMarker);

echo "  Testing that every layer is present in the bundle...\n";
assert_test($fontAt !== false, 'Font declarations are bundled');
assert_critical($heroAt !== false, 'Core block styles are bundled (blocks/hero.css)');
assert_test($galleryAt !== false, 'Core block styles are bundled (blocks/gallery.css)');
assert_critical($themeAt !== false, 'The active theme stylesheet is bundled');

echo "  Testing the cascade order...\n";
assert_test($fontAt < $heroAt, 'Fonts load before block styles');

// The load-bearing assertion. Block rules and theme rules share the same selector specificity,
// so source order is the only tie-breaker: a theme restating .block-hero can only win if its
// stylesheet is concatenated after the block's. Reversing these two silently makes every theme's
// block customization inert -- present in the bundle, but overridden.
assert_test(
    $themeAt > $heroAt,
    "The theme stylesheet must come AFTER core block styles so it can override them (theme at {$themeAt}, blocks/hero.css at {$heroAt})"
);
assert_test(
    $themeAt > $galleryAt,
    "The theme stylesheet must come AFTER every core block stylesheet (theme at {$themeAt}, blocks/gallery.css at {$galleryAt})"
);

echo "  Testing that a theme override actually lands last...\n";
// blocks/hero.css aligns hero content to the bottom of the block. Whatever the default theme has
// to say about .block-hero, if anything, must appear later in the bundle than that base rule.
$baseAlignAt = strpos($bundle, 'align-items:flex-end');
assert_test($baseAlignAt !== false, 'The base hero alignment rule is present');
assert_test(
    $baseAlignAt < $themeAt,
    'A theme rule restating a base block declaration is resolved in the theme\'s favour'
);

// --- Every shipped theme, not just the default one -----------------------------------------
// A dead @import or a mis-ordered theme is a per-theme defect, so each theme's own bundle is
// checked. Discovered from disk rather than hardcoded, so a theme added later is covered too.
echo "  Testing every shipped theme's bundle...\n";
$themeDirs = glob(APPLICATION_ROOT . '/public/assets/css/themes/*', GLOB_ONLYDIR) ?: [];
assert_test(count($themeDirs) > 0, 'At least one theme was discovered to check');

foreach ($themeDirs as $themeDir) {
    $themeName = basename($themeDir);
    if (!file_exists($themeDir . '/' . $themeName . '.css')) {
        continue;
    }

    $themeBundle = compileThemeBundle($themeName);
    assert_test($themeBundle !== '', "Theme '{$themeName}' compiles to a non-empty bundle");

    // CSS requires @import to precede all other rules. Every bundle opens with @font-face
    // declarations, so an @import concatenated in from a source file is invalid and silently
    // dropped -- meaning a stylesheet or webfont that looks configured but never loads, which is
    // exactly how a theme ends up silently rendering in a fallback font.
    assert_test(
        strpos($themeBundle, '@import') === false,
        "Theme '{$themeName}' has no @import in its bundle, which would be silently ignored this late in a stylesheet"
    );

    // The theme's own rules must still be the last word in its own bundle.
    $themeBlockAt = strpos($themeBundle, '--hero-min-height');
    $themeOwnAt = strrpos($themeBundle, '.block-hero');
    if ($themeBlockAt !== false && $themeOwnAt !== false) {
        assert_test(
            $themeOwnAt >= $themeBlockAt,
            "Theme '{$themeName}' keeps base block styles ahead of its own rules"
        );
    }
}

echo "  Testing that a theme's webfonts are self-hosted...\n";
// A remote font URL in a bundle is either a privacy leak or, if it arrived via @import, a font
// that never loads at all. Neither is acceptable, so both are refused here.
foreach ($themeDirs as $themeDir) {
    $themeName = basename($themeDir);
    if (!file_exists($themeDir . '/' . $themeName . '.css')) {
        continue;
    }

    $themeCss = (string)file_get_contents($themeDir . '/' . $themeName . '.css');
    assert_test(
        strpos($themeCss, 'fonts.googleapis.com') === false && strpos($themeCss, 'fonts.gstatic.com') === false,
        "Theme '{$themeName}' references no third-party font CDN; webfonts are served from assets/fonts/"
    );
}

// --- Content-addressed naming -------------------------------------------------------------
// The fingerprint is what makes the bundle impossible to serve stale and legitimate to cache
// immutably, so its structural properties are pinned here.
echo "  Testing content-addressed bundle naming...\n";

// Bootstrapping is what runs each module's init(), which is where a module registers its own
// frontend stylesheet -- without it, App::getRegisteredModuleStylesheets() is empty and the
// module-scoping assertions further down would pass vacuously.
App::bootstrap();

// A site has to be active for the source set to include the block stylesheets at all.
App::setCurrentSite(new Site([
    'id' => '019fa1f1-cccc-72f0-8c3b-9732ab7f9e3b',
    'name' => 'Bundle Fingerprint Site',
    'domain' => 'bundle-test.local',
    'theme' => 'default',
    'enabled_modules' => '[]'
]));

$fingerprint = StyleBundle::fingerprint('default');
$filename = StyleBundle::filename('default');
$url = StyleBundle::url('default');

assert_test(
    preg_match('/^[0-9a-f]{' . StyleBundle::FINGERPRINT_LENGTH . '}$/', $fingerprint) === 1,
    "Fingerprint is a fixed-length hex digest, got '{$fingerprint}'"
);
assert_test($fingerprint === StyleBundle::fingerprint('default'), 'Fingerprint is stable across calls');

assert_test($filename === "main-default.{$scope}.{$fingerprint}.css", "Filename embeds the tenant scope and the fingerprint: {$filename}");
assert_test($url === "/assets/css/cache/{$filename}", "URL is the published path: {$url}");
assert_test(
    strpos(StyleBundle::path('default'), '/assets/css/cache/') !== false,
    'Compiled bundles are published to a dedicated cache directory, not the stylesheet source tree'
);
assert_test(
    !file_exists(APPLICATION_ROOT . '/public/assets/css/' . $filename),
    'Nothing generated is written alongside the authored stylesheets'
);
assert_test(strpos($url, '?') === false, 'URL carries no hand-maintained query-string cache buster');

$sources = StyleBundle::sourceFiles('default');
assert_test(count($sources) > 2, 'The source set spans fonts, block styles and the theme');
assert_test(basename($sources[0]) === 'fonts.css', 'Fonts are the first source file');
assert_test(
    $sources[count($sources) - 1] === APPLICATION_ROOT . '/public/assets/css/themes/default/default.css',
    'The active theme is the last source file, so it wins the cascade'
);
assert_test($sources === array_values(array_filter($sources, 'is_file')), 'Every source file in the set exists');

// --- The fingerprint follows content, not modification time -------------------------------
// A git checkout or docker build restamps every file's mtime, so an mtime-derived fingerprint
// would invalidate the bundle on every deployment even when no stylesheet changed -- and a
// rollback would not recover the previously cached bundle either. Exercised through a scratch
// stylesheet registered as a module contribution, so no shared source file is edited.
echo "  Testing that the fingerprint follows content rather than mtime...\n";
$scratchToken = bin2hex(random_bytes(5));
$scratchStylesheet = APPLICATION_ROOT . '/public/assets/css/zz-fp-' . $scratchToken . '.css';
file_put_contents($scratchStylesheet, ".zz-fp-probe { color: red; }\n");

App::registerModuleStylesheet('site-search', $scratchStylesheet);
App::setCurrentSite(new Site([
    'id' => '019fa1f1-eeee-72f0-8c3b-9732ab7f9e3b',
    'name' => 'Fingerprint Basis Site',
    'domain' => 'fp-basis.local',
    'theme' => 'default',
    'enabled_modules' => '["site-search"]'
]));
StyleBundle::clearFingerprintCache();

$baseline = StyleBundle::fingerprint('default');
assert_test(
    in_array($scratchStylesheet, StyleBundle::sourceFiles('default'), true),
    'The scratch stylesheet is part of the source set, so it can influence the fingerprint'
);

touch($scratchStylesheet, time() + 500);
StyleBundle::clearFingerprintCache();
assert_test(
    StyleBundle::fingerprint('default') === $baseline,
    'Touching a source file without changing it leaves the fingerprint alone, so a deploy does not discard valid caches'
);

file_put_contents($scratchStylesheet, ".zz-fp-probe { color: blue; }\n");
StyleBundle::clearFingerprintCache();
$afterEdit = StyleBundle::fingerprint('default');
assert_test($afterEdit !== $baseline, "Editing a source file changes the fingerprint ({$baseline} -> {$afterEdit})");

file_put_contents($scratchStylesheet, ".zz-fp-probe { color: red; }\n");
StyleBundle::clearFingerprintCache();
assert_test(
    StyleBundle::fingerprint('default') === $baseline,
    'Reverting the edit restores the original fingerprint, so a rollback reuses the cached bundle'
);

unlink($scratchStylesheet);
StyleBundle::clearFingerprintCache();
$scope = StyleBundle::siteScope();
assert_test(
    preg_match('/^[0-9a-f]{' . StyleBundle::SCOPE_LENGTH . '}$/', $scope) === 1,
    "Tenant scope is a fixed-length hex digest, got '{$scope}'"
);
assert_test(strpos($scope, '019fa1f1') !== 0, 'The tenant scope is a digest, not the site id itself');

// --- Pruning is tenant-aware ---------------------------------------------------------------
// Content-addressed names publish a new file rather than replacing one, so without pruning every
// stylesheet edit and every deployment would leave another orphan behind. But "not the current
// name" does not mean "unused": another tenant's bundle may be live right now, so only this
// site's own superseded bundles go immediately, and other tenants' are reclaimed on age.
//
// Run against a throwaway theme name, because main-default.*.css is shared global state that
// other suites publish to concurrently in other worker slots.
echo "  Testing tenant-aware pruning...\n";
$cssDir = APPLICATION_ROOT . '/public/' . StyleBundle::OUTPUT_DIRECTORY;
$scratchTheme = 'zz-prune-fixture';
$ownScope = StyleBundle::siteScope();

$ownSuperseded = $cssDir . '/main-' . $scratchTheme . '.' . $ownScope . '.000000000000.css';
$otherRecent = $cssDir . '/main-' . $scratchTheme . '.99999999.111111111111.css';
$otherStale = $cssDir . '/main-' . $scratchTheme . '.88888888.222222222222.css';
$current = StyleBundle::path($scratchTheme);

file_put_contents($ownSuperseded, '/* this site, previous source set */');
file_put_contents($otherRecent, '/* another tenant, published moments ago */');
file_put_contents($otherStale, '/* another tenant, long abandoned */');
file_put_contents($current, '/* current */');
touch($ownSuperseded, time() - 30);    // deliberately recent: own scope should go regardless
touch($otherRecent, time() - 60);
touch($otherStale, time() - 172800);   // two days

$pruned = StyleBundle::prune($scratchTheme);

assert_test($pruned === 2, "Pruning removed this site's superseded bundle and the abandoned one, got {$pruned}");
assert_test(!file_exists($ownSuperseded), "This site's own superseded bundle goes immediately, even though it is recent");
assert_test(!file_exists($otherStale), 'Another tenant\'s long-abandoned bundle is reclaimed');
assert_test(file_exists($otherRecent), 'Another tenant\'s recent bundle is kept, so tenants do not evict each other');
assert_test(file_exists($current), 'The current bundle is never pruned');

// A zero grace period is the deployment-cleanup case: everything but the current name goes.
$prunedAll = StyleBundle::prune($scratchTheme, 0);
assert_test($prunedAll === 1, "A zero grace period reclaims the remaining tenant's bundle too, got {$prunedAll}");
assert_test(file_exists($current), 'Even with no grace period the current bundle survives');

foreach ([$ownSuperseded, $otherRecent, $otherStale, $current] as $fixture) {
    if (file_exists($fixture)) {
        unlink($fixture);
    }
}

// --- Bundles are scoped per tenant ---------------------------------------------------------
// Two sites are already capable of compiling different bytes from one theme, since the source set
// follows each site's enabled modules. Sharing a filename would have them serving each other's
// stylesheet out of cache.
echo "  Testing per-tenant bundle scoping...\n";

/**
 * Resolve a theme's bundle filename as it would be built for a given tenant.
 *
 * @param string $siteId Tenant id.
 * @param string $enabledModules JSON array of enabled module ids.
 * @return string The bundle filename.
 */
function filenameForTenant(string $siteId, string $enabledModules): string
{
    App::setCurrentSite(new Site([
        'id' => $siteId,
        'name' => 'Scoping Site',
        'domain' => 'scope.local',
        'theme' => 'default',
        'enabled_modules' => $enabledModules
    ]));
    StyleBundle::clearFingerprintCache();

    return StyleBundle::filename('default');
}

$tenantA = filenameForTenant('019fa1f1-aaaa-72f0-8c3b-9732ab7f9e3b', '["site-search"]');
$tenantB = filenameForTenant('019fa1f1-bbbb-72f0-8c3b-9732ab7f9e3b', '["site-search"]');

assert_test($tenantA !== $tenantB, "Two tenants never share a bundle filename ({$tenantA} vs {$tenantB})");

// Identical source sets, so the fingerprints match and only the scope differs -- which is exactly
// the case a shared name would have collapsed into one file.
$partsA = explode('.', $tenantA);
$partsB = explode('.', $tenantB);
assert_test($partsA[1] !== $partsB[1], 'The tenant scope segment differs between the two sites');
assert_test($partsA[2] === $partsB[2], 'With identical source sets the fingerprint segment is the same');

// --- Module stylesheets are conditional, and part of the base layer ------------------------
// A module's own frontend styles are folded into the bundle only for sites that enable it, and
// sit ahead of the theme so the theme can still restyle them.
echo "  Testing conditional inclusion of module stylesheets...\n";

/**
 * Resolve a theme's source set as it would be built for a given set of enabled modules.
 *
 * @param string $enabledModules JSON array of enabled module ids.
 * @return array<int, string> Source file basenames, in load order.
 */
function sourcesForModules(string $enabledModules): array
{
    App::setCurrentSite(new Site([
        'id' => '019fa1f1-dddd-72f0-8c3b-9732ab7f9e3b',
        'name' => 'Module Scoping Site',
        'domain' => 'module-scope.local',
        'theme' => 'default',
        'enabled_modules' => $enabledModules
    ]));
    StyleBundle::clearFingerprintCache();

    return array_map('basename', StyleBundle::sourceFiles('default'));
}

$withSearch = sourcesForModules('["site-search"]');
$fingerprintWithSearch = StyleBundle::fingerprint('default');

$withoutSearch = sourcesForModules('["security"]');
$fingerprintWithoutSearch = StyleBundle::fingerprint('default');

assert_test(in_array('search.css', $withSearch, true), "A site with site-search enabled bundles search.css");
assert_test(!in_array('search.css', $withoutSearch, true), "A site without site-search does not bundle search.css");
assert_test(
    $fingerprintWithSearch !== $fingerprintWithoutSearch,
    'Enabling a module changes the bundle fingerprint, so the two sites cannot share a cached stylesheet'
);

// The module's styles must remain theme-overridable, which means landing before the theme.
$searchIndex = array_search('search.css', $withSearch, true);
$themeIndex = array_search('default.css', $withSearch, true);
assert_test($searchIndex !== false && $themeIndex !== false, 'Both the module stylesheet and the theme are in the source set');
assert_test(
    $searchIndex < $themeIndex,
    "A module stylesheet loads before the theme so the theme can restyle it (module at {$searchIndex}, theme at {$themeIndex})"
);

StyleBundle::clearFingerprintCache();

echo "Theme CSS bundle tests completed successfully.\n\n";
