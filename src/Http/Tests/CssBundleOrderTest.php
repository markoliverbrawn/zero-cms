<?php
// src/Http/Tests/CssBundleOrderTest.php
// Guards the compiled stylesheet bundle's concatenation order, which is what decides whether a
// theme can restyle a core block.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Support\TestRequest;

echo "=== Theme CSS Bundle Cascade Order Tests ===\n";

/**
 * Compile a theme's bundle through the real request pipeline and return it.
 *
 * The bundler serves a previously compiled file verbatim when one exists, so the cache is cleared
 * first to force a genuine recompile from the current source ordering. It regenerates on the
 * request, so nothing is left missing afterwards.
 *
 * @param string $theme Theme name.
 * @return string The compiled, minified bundle.
 */
function compileThemeBundle(string $theme): string
{
    $bundlePath = APPLICATION_ROOT . '/public/assets/css/main-' . $theme . '.css';
    if (file_exists($bundlePath)) {
        unlink($bundlePath);
    }

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
assert_test(count($themeDirs) > 1, 'More than one theme was discovered to check');

foreach ($themeDirs as $themeDir) {
    $themeName = basename($themeDir);
    if (!file_exists($themeDir . '/' . $themeName . '.css')) {
        continue;
    }

    $themeBundle = compileThemeBundle($themeName);
    assert_test($themeBundle !== '', "Theme '{$themeName}' compiles to a non-empty bundle");

    // CSS requires @import to precede all other rules. Every bundle opens with @font-face
    // declarations, so an @import concatenated in from a source file is invalid and silently
    // dropped -- meaning a stylesheet or webfont that looks configured but never loads. This is
    // the defect that left the kitchensink theme rendering in a fallback font.
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

echo "Theme CSS bundle cascade order tests completed successfully.\n\n";
