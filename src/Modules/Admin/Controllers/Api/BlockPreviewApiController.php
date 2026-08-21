<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Controllers/Api/BlockPreviewApiController.php
 * Architectural Purpose: REST API endpoint that renders a single page-builder block server-side
 * (theme-aware) for the admin block editor's live preview pane.
 * Package: Zero\Modules\Admin\Controllers\Api
 */

namespace Zero\Modules\Admin\Controllers\Api;

use Zero\Core\App;
use Zero\Core\Template;
use Zero\Support\Security;

/**
 * Class BlockPreviewApiController
 */
class BlockPreviewApiController extends AdminApiControllerBase
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $matches Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($matches)
    {
        $this->authenticate();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = \parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $body = $this->parseBody();

        if (\preg_match('#^/api/v1/admin/block-preview/?$#', $uri) && $method === 'POST') {
            $this->handleBlockPreview($body);
        }

        $this->respond(['success' => false, 'error' => 'Endpoint not found or method not allowed'], 404);
    }

    /**
     * Handle block preview processing implementation helper.
     *
     * @param mixed $body Argument descriptor.
     * @return mixed Response output.
     */
    protected function handleBlockPreview($body)
    {
        $block = $body['block'] ?? [];
        $type = $block['type'] ?? 'text';
        $site = App::getCurrentSite();
        $theme = $site ? ($site->theme ?? 'default') : 'default';

        // Dynamic Cascading Block View Resolution:
        // 1. Check if the active theme overrides this block: src/Views/themes/{theme}/blocks/{type}.php
        // 2. Check if the block has a registered, module-owned 'frontend_view' path.
        // 3. Graceful legacy fallback.
        $themeBlocksDir = App::resolveThemeDir($theme);
        $blockPath = $themeBlocksDir !== null ? $themeBlocksDir . '/blocks/' . $type . '.php' : '';
        if (!\file_exists($blockPath)) {
            $registeredBlock = App::getRegisteredBlocks()[$type] ?? [];
            if (!empty($registeredBlock['frontend_view']) && \file_exists($registeredBlock['frontend_view'])) {
                $blockPath = $registeredBlock['frontend_view'];
            } else {
                $blockPath = App::resolveThemeFile('default', 'blocks/' . $type . '.php') ?? '';
            }
        }

        // The same resolver the frontend uses, so a preview resolves media (and mints resized
        // variant URLs) through exactly the code path the published page will. The previous
        // bespoke closure issued one model lookup per image; this batches the whole block.
        $resolveMedia = App::mediaResolver([$block]);

        if (\file_exists($blockPath)) {
            \ob_start();
            echo Template::renderFile($blockPath, [
                'block' => $block,
                'resolveMedia' => $resolveMedia
            ]);
            $html = \ob_get_clean();

            // Clean/Sanitise HTML output for XSS (bypass dynamically based on the block's registered configuration option)
            $registeredBlock = App::getRegisteredBlocks()[$type] ?? [];
            $bypassSanitizer = $registeredBlock['bypass_preview_sanitizer'] ?? false;

            if (!$bypassSanitizer) {
                $html = Security::sanitizeHtml($html);
            }

            // Construct block-level title preview based on the active theme so header settings affect the block preview
            $titleHtml = '';
            $hideTitle = $block['hide_title'] ?? '0';
            $title = $block['title'] ?? '';

            if ($hideTitle !== '1' && !empty($title) && $type !== 'hero') {
                if ($theme === 'kitchensink') {
                    $tag = $hideTitle === '2' ? 'h1' : 'h3';
                    $colorVar = \in_array($type, ['text_image', 'testimonials', 'gallery']) ? '--neon-pink' : '--neon-cyan';
                    $titleHtml = '<' . $tag . ' style="color: var(' . $colorVar . '); margin-bottom: 1.25rem;">' . Security::sanitizeHtml($title) . '</' . $tag . '>';
                } else {
                    $tag = $hideTitle === '2' ? 'h1' : 'h2';
                    $titleHtml = '<' . $tag . ' class="block-section-title">' . Security::sanitizeHtml($title) . '</' . $tag . '>';
                }
            }

            $html = $titleHtml . $html;

            // Determine appropriate theme stylesheets dynamically using App theme registry
            $themeStylesheets = [];
            $themeStylesheets[] = '/assets/css/blocks/hero.css'; // Always load dynamic public block hero styles!

            // Dynamically load block-specific styles if they exist on disk (e.g. blocks/text_image.css)
            $blockCss = '/assets/css/blocks/' . $type . '.css';
            if (\file_exists(APPLICATION_ROOT . '/public' . $blockCss)) {
                $themeStylesheets[] = $blockCss;
            }

            // Resolve theme main stylesheet dynamically (registry lookup, falling back to the
            // bundled convention path internally)
            $themeStylesheet = App::getThemeStylesheet($theme);
            if (!empty($themeStylesheet)) {
                $themeStylesheets[] = $themeStylesheet;
            }

            $this->respond([
                'success' => true,
                'html' => $html,
                'theme' => $theme,
                'stylesheets' => $themeStylesheets
            ]);
        } else {
            $this->respond([
                'success' => false,
                'error' => 'Block template not found'
            ], 404);
        }
    }
}
