<?php

declare(strict_types=1);

/**
 * File: src/Support/SeederRunner.php
 * Architectural Purpose: Multi-tenant database seeding orchestration -- discovers every module's
 * OOP class seeders and dataset blueprint files, reconstructs the database schema, and runs them
 * in priority order. Extracted out of the former seeders/seeder.php (now bin/seed) so the
 * orchestration logic lives alongside the core Seeder engine class it drives.
 * Package: Zero\Support
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support;

use Zero\Core\App;
use Zero\Core\Env;
use Zero\Core\Storage\Storage;
use Zero\Database\DB;
use Zero\Database\MigrationManager;
use Zero\Interfaces\SeederInterface;

/**
 * Class SeederRunner
 *
 * Discovers and runs every module's seed data (OOP class seeders and dataset blueprint files),
 * after reconstructing the database schema from scratch. Supports selective per-site/per-module
 * targeting via CLI flags and optional post-run ZIP packaging.
 */
class SeederRunner
{
    /**
     * Parse, migrate, discover, and run the full multi-tenant seeding pipeline. Echoes progress
     * directly (matching the original script's behavior) and always returns 0 -- any fatal error
     * surfaces as an uncaught exception, matching prior behavior.
     *
     * @param array $argv Raw CLI arguments (as passed to the entry point script).
     * @return int
     */
    public static function run(array $argv): int
    {
        Env::load(APPLICATION_ROOT);

        // Bootstrap multi-tenant framework to discover all active modules and capabilities
        App::bootstrap();

        [$targetSites, $generateZip] = self::parseArgs($argv);

        echo "====================================================================\n";
        echo "ZERO CMS MULTI-TENANT SEEDER SYSTEM (CLASS-BASED OOP)\n";
        echo "====================================================================\n";

        if (!empty($targetSites)) {
            $targetsStr = \implode(', ', $targetSites);
            echo "--> Mode: Selective seeding enabled for: [{$targetsStr}]...\n\n";
        } else {
            echo "\n--> Target: Sequentially initializing all multi-tenant domains...\n\n";
        }

        // Fully unwind the schema to empty (looping down() across every batch, not just the
        // latest one) before rebuilding it from scratch via up(). The seeder engine assumes a
        // truly empty database -- only 'sites' rows are upserted by domain, every other table
        // (users, pages, media, ...) is an unconditional INSERT, so a partial reset here would
        // resurface as a duplicate-key error the moment seeding actually runs.
        do {
            MigrationManager::down();
        } while (MigrationManager::hasBatches());
        MigrationManager::up();

        $modulesDir = APPLICATION_ROOT . '/src/Modules';
        $classSeeders = self::discoverClassSeeders($modulesDir);
        $discoveredList = self::discoverDatasetFiles($modulesDir);

        $cleanUploads = true;
        foreach ($discoveredList as $setInfo) {
            $ran = self::seedDataset($setInfo, $targetSites, $generateZip, $cleanUploads, $classSeeders);
            if ($ran) {
                $cleanUploads = false; // Preserve uploads directory on subsequent sets
            }
        }

        // Trigger all dynamically registered post-run Hooks (e.g. search indices)
        Seeder::triggerPostRunHooks();

        self::fixStorageOwnership();

        echo "====================================================================\n";
        echo "DATABASE SEEDING OPERATIONS COMPLETED WITH 100% SUCCESS!\n";
        echo "====================================================================\n";

        return 0;
    }

    /**
     * Parse --only=/--sites=/--zip CLI flags, falling back to the SEED_SITES env var for target
     * sites if no CLI flag was given.
     *
     * @param array $argv
     * @return array{0: string[], 1: bool} [$targetSites, $generateZip]
     */
    private static function parseArgs(array $argv): array
    {
        $targetSites = [];
        $generateZip = false;

        foreach ($argv as $arg) {
            if (\strpos($arg, '--only=') === 0) {
                $rawSites = \substr($arg, 7);
                $targetSites = \array_filter(\array_map('trim', \explode(',', $rawSites)));
            } elseif (\strpos($arg, '--sites=') === 0) {
                $rawSites = \substr($arg, 8);
                $targetSites = \array_filter(\array_map('trim', \explode(',', $rawSites)));
            }
            if ($arg === '--zip') {
                $generateZip = true;
            }
        }

        if (empty($targetSites)) {
            $envSites = Env::get('SEED_SITES');
            if (!empty($envSites)) {
                $targetSites = \array_filter(\array_map('trim', \explode(',', $envSites)));
            }
        }

        return [$targetSites, $generateZip];
    }

    /**
     * Dynamic Class Seeder Auto-Discovery Engine (OOP Seeders). Scans every module's own
     * Seeders/ folder for classes named *Seeder.php (excluding the base Seeder.php itself)
     * implementing SeederInterface, sorted by getPriority(). Public so DemoSiteFactory can reuse
     * the exact same discovery+priority mechanism when seeding an on-demand sandbox site, instead
     * of hardcoding a duplicate list of module seeder class names.
     *
     * @param string $modulesDir
     * @return SeederInterface[]
     */
    public static function discoverClassSeeders(string $modulesDir): array
    {
        $classSeeders = [];

        if (\is_dir($modulesDir)) {
            $folders = \scandir($modulesDir);
            foreach ($folders as $folder) {
                if ($folder === '.' || $folder === '..') {
                    continue;
                }
                $moduleSeederDir = "{$modulesDir}/{$folder}/Seeders";
                if (\is_dir($moduleSeederDir)) {
                    $files = \scandir($moduleSeederDir);
                    foreach ($files as $file) {
                        if (\str_ends_with($file, 'Seeder.php') && $file !== 'Seeder.php') {
                            $className = "Zero\\Modules\\{$folder}\\Seeders\\" . \basename($file, '.php');
                            if (\class_exists($className)) {
                                $seederInstance = new $className();
                                if ($seederInstance instanceof SeederInterface) {
                                    $classSeeders[] = $seederInstance;
                                }
                            }
                        }
                    }
                }
            }
        }

        \usort($classSeeders, function ($a, $b) {
            return $a->getPriority() <=> $b->getPriority();
        });

        return $classSeeders;
    }

    /**
     * Dynamic Seeder Auto-Discovery Engine (dataset blueprint files). Scans every module's own
     * Seeders/ folder for a fixed set of recognized dataset filenames, sorted by a priority map
     * (lower runs first).
     *
     * @param string $modulesDir
     * @return array<int, array{filename: string, path: string}>
     */
    private static function discoverDatasetFiles(string $modulesDir): array
    {
        $priorityMap = [
            'default.php' => 10,
            'documentation.php' => 20,
            'kitchensink.php' => 50,
        ];

        $discoveredFiles = [];

        if (\is_dir($modulesDir)) {
            $folders = \scandir($modulesDir);
            foreach ($folders as $folder) {
                if ($folder === '.' || $folder === '..') {
                    continue;
                }
                $moduleSeederDir = "{$modulesDir}/{$folder}/Seeders";
                if (\is_dir($moduleSeederDir)) {
                    $files = \scandir($moduleSeederDir);
                    foreach ($files as $file) {
                        if (\str_ends_with($file, '.php') && isset($priorityMap[$file])) {
                            $discoveredFiles[$file] = [
                                'filename' => $file,
                                'path' => $moduleSeederDir . '/' . $file
                            ];
                        }
                    }
                }
            }
        }

        $discoveredList = \array_values($discoveredFiles);

        \usort($discoveredList, function ($a, $b) use ($priorityMap) {
            $pA = $priorityMap[$a['filename']] ?? 100;
            $pB = $priorityMap[$b['filename']] ?? 100;
            return $pA <=> $pB;
        });

        return $discoveredList;
    }

    /**
     * Run a single discovered dataset file: apply selective-targeting overrides (including the
     * 'blank' clean-install override), apply the BASE_URL domain override, run the core Seeder
     * engine, and run any class seeders scoped to the site IDs this dataset actually seeded.
     *
     * @param array{filename: string, path: string} $setInfo
     * @param string[] $targetSites
     * @param bool $generateZip
     * @param bool $cleanUploads
     * @param SeederInterface[] $classSeeders
     * @return bool True if this dataset was actually run (not skipped by selective targeting).
     */
    private static function seedDataset(array $setInfo, array $targetSites, bool $generateZip, bool $cleanUploads, array $classSeeders): bool
    {
        $filename = $setInfo['filename'];
        $filePath = $setInfo['path'];
        $identifier = \basename($filename, '.php');

        // Selective filtering capability
        if (!empty($targetSites)) {
            if (\in_array('blank', $targetSites)) {
                if ($identifier !== 'default') {
                    return false; // Skip all other datasets for blank/clean install
                }
            } else {
                if ($identifier !== 'default' && !\in_array($identifier, $targetSites)) {
                    return false; // Skip if not default/core and not targeted
                }
            }
        }

        echo "--------------------------------------------------\n";
        echo "SEEDING DATASET: {$filename} (ID: {$identifier})\n";
        echo "--------------------------------------------------\n";

        $data = require $filePath;

        // Core processing for default.php selective overrides
        if ($identifier === 'default' && !empty($targetSites) && !\in_array('default', $targetSites)) {
            if (\in_array('blank', $targetSites)) {
                // Seed a clean standalone blank welcome site
                $baseUrl = Env::get('BASE_URL', 'http://localhost');
                $parsedUrl = \parse_url($baseUrl);
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
            $parsedUrl = \parse_url($baseUrl);
            $targetDomain = $parsedUrl['host'] ?? null;
            if ($targetDomain) {
                echo "--> Dynamically overriding default site domain reference to: {$targetDomain}\n";

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

        // Run any discovered class seeders targeting ONLY the site IDs seeded in this dataset
        $datasetSiteIds = $seeder->getSeededSiteIds();
        foreach ($datasetSiteIds as $siteId) {
            $siteRow = DB::query("SELECT name, enabled_modules FROM sites WHERE id = ?", [$siteId])->fetch();
            if ($siteRow) {
                $enabledModules = \json_decode($siteRow['enabled_modules'] ?? '[]', true);
                foreach ($classSeeders as $oopSeeder) {
                    $moduleId = $oopSeeder->getModuleId();

                    // Selective filtering capability for modular class seeders
                    if (!empty($targetSites) && !\in_array('blank', $targetSites)) {
                        $isModuleTargeted = \in_array($moduleId, $targetSites);
                        $isDatasetTargeted = \in_array($identifier, $targetSites);
                        if (!$isModuleTargeted && !$isDatasetTargeted) {
                            continue; // Skip if neither the specific module nor parent dataset is targeted
                        }
                    }

                    // Execute only if the module is active/enabled for this site
                    if (\in_array($moduleId, $enabledModules)) {
                        $oopSeeder->run($siteId, Storage::getUploadsRoot());

                        // Scoped Garbage Collection to keep CLI footprint extremely light
                        DB::clearIdentityMap();
                        \gc_collect_cycles();
                    }
                }
            }
        }

        return true;
    }

    /**
     * Securely adjust ownership of storage directories recursively to the web server user
     * (www-data) on Linux, only when running as root.
     *
     * @return void
     */
    private static function fixStorageOwnership(): void
    {
        if (\function_exists('posix_getuid') && \posix_getuid() === 0) {
            echo "--> Automatically adjusting ownership of storage folder recursively to 'www-data'...\n";
            @\exec("chown -R www-data:www-data " . APPLICATION_ROOT . "/storage");
        }
    }
}
