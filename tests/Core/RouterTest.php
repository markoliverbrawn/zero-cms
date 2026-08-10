<?php
// tests/RouterTest.php
// Unit tests for HTTP Router engine (Zero\Http\Router)

require_once __DIR__ . '/bootstrap.php';

use Zero\Http\Router;

echo "=== Router Component Tests ===\n";

// Clear previous static routes state for clean unit test
try {
    $reflector = new ReflectionClass(Router::class);
    $routesProp = $reflector->getProperty('routes');
    $routesProp->setAccessible(true);
    $routesProp->setValue(null, []);

    $modulesProp = $reflector->getProperty('routeModules');
    $modulesProp->setAccessible(true);
    $modulesProp->setValue(null, []);

    $nsProp = $reflector->getProperty('moduleNamespaces');
    $nsProp->setAccessible(true);
    $nsProp->setValue(null, []);
} catch (Exception $e) {
    echo "Warning resetting Router properties: " . $e->getMessage() . "\n";
}

// 1. Test registration of single route
echo "Testing route registration...\n";
Router::register('#^/api/items$#', 'Zero\\Http\\Controllers\\ApiController', 'shop');

$routes = $routesProp->getValue();
assert_test(isset($routes['#^/api/items$#']), "Route is successfully stored in registered routes mapping");
assert_test($routes['#^/api/items$#'] === 'Zero\\Http\\Controllers\\ApiController', "Route is mapped to correct controller class");

// 2. Test registration of batch routes array
$batch = [
    '#^/blog/list$#' => 'Zero\\Modules\\Blog\\Controllers\\BlogController',
    '#^/blog/post/([a-z0-9\-]+)$#' => 'Zero\\Modules\\Blog\\Controllers\\PostViewController'
];
Router::register($batch, null, 'blog');

$routes = $routesProp->getValue();
assert_test(isset($routes['#^/blog/list$#']), "Batch route 1 registered successfully");
assert_test(isset($routes['#^/blog/post/([a-z0-9\-]+)$#']), "Batch route 2 registered successfully with regex dynamic pattern");

// 3. Test Module namespace resolution
echo "Testing controller-to-module namespace mapping...\n";
Router::registerModuleNamespace('Zero\\Modules\\Blog\\', 'blog');
Router::registerModuleNamespace('Zero\\Modules\\Shop\\', 'shop');

$module1 = Router::getModuleForController('Zero\\Modules\\Blog\\Controllers\\BlogController');
$module2 = Router::getModuleForController('Zero\\Modules\\Shop\\Controllers\\CheckoutController');
$module3 = Router::getModuleForController('Zero\\Http\\Controllers\\ApiController', '#^/api/items$#');
$moduleNone = Router::getModuleForController('Zero\\Core\\App');

assert_test($module1 === 'blog', "Resolves module 'blog' correctly from controller namespace prefix");
assert_test($module2 === 'shop', "Resolves module 'shop' correctly from controller namespace prefix");
assert_test($module3 === 'shop', "Resolves module 'shop' correctly from explicit route-pattern mappings overrides");
assert_test($moduleNone === null, "Returns null for controller class not residing in any registered module namespace");

echo "Router component tests completed.\n\n";
