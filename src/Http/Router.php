<?php
/**
 * File: src/Http/Router.php
 * Architectural Purpose: HTTP request routing, request filtering middleware, or dynamic content-security controllers.
 * Package: Zero\Http
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Http;

use Zero\Core\App;
use Zero\Models\Page;

/**
 * Class Router
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class Router
{
    protected static $routes = [];
    protected static $routeModules = [];
    protected static $moduleNamespaces = [];

    /**
     * Identify which active module a specific controller class belongs to based on registered namespaces or custom route mappings.
     */
    public static function getModuleForController(string $controllerClass, string $pattern = null): ?string
    {
        if ($pattern !== null && isset(self::$routeModules[$pattern])) {
            return self::$routeModules[$pattern];
        }

        foreach (self::$moduleNamespaces as $ns => $mod) {
            if (strpos($controllerClass, $ns) === 0) {
                return $mod;
            }
        }

        return null;
    }

    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param string $uri Argument descriptor.
     * @return bool Response output.
     */
    public function handle(string $uri): bool
    {
        // 0. Process dynamically registered static module and core admin routes first!
        foreach (self::$routes as $pattern => $controllerClass) {
            if (preg_match($pattern, $uri, $matches)) {
                if (class_exists($controllerClass)) {
                    
                    // Identify if the matched route is associated with an active module
                    $moduleName = self::getModuleForController($controllerClass, $pattern);
                    
                    if ($moduleName !== null) {
                        $site = App::getCurrentSite();
                        // print_r([$moduleName, $site->isModuleEnabled($moduleName)]);
                        if ($site && !$site->isModuleEnabled($moduleName)) {
                            // Module is disabled for this site! Bypass routing (acts as a clean 404).
                            continue;
                        }
                    }
                            
                    $controller = new $controllerClass();
                    $controller->handle($matches);
                    return true;
                }
            }
        }

        // 1. Dynamic Page View fallback (any non-admin slug matches a Page)
        if (preg_match('#^/([a-zA-Z0-9\-/]+)$#', $uri, $matches)) {
            $slug = $matches[1];
            $pageRecord = Page::findBySlug($slug);
            if ($pageRecord) {
                // If a page has a custom controller registered, invoke it dynamically!
                if (!empty($pageRecord->controller)) {
                    $controllerClass = $pageRecord->controller;
                    
                    // Verify if the custom controller's associated module is enabled
                    $moduleName = self::getModuleForController($controllerClass);

                    if ($moduleName !== null) {
                        $site = App::getCurrentSite();
                        if ($site && !$site->isModuleEnabled($moduleName)) {
                            return false; // Module is disabled! Treat as 404 Not Found.
                        }
                    }
                    
                    if (class_exists($controllerClass)) {
                        $controller = new $controllerClass();
                        $controller->handle($pageRecord);
                        return true;
                    }
                }
                
                // Fallback to custom view or default 'post' view rendering
                $viewTemplate = !empty($pageRecord->view) ? $pageRecord->view : 'post';
                App::render($viewTemplate, ['post' => $pageRecord]);
                return true;
            }
        }

        return false;
    }
/**
     * Statically register custom route pattern(s) mapped to controller class(es).
     *
     * @param string|array $routes Either a single regex pattern string, or an associative array [pattern => controllerClass].
     * @param string|null $controllerClass The Controller class name string (only if $routes is a string pattern).
     * @param string|null $moduleName The optional Module identifier this route belongs to (e.g. 'shop', 'blog', 'howtos').
     */
    public static function register($routes, string $controllerClass = null, string $moduleName = null)
    {
        if (is_array($routes)) {
            self::$routes = $routes + self::$routes;
            if ($moduleName !== null) {
                foreach ($routes as $pattern => $controller) {
                    self::$routeModules[$pattern] = $moduleName;
                }
            }
        } elseif ($controllerClass !== null) {
            self::$routes = [$routes => $controllerClass] + self::$routes;
            if ($moduleName !== null) {
                self::$routeModules[$routes] = $moduleName;
            }
        }
    }

    /**
     * Register a module namespace mapping to automatically associate dynamic pages custom controllers.
     */
    public static function registerModuleNamespace(string $namespace, string $moduleName)
    {
        self::$moduleNamespaces[$namespace] = $moduleName;
    }

    }
