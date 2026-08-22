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
use Zero\Core\Env;
use Zero\Interfaces\Controller;

/**
 * Class CssBundleController
 *
 * Compiles a theme's stylesheets into one minified bundle cached at
 * public/assets/css/main-{theme}.css and serves it as text/css, resolving the theme from the
 * matched route or the active site.
 *
 * Concatenation order defines the cascade: fonts, then core block base styles, then the enabled
 * modules' own block styles, then the active theme. Because block and theme rules share the same
 * selector specificity, that ordering is the only thing that lets a theme restyle a core block.
 *
 * A consequence worth knowing when authoring a theme: an @import inside any of these files is
 * dead. The bundle concatenates raw file contents, and CSS requires @import to precede all other
 * rules, so an import landing after the font declarations is invalid and dropped by the browser.
 * Register additional stylesheets through the bundler instead.
 */
class CssBundleController implements Controller
{
    /**
     * Concatenate and dynamically compile the theme-specific CSS stylesheets into a cached, minified main-*.css file.
     *
     * @param mixed $param
     * @throws \Exception If files cannot be read or written.
     */
    public function handle($param)
    {
        // 1. Resolve target theme name from regex match or site fallback
        $theme = $param[1] ?? '';
        if (empty($theme)) {
            $theme = App::getCurrentSite()->theme ?? 'default';
        }

        $targetFile = APPLICATION_ROOT . "/public/assets/css/main-{$theme}.css";
        $isDevelopment = Env::get('APP_ENV', 'production') === 'development';

        // 2. If we are in production and the compiled file already exists on disk, serve it immediately.
        // We only re-compile if the file is missing, or if we are actively in development mode.
        if (!$isDevelopment && \file_exists($targetFile)) {
            $cachedContent = \file_get_contents($targetFile);
            if ($cachedContent === false) {
                throw new \Exception("Failed to read cached CSS bundle from disk path: {$targetFile}");
            }
            \header('Content-Type: text/css; charset=UTF-8');
            \header('Cache-Control: public, max-age=31536000, immutable');
            echo $cachedContent;
            exit;
        }

        // 3. Define the exact files and load sequence.
        //
        // The order below IS the cascade, because this bundler concatenates raw file contents and
        // every block rule is written at the same specificity as the theme rule that wants to
        // restyle it. Whichever file comes last wins. Base styles therefore load first and the
        // active theme's stylesheet loads LAST, so a theme can override any block simply by
        // restating the selector -- which is the whole point of having a theme layer.
        //
        // Getting this backwards is not a subtle bug: it makes theme customization of core blocks
        // silently impossible, with the theme author's rules present in the bundle but inert.
        $cssFiles = [APPLICATION_ROOT . '/public/assets/css/fonts.css'];

        $site = App::getCurrentSite();
        if ($site) {
            // A. Core Block Stylesheets (Core-level layout blocks available to any site)
            $cssFiles = \array_merge($cssFiles, \array_map(
                fn($f) => APPLICATION_ROOT . $f,
                [
                    '/public/assets/css/blocks/hero.css',
                    '/public/assets/css/blocks/text.css',
                    '/public/assets/css/blocks/text_image.css',
                    '/public/assets/css/blocks/gallery.css',
                    '/public/assets/css/blocks/masonry.css',
                    '/public/assets/css/blocks/testimonials.css',
                    '/public/assets/css/blocks/code.css',
                    '/public/assets/css/blocks/chart.css',
                    '/public/assets/css/blocks/grid.css',
                    '/public/assets/css/blocks/sub_pages.css'
                ]
            ));

            // B. Module-contributed stylesheets, registered dynamically via
            // App::registerModuleStylesheet() (e.g. FormBuilder) -- only appended
            // when that module is actually enabled for the requesting site. Still base styles,
            // so these sit ahead of the theme too and remain theme-overridable.
            foreach (App::getRegisteredModuleStylesheets() as $moduleStylesheet) {
                if ($site->isModuleEnabled($moduleStylesheet['module'])) {
                    $cssFiles[] = $moduleStylesheet['path'];
                }
            }
        }

        // C. The active theme, last, so it has the final say over everything above it. Resolved
        // via App::resolveThemeStylesheetFile() so a host project can register its own theme's CSS
        // from outside this repo.
        $themeStylesheetFile = App::resolveThemeStylesheetFile($theme);
        if ($themeStylesheetFile !== null) {
            $cssFiles[] = $themeStylesheetFile;
        }

        $combinedCss = '';

        foreach ($cssFiles as $fullPath) {
            if (\file_exists($fullPath)) {
                $content = \file_get_contents($fullPath);
                if ($content === false) {
                    throw new \Exception("Failed to read source stylesheet file from disk path: {$fullPath}");
                }
                $combinedCss .= $content . "\n\n";
            }
        }

        // 4. Minify compiled CSS (0% Package dependency)
        $minifiedCss = $this->minify($combinedCss);
        $minifiedCss = "/* --- Compiled & Minified Theme Asset Bundle: " . \gmdate('Y-m-d H:i:s') . " UTC [Theme: {$theme}] --- */\n" . $minifiedCss;

        // 5. Save the compiled & minified bundle onto disk so Apache serves it directly next time in production!
        $bytesWritten = \file_put_contents($targetFile, $minifiedCss);
        if ($bytesWritten === false) {
            throw new \Exception("Failed to write compiled CSS bundle to disk path: {$targetFile}");
        }

        \header('Content-Type: text/css; charset=UTF-8');
        \header('Cache-Control: public, max-age=31536000, immutable');
        echo $minifiedCss;
        exit;
    }

    /**
     * Clean and minify raw CSS code natively in PHP with 0% library dependencies.
     *
     * @param string $css The raw combined CSS content.
     * @return string The minified CSS content.
     */
    protected function minify($css)
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
}
