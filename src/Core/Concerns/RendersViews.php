<?php

declare(strict_types=1);

/**
 * File: src/Core/Concerns/RendersViews.php
 * Architectural Purpose: Theme-aware view rendering (App::render()), pagination HTML generation,
 * the custom view-directory override registry, and the dev-mode benchmark widget. Extracted out
 * of App.php.
 * Package: Zero\Core\Concerns
 */

namespace Zero\Core\Concerns;

use Zero\Core\Env;
use Zero\Core\Template;
use Zero\Database\DB;
use Zero\Support\Security;
use Zero\Support\Str;

/**
 * Trait RendersViews
 */
trait RendersViews
{
    protected static $viewDirs = [];

    /**
     * Appends database query benchmarking and page load time telemetry widgets.
     *
     * @return mixed Response output.
     */
    public static function appendBenchmarkWidget()
    {
        if (Env::get('BENCHMARKING') !== 'true') {
            return;
        }

        $queryLog = DB::getQueryLog();
        $queryCount = DB::getQueryCount();
        $totalTime = DB::getTotalQueryTime();
        
        // Calculate total page execution runtime
        $totalPageTime = \microtime(true) - (\defined('REQUEST_START_TIME') ? REQUEST_START_TIME : $_SERVER['REQUEST_TIME_FLOAT']);

        // Render the benchmark widget from view template safely
        echo Template::renderFile(APPLICATION_ROOT . '/src/Views/components/BenchmarkWidget.php', [
            'queryLog' => $queryLog,
            'queryCount' => $queryCount,
            'totalTime' => $totalTime,
            'totalPageTime' => $totalPageTime,
            'nonce' => self::getNonce()
        ]);
    }

    /**
     * Appends a floating "Edit This Page" shortcut widget for logged-in users
     * with permission to edit the currently viewed page.
     *
     * @param mixed $post Current page record (or null if not applicable).
     * @return mixed Response output.
     */
    public static function appendEditPageWidget($post = null)
    {
        if (empty($post->id)) {
            return;
        }

        if (!self::authorize('content.edit')) {
            return;
        }

        echo Template::renderFile(APPLICATION_ROOT . '/src/Views/components/EditPageWidget.php', [
            'pageId' => $post->id,
            'nonce' => self::getNonce()
        ]);
    }

    /**
     * Registers the view dir component definition dynamically.
     *
     * @param string $prefix Argument descriptor.
     * @param string $dirPath Argument descriptor.
     * @return mixed Response output.
     */
    public static function registerViewDir(string $prefix, string $dirPath)
    {
        self::$viewDirs[$prefix] = \rtrim($dirPath, '/');
    }

    /**
     * Render processing implementation helper.
     *
     * @param mixed $view Argument descriptor.
     * @param mixed $data Argument descriptor.
     * @return mixed Response output.
     */
    public static function render($view, $data = [])
    {
        $phpFile = null;
        $layoutFile = null;

        // Check if the view starts with any registered custom view directory prefix (e.g. 'admin/')
        foreach (self::$viewDirs as $prefix => $dirPath) {
            if (\strpos($view, $prefix . '/') === 0) {
                $subView = \substr($view, \strlen($prefix) + 1);
                $phpFile = $dirPath . '/' . $subView . '.php';
                $layoutFile = $dirPath . '/layout.php';
                break;
            }
        }

        if ($phpFile === null) {
            // Frontend Multi-tenant Theme Resolver
            $site = self::getCurrentSite();
            $theme = $site ? ($site->theme ?? 'default') : 'default';
            
            $phpFile = self::resolveThemeFile($theme, $view . '.php')
                ?? (APPLICATION_ROOT . '/src/Views/themes/' . $theme . '/' . $view . '.php');
            $layoutFile = self::resolveThemeFile($theme, 'layout.php')
                ?? (APPLICATION_ROOT . '/src/Views/themes/' . $theme . '/layout.php');
        }

        if (\file_exists($phpFile)) {
            // ensure csrf token is available to templates
            $data['csrf'] = Security::csrfToken();
            $data['error'] = $data['error'] ?? '';
            $data['session'] = $_SESSION;
    
            $content = Template::renderFile($phpFile, $data);
            if (\file_exists($layoutFile)) {
                $data['content'] = $content;
                $data['error'] = $data['error'] ?? '';
                echo Template::renderFile($layoutFile, $data);
                self::appendBenchmarkWidget(); // Inject unified benchmark overlay!
                self::appendEditPageWidget($data['post'] ?? null); // Inject frontend "Edit This Page" shortcut!
                return;
            }
        }

        // If .php file not found, directly return "View not found".
        echo "View not found: " . Str::escape($view);
        return;
    }

    /**
     * Render a unified, sliding-window pagination HTML block.
     * Preserves active query parameters automatically and scales up safely.
     *
     * @param array $pagination Pagination metadata array
     * @param string $baseUrl Base URL string (e.g. '/search' or '/admin/list/pages')
     * @param array $queryParams Current $_GET parameters array to merge and preserve
     * @return string Compiled HTML string
     */
    public static function renderPagination(array $pagination, string $baseUrl, array $queryParams = []): string
    {
        $currentPage = isset($pagination['currentPage']) ? (int)$pagination['currentPage'] : 1;
        $totalPages = isset($pagination['totalPages']) ? (int)$pagination['totalPages'] : 1;

        if ($totalPages <= 1) {
            return '';
        }

        // Clean and prepare query parameters, skipping 'page' as it is appended dynamically
        $cleanedParams = [];
        foreach ($queryParams as $k => $v) {
            if ($k !== 'page' && $v !== null && $v !== '') {
                $cleanedParams[$k] = $v;
            }
        }

        // The '#pagination' fragment (matching the <nav id="pagination"> below) makes the browser
        // scroll straight to the pagination control on load -- without it, following a page link
        // lands the visitor back at the very top of a long list, which reads as jarring/broken.
        $buildUrl = function($pageNum) use ($baseUrl, $cleanedParams) {
            $params = \array_merge($cleanedParams, ['page' => $pageNum]);
            return $baseUrl . '?' . \http_build_query($params) . '#pagination';
        };

        // Sliding window range calculation
        $range = 2;
        $startPage = $currentPage - $range;
        $endPage = $currentPage + $range;

        if ($startPage < 1) {
            $endPage += \abs($startPage) + 1;
            $startPage = 1;
        }
        if ($endPage > $totalPages) {
            $startPage -= ($endPage - $totalPages);
            $endPage = $totalPages;
        }
        $startPage = \max(1, $startPage);

        $showFirst = ($startPage > 1);
        $showLast = ($endPage < $totalPages);

        // Buffer the baseline template render
        \ob_start();
        $partialPath = self::resolveThemeFile('default', 'partials/pagination.php');
        if ($partialPath !== null) {
            include $partialPath;
        } else {
            // Inline fallback markup if the partial file is missing
            ?>
            <nav id="pagination" class="unified-pagination-wrapper">
                <?php if ($currentPage > 1): ?>
                    <a href="<?php echo Str::escape($buildUrl($currentPage - 1)); ?>" class="pagination-btn page-nav-prev">Prev</a>
                <?php endif; ?>
                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <?php if ($i === $currentPage): ?>
                        <span class="pagination-btn active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo Str::escape($buildUrl($i)); ?>" class="pagination-btn"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?php echo Str::escape($buildUrl($currentPage + 1)); ?>" class="pagination-btn page-nav-next">Next</a>
                <?php endif; ?>
            </nav>
            <?php
        }
        return \ob_get_clean();
    }

}
