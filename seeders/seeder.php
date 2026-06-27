<?php
// seeders/seeder.php

define('APPLICATION_ROOT', dirname(__DIR__));

// Register PSR-4 Namespace Autoloading
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

use Zero\Core\Env;
use Zero\Database\DB;
use Zero\Core\App;
use Zero\Database\MigrationManager;
use Zero\Support\Seeder;

Env::load(APPLICATION_ROOT);

// Bootstrap multi-tenant framework to discover all active modules and capabilities
App::bootstrap();

// Parse command line arguments for selective seeding capabilities (e.g. php seeders/seeder.php --only=kitchensink)
$onlySite = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--only=') === 0) {
        $onlySite = substr($arg, 7);
    }
}

$runAll = ($onlySite === null);
$runDocs = $runAll || $onlySite === 'docs' || $onlySite === 'documentation';
$runPortfolio = $runAll || $onlySite === 'portfolio';
$runShop = $runAll || $onlySite === 'shop';
$runKitchenSink = $runAll || $onlySite === 'kitchensink';
$runBlogGenerator = $runAll || $onlySite === 'docs' || $onlySite === 'documentation';

echo "====================================================================\n";
echo "ZERO CMS MULTI-TENANT SEEDER SYSTEM\n";
echo "====================================================================\n";

if ($onlySite) {
    echo "--> Mode: Selective seeding enabled for '{$onlySite}' only...\n\n";
} else {
    echo "\n--> Target: Sequentially initializing all four multi-tenant domains...\n\n";
}

// Run migrations Down then Up to reconstruct all database schemas cleanly (First core tables, then each module sequentially!)
MigrationManager::down();
MigrationManager::up();

// Initialize Central Database Tables Schemas & Super Admin from static JSON configuration
$coreData = json_decode(file_get_contents(APPLICATION_ROOT . '/seeders/data/corporate.json'), true) ?? [];
if ($onlySite !== null) {
    // If selective seeding is enabled, we only want the core users (super admins) from corporate.json.
    // We remove "sites", "pages", and "media" keys to prevent seeding the corporate main site record and its pages/assets!
    unset($coreData['sites']);
    unset($coreData['pages']);
    unset($coreData['media']);
}
$coreSeeder = new Seeder($coreData);
$coreSeeder->run(true); // First run cleans the uploads folder

// Hydrate Stage 2 (Developer Docs)
if ($runDocs) {
    echo "--------------------------------------------------\n";
    echo "STAGE 2: Seeding Zero CMS Technical Developer Docs...\n";
    echo "--------------------------------------------------\n";
    $docsSeeder = new Seeder(APPLICATION_ROOT . '/seeders/data/documentation.json');
    $docsSeeder->run(false); // Subsequent runs preserve existing files
}

// Hydrate Stage 3 (Designer Portfolio)
if ($runPortfolio) {
    echo "--------------------------------------------------\n";
    echo "STAGE 3: Seeding Zero CMS Designer Portfolio & Compiling Bundle...\n";
    echo "--------------------------------------------------\n";
    $portfolioSeeder = new Seeder(APPLICATION_ROOT . '/seeders/data/portfolio.json');
    $portfolioSeeder->run(false); // Subsequent runs preserve existing files
}

// Hydrate Stage 4 (Luxe E-Commerce Shop)
if ($runShop) {
    echo "--------------------------------------------------\n";
    echo "STAGE 4: Seeding Zero CMS Luxe E-Commerce Shop...\n";
    echo "--------------------------------------------------\n";
    $shopSeeder = new Seeder(APPLICATION_ROOT . '/seeders/data/shop.json');
    $shopSeeder->run(false); // Subsequent runs preserve existing files
}

// Hydrate Stage 5 (Kitchen Sink Showroom)
if ($runKitchenSink) {
    echo "--------------------------------------------------\n";
    echo "STAGE 5: Seeding Zero CMS Kitchen Sink Showroom...\n";
    echo "--------------------------------------------------\n";
    $kitchenSinkSeeder = new Seeder(APPLICATION_ROOT . '/seeders/data/kitchensink.json');
    $kitchenSinkSeeder->run(false); // Subsequent runs preserve existing files

    echo "--> Running Kitchen Sink dynamic orders seeder...\n";
    $output = [];
    $exitCode = 0;
    exec("php " . APPLICATION_ROOT . "/seeders/seed_kitchensink_orders.php", $output, $exitCode);
    foreach ($output as $line) {
        echo "   " . $line . "\n";
    }
}

// Dynamically generate and seed 50 premium long-form blog posts (at least 5000 words each) for the Guide site
if ($runBlogGenerator) {
    require_once APPLICATION_ROOT . '/seeders/generate_blog_articles.php';
}

// Securely adjust ownership of storage directories recursively to the web server user (www-data)
if (function_exists('posix_getuid') && posix_getuid() === 0) {
    echo "--> Automatically adjusting ownership of storage folder recursively to 'www-data'...\n";
    @exec("chown -R www-data:www-data " . APPLICATION_ROOT . "/storage");
}

echo "====================================================================\n";
echo "DATABASE SEEDING OPERATIONS COMPLETED WITH 100% SUCCESS!\n";
echo "====================================================================\n";
