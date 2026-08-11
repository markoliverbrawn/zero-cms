<?php
define('REQUEST_START_TIME', microtime(true));
// Error reporting is configured dynamically once the environment is loaded below.

define('APPLICATION_ROOT', dirname(__DIR__));
define('VIEWS_DIR', APPLICATION_ROOT . '/src/Views');

// Register the Zero\ -> src/ namespace autoloader (shared by every entry point)
require_once APPLICATION_ROOT . '/src/Core/Autoloader.php';
\Zero\Core\Autoloader::init();

use Zero\Core\Env;
use Zero\Core\App;

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

// Resolve and render the homepage for '/', dispatch dynamic/admin routes, and fall back to
// serving static assets directly from this front controller's own public/ directory.
App::handleRequest(__DIR__);
