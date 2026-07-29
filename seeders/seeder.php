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

// Parse command line arguments for selective seeding capabilities and ZIP generation
$onlySite = null;
$generateZip = false;
foreach ($argv as $arg) {
    if (strpos($arg, '--only=') === 0) {
        $onlySite = substr($arg, 7);
    }
    if ($arg === '--zip') {
        $generateZip = true;
    }
}

echo "====================================================================\n";
echo "ZERO CMS MULTI-TENANT SEEDER SYSTEM (CLASS-BASED OOP)\n";
echo "====================================================================\n";

if ($onlySite) {
    echo "--> Mode: Selective seeding enabled for '{$onlySite}' only...\n\n";
} else {
    echo "\n--> Target: Sequentially initializing all multi-tenant domains...\n\n";
}

// Run migrations Down then Up to reconstruct all database schemas cleanly (First core tables, then each module sequentially!)
MigrationManager::down();
MigrationManager::up();

// Dynamic Class Seeder Auto-Discovery Engine (OOP Seeders)
$classSeeders = [];
$modulesDir = APPLICATION_ROOT . '/src/Modules';
if (is_dir($modulesDir)) {
    $folders = scandir($modulesDir);
    foreach ($folders as $folder) {
        if ($folder === '.' || $folder === '..') {
            continue;
        }
        $moduleSeederDir = "{$modulesDir}/{$folder}/Seeders";
        if (is_dir($moduleSeederDir)) {
            $files = scandir($moduleSeederDir);
            foreach ($files as $file) {
                if (str_ends_with($file, 'Seeder.php') && $file !== 'Seeder.php') {
                    $className = "Zero\\Modules\\{$folder}\\Seeders\\" . basename($file, '.php');
                    if (class_exists($className)) {
                        $seederInstance = new $className();
                        if ($seederInstance instanceof \Zero\Interfaces\SeederInterface) {
                            $classSeeders[] = $seederInstance;
                        }
                    }
                }
            }
        }
    }
}

// Sort class seeders by execution priority
usort($classSeeders, function ($a, $b) {
    return $a->getPriority() <=> $b->getPriority();
});

// Dynamic Seeder Auto-Discovery Engine (JSON Datasets)
$discoveredFiles = [];

// 1. Scan global seeder directory
$seederDataDir = APPLICATION_ROOT . '/seeders/data';
if (is_dir($seederDataDir)) {
    $files = scandir($seederDataDir);
    foreach ($files as $file) {
        if (str_ends_with($file, '.json') && $file !== 'handwritten_articles.json') {
            $discoveredFiles[$file] = [
                'filename' => $file,
                'path' => $seederDataDir . '/' . $file
            ];
        }
    }
}

// 2. Scan active modular directories dynamically for encapsulated datasets
if (is_dir($modulesDir)) {
    $folders = scandir($modulesDir);
    foreach ($folders as $folder) {
        if ($folder === '.' || $folder === '..') {
            continue;
        }
        $moduleSeederDir = "{$modulesDir}/{$folder}/Seeders";
        if (is_dir($moduleSeederDir)) {
            $files = scandir($moduleSeederDir);
            foreach ($files as $file) {
                if (str_ends_with($file, '.json') && $file !== 'handwritten_articles.json') {
                    $discoveredFiles[$file] = [
                        'filename' => $file,
                        'path' => $moduleSeederDir . '/' . $file
                    ];
                }
            }
        }
    }
}

// Prioritize files based on system ordering requirements
$priorityMap = [
    'corporate.json' => 10,
    'documentation.json' => 20,
    'portfolio.json' => 30,
    'shop.json' => 40,
    'kitchensink.json' => 50,
];

// Convert maps to a list and sort by precedence priority
$discoveredList = array_values($discoveredFiles);

usort($discoveredList, function ($a, $b) use ($priorityMap) {
    $pA = $priorityMap[$a['filename']] ?? 100;
    $pB = $priorityMap[$b['filename']] ?? 100;
    return $pA <=> $pB;
});

// Run each discovered dataset sequentially
$cleanUploads = true;

foreach ($discoveredList as $setInfo) {
    $filename = $setInfo['filename'];
    $filePath = $setInfo['path'];
    $identifier = basename($filename, '.json');

    // Selective filtering capability
    if ($onlySite !== null && $onlySite !== 'blank') {
        if ($identifier !== 'corporate' && $identifier !== $onlySite) {
            continue; // Skip if not corporate/core and not targeted
        }
    }

    echo "--------------------------------------------------\n";
    echo "SEEDING DATASET: {$filename} (ID: {$identifier})\n";
    echo "--------------------------------------------------\n";

    $data = json_decode(file_get_contents($filePath), true) ?? [];

    // Core processing for corporate.json selective overrides
    if ($identifier === 'corporate' && $onlySite !== null && $onlySite !== 'corporate') {
        if ($onlySite === 'blank') {
            // Seed a clean standalone blank welcome site
            $baseUrl = Env::get('BASE_URL', 'http://localhost');
            $parsedUrl = parse_url($baseUrl);
            $targetDomain = $parsedUrl['host'] ?? 'localhost';
            
            $data['sites'] = [
                [
                    "id" => "019fa1f1-7800-7031-a269-fcc0aa1fe578",
                    "name" => "My New Standalone Site",
                    "domain" => $targetDomain,
                    "theme" => "default",
                    "enabled_modules" => ["blog", "security", "queue", "site-search", "formbuilder"]
                ]
            ];
            
            $data['pages'] = [
                [
                    "id" => "019fa1f1-7bcc-72f0-8c3b-9732ab7f9e3a",
                    "site_domain" => $targetDomain,
                    "title" => "Welcome",
                    "slug" => "",
                    "status" => "published",
                    "content" => [
                        [
                            "type" => "text",
                            "title" => "Welcome to your new Zero CMS website!",
                            "content" => "<p>You have successfully initialized a blank standalone Zero CMS project. Log in to the <a href=\"/admin\">admin area</a> to start customizing your pages, blocks, and themes!</p>"
                        ]
                    ],
                    "precedence" => 0
                ]
            ];
            
            unset($data['media']);
        } else {
            // Strip out site structures to run core-only user generation (super admin)
            unset($data['sites']);
            unset($data['pages']);
            unset($data['media']);
        }
    }

    // Dynamically override default domains to match active BASE_URL if injected (Cloud Run deploy)
    $baseUrl = Env::get('BASE_URL');
    if (!empty($baseUrl)) {
        $parsedUrl = parse_url($baseUrl);
        $targetDomain = $parsedUrl['host'] ?? null;
        if ($targetDomain) {
            echo "--> Dynamically overriding corporate site domain reference to: {$targetDomain}\n";
            
            if (isset($data['sites'])) {
                foreach ($data['sites'] as &$site) {
                    if ($site['domain'] === 'd6laptop.zero') {
                        $site['domain'] = $targetDomain;
                    }
                }
            }
            if (isset($data['media'])) {
                foreach ($data['media'] as &$media) {
                    if ($media['site_domain'] === 'd6laptop.zero') {
                        $media['site_domain'] = $targetDomain;
                    }
                }
            }
            if (isset($data['pages'])) {
                foreach ($data['pages'] as &$page) {
                    if ($page['site_domain'] === 'd6laptop.zero') {
                        $page['site_domain'] = $targetDomain;
                    }
                }
            }
        }
    }

    // Run core seeder
    $seeder = new Seeder($data);
    $seeder->run($cleanUploads, $generateZip);
    $cleanUploads = false; // Preserve uploads directory on subsequent sets

    // Run any discovered class seeders targeting ONLY the site IDs seeded in this dataset
    $datasetSiteIds = $seeder->getSeededSiteIds();
    foreach ($datasetSiteIds as $siteId) {
        $siteRow = DB::query("SELECT name, enabled_modules FROM sites WHERE id = ?", [$siteId])->fetch();
        if ($siteRow) {
            $enabledModules = json_decode($siteRow['enabled_modules'] ?? '[]', true);
            foreach ($classSeeders as $oopSeeder) {
                $moduleId = $oopSeeder->getModuleId();

                // Selective filtering capability for modular class seeders
                if ($onlySite !== null && $onlySite !== 'blank' && $moduleId !== $onlySite) {
                    continue; // Skip if targeting another module specifically
                }

                // Execute only if the module is active/enabled for this site
                if (in_array($moduleId, $enabledModules)) {
                    $oopSeeder->run($siteId, APPLICATION_ROOT . '/public/storage/uploads');

                    // Scoped Garbage Collection to keep CLI footprint extremely light
                    DB::clearIdentityMap();
                    gc_collect_cycles();
                }
            }
        }
    }
}

// Trigger all dynamically registered post-run Hooks (e.g. search indices)
Seeder::triggerPostRunHooks();

// Securely adjust ownership of storage directories recursively to the web server user (www-data) on Linux
if (function_exists('posix_getuid') && posix_getuid() === 0) {
    echo "--> Automatically adjusting ownership of storage folder recursively to 'www-data'...\n";
    @exec("chown -R www-data:www-data " . APPLICATION_ROOT . "/storage");
}

echo "====================================================================\n";
echo "DATABASE SEEDING OPERATIONS COMPLETED WITH 100% SUCCESS!\n";
echo "====================================================================\n";
