<?php

declare(strict_types=1);

/**
 * File: src/Core/Concerns/HandlesRequests.php
 * Architectural Purpose: End-to-end HTTP request dispatch (App::handleRequest()) and the
 * multi-tenant "site not found" diagnostic page it falls back to. Extracted out of App.php.
 * Package: Zero\Core\Concerns
 */

namespace Zero\Core\Concerns;

use Zero\Core\Env;
use Zero\Core\Template;
use Zero\Database\DB;
use Zero\Http\Controllers\CssBundleController;
use Zero\Http\Router;

/**
 * Trait HandlesRequests
 */
trait HandlesRequests
{

    /**
     * Handle the current HTTP request end-to-end: resolve and render the homepage for '/'
     * (including dispatching a custom controller if the homepage page has one registered),
     * register the CSS bundler route, dispatch dynamic/admin routes via Router, and fall back to
     * serving static assets directly from the given public directory. Must be called after
     * App::bootstrap(). Every branch of this method terminates the request (exit or falling off
     * the end into a final 404), matching the original inline public/index.php control flow this
     * was extracted from -- it does not return control to the caller under normal operation.
     *
     * Extracted out of public/index.php so a host project that installs Zero CMS Core via Composer
     * can reuse the exact same request-handling logic from its own front controller instead of
     * hand-copying it (and risking drift, e.g. missing the admin-route or static-asset fallback).
     *
     * @param string $publicDir Absolute path to the calling front controller's own public/
     *                          directory (typically __DIR__ from public/index.php), used to
     *                          resolve the static asset fallback.
     * @return void
     */
    public static function handleRequest(string $publicDir): void
    {
        $uri = \parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Basic routing
        if ($uri === '/' || $uri === '') {
            $site = self::getCurrentSite();

            // Dynamic Homepage Resolution: Fetch the pre-loaded, tenant-isolated homepage page record eager loaded during bootstrapping
            $pageRecord = self::getCurrentHomepage();

            if (!empty($pageRecord)) {
                // If the homepage page has a custom controller registered, invoke it dynamically!
                if (!empty($pageRecord->controller)) {
                    $controllerClass = $pageRecord->controller;

                    // Verify if the custom controller's associated module is enabled
                    $moduleName = Router::getModuleForController($controllerClass);
                    if ($moduleName !== null && $site && !$site->isModuleEnabled($moduleName)) {
                        \http_response_code(404);
                        echo "Homepage module is disabled";
                        exit;
                    }

                    if (\class_exists($controllerClass)) {
                        $controller = new $controllerClass();
                        $controller->handle($pageRecord);
                        exit;
                    }
                }

                // Otherwise, render the page's custom view or the fallback 'post' template
                $viewTemplate = !empty($pageRecord->view) ? $pageRecord->view : 'post';
                self::render($viewTemplate, ['post' => $pageRecord]);
                exit;
            }

            // Default 404 if no homepage page is found in the database at all
            \http_response_code(404);
            echo "Homepage not found";
            exit;
        }

        // Statically register the dynamic theme-specific CSS asset bundler route
        Router::register('#^/assets/css/main-([a-zA-Z0-9_\-]+)\.css$#', CssBundleController::class);

        // Router middleware for all dynamic and admin routes (view post by slug, admin list, edit, new, delete, login, logout, forgot, reset, dashboard)
        $router = new Router();
        if ($router->handle($uri)) {
            exit;
        }

        // admin routes fallback
        if (\strpos($uri, '/admin') === 0) {
            \http_response_code(404);
            echo "Admin route not found";
            exit;
        }

        // static files fallback
        $static = \rtrim($publicDir, '/') . $uri;
        if (\file_exists($static) && \is_file($static)) {
            $ext = \strtolower(\pathinfo($static, PATHINFO_EXTENSION));
            $mimes = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'svg' => 'image/svg+xml',
                'woff2' => 'font/woff2',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'mp4' => 'video/mp4'
            ];
            $mime = $mimes[$ext] ?? \mime_content_type($static);
            \header('Content-Type: ' . $mime);
            \readfile($static);
            exit;
        }

        \http_response_code(404);
        echo "Not found";
    }

    /**
     * Renders a highly-polished, high-contrast, developer-friendly fallback page
     * when a requested host domain is not registered inside the multi-tenant database.
     */
    public static function renderSiteNotFoundPage(string $host): void
    {
        \http_response_code(404);

        $env = \strtolower(Env::get('ENVIRONMENT', 'production'));
        $isDev = ($env === 'development' || $env === 'dev');

        $originalHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $parts = \explode(':', $originalHost);
        $portSuffix = isset($parts[1]) ? ':' . $parts[1] : '';

        // Query database to fetch all currently configured and active multi-tenant sites
        $activeSites = [];
        try {
            $stmt = DB::query("SELECT name, domain FROM sites WHERE deleted_at IS NULL ORDER BY name ASC");
            $activeSites = $stmt->fetchAll();
        } catch (\Exception $e) {
            // Fallback if database itself is not initialized
        }

        $templatePath = APPLICATION_ROOT . '/src/Views/errors/site-not-found.php';
        if (\file_exists($templatePath)) {
            echo Template::renderFile($templatePath, [
                'host' => $host,
                'isDev' => $isDev,
                'portSuffix' => $portSuffix,
                'activeSites' => $activeSites
            ]);
        } else {
            echo "404 Site Not Found";
        }
    }

}
