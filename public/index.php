<?php
define('REQUEST_START_TIME', microtime(true));
// Error reporting is configured dynamically once the environment is loaded below.

define('APPLICATION_ROOT', dirname(__DIR__));
define('VIEWS_DIR', APPLICATION_ROOT . '/src/Views');

// Register global PSR-4 Autoloader for Zero namespace mapping
spl_autoload_register(function ($class) {
    $prefix = 'Zero\\';
    $base_dir = APPLICATION_ROOT . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use Zero\Support\Security;
use Zero\Core\Env;
use Zero\Database\DB;
use Zero\Core\App;
use Zero\Support\Logger;
use Zero\Http\Router;
use Zero\Http\Controllers\CssBundleController;

Env::load(APPLICATION_ROOT);

// Security Hardening: Disable error disclosures in production to prevent schema/path leaks
if (Env::get('ENVIRONMENT') === 'production') {
    ini_set('display_errors', '0');
    error_reporting(0);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Bootstrap Multi-Tenant CMS (Automatically discovers active modules, registers routes, and boots tenant)
App::bootstrap();

$base = rtrim(Env::get('BASE_URL', ''), '/');

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Basic routing
if ($uri === '/' || $uri === '') {
    $site = App::getCurrentSite();
    
    // Dynamic Homepage Resolution: Fetch the pre-loaded, tenant-isolated homepage page record eager loaded during bootstrapping
    $pageRecord = App::getCurrentHomepage();
    
    if (!empty($pageRecord)) {

        // If the homepage page has a custom controller registered, invoke it dynamically!
        if (!empty($pageRecord->controller)) {
            $controllerClass = $pageRecord->controller;
            
            // Verify if the custom controller's associated module is enabled
            $moduleName = Router::getModuleForController($controllerClass);
            if ($moduleName !== null && $site && !$site->isModuleEnabled($moduleName)) {
                http_response_code(404);
                echo "Homepage module is disabled";
                exit;
            }
            
            if (class_exists($controllerClass)) {
                $controller = new $controllerClass();
                $controller->handle($pageRecord);
                exit;
            }
        }

        // Otherwise, render the page's custom view or the fallback 'post' template
        $viewTemplate = !empty($pageRecord->view) ? $pageRecord->view : 'post';
        App::render($viewTemplate, ['post' => $pageRecord]);
        exit;
    }

    // Default 404 if no homepage page is found in the database at all
    http_response_code(404);
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
if (strpos($uri, '/admin') === 0) {
    http_response_code(404);
    echo "Admin route not found";
    exit;
}

// static files fallback
$cleanUri = parse_url($uri, PHP_URL_PATH);
$static = __DIR__ . $cleanUri;
if (file_exists($static) && is_file($static)) {
    $ext = strtolower(pathinfo($static, PATHINFO_EXTENSION));
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
    $mime = $mimes[$ext] ?? mime_content_type($static);
    header('Content-Type: ' . $mime);
    readfile($static);
    exit;
}

http_response_code(404);
echo "Not found";
