<?php

namespace Zero\Http\Controllers;

use Zero\Interfaces\Controller;
use Zero\Core\App;
use Zero\Core\Env;

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
        if (!$isDevelopment && file_exists($targetFile)) {
            $cachedContent = file_get_contents($targetFile);
            if ($cachedContent === false) {
                throw new \Exception("Failed to read cached CSS bundle from disk path: {$targetFile}");
            }
            header('Content-Type: text/css; charset=UTF-8');
            header('Cache-Control: public, max-age=31536000, immutable');
            echo $cachedContent;
            exit;
        }

        // 3. Define the exact files and load sequence (preserving styling dependencies)
        $cssFiles = [
            // Fonts & Layouts
            '/public/assets/css/fonts.css',
            "/public/assets/css/themes/{$theme}/{$theme}.css"
        ];

        // If it is the guide theme, we also bundle all of its block-specific stylesheets
        if ($theme === 'guide') {
            $cssFiles = array_merge($cssFiles, [
                '/public/assets/css/blocks/text.css',
                '/public/assets/css/blocks/text_image.css',
                '/public/assets/css/blocks/gallery.css',
                '/public/assets/css/blocks/masonry.css',
                '/public/assets/css/blocks/testimonials.css',
                '/public/assets/css/blocks/code.css',
                '/public/assets/css/blocks/contact_form.css',
                '/public/assets/css/blocks/latest_articles.css',
                '/public/assets/css/blocks/sub_pages.css',
                '/public/assets/css/blocks/chart.css',
                '/public/assets/css/blocks/grid.css'
            ]);
        }

        $combinedCss = '';

        foreach ($cssFiles as $f) {
            $fullPath = APPLICATION_ROOT . $f;
            if (file_exists($fullPath)) {
                $content = file_get_contents($fullPath);
                if ($content === false) {
                    throw new \Exception("Failed to read source stylesheet file from disk path: {$fullPath}");
                }
                $combinedCss .= $content . "\n\n";
            }
        }

        // 4. Minify compiled CSS (0% Package dependency)
        $minifiedCss = $this->minify($combinedCss);
        $minifiedCss = "/* --- Compiled & Minified Theme Asset Bundle: " . gmdate('Y-m-d H:i:s') . " UTC [Theme: {$theme}] --- */\n" . $minifiedCss;

        // 5. Save the compiled & minified bundle onto disk so Apache serves it directly next time in production!
        $bytesWritten = file_put_contents($targetFile, $minifiedCss);
        if ($bytesWritten === false) {
            throw new \Exception("Failed to write compiled CSS bundle to disk path: {$targetFile}");
        }

        header('Content-Type: text/css; charset=UTF-8');
        header('Cache-Control: public, max-age=31536000, immutable');
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
        $css = preg_replace('!/\*[^*]*\*+([^/*][^*]*\*+)*/!', '', $css);
        
        // 2. Strip tabs, carriage returns, and newlines
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        
        // 3. Strip redundant multiple spaces
        $css = preg_replace('/\s+/', ' ', $css);
        
        // 4. Strip spaces around structural delimiters and braces
        $css = preg_replace('/\s*([{}|:;,])\s*/', '$1', $css);
        
        // 5. Remove trailing semi-colons inside braces
        $css = str_replace(';}', '}', $css);
        
        return trim($css);
    }
}
