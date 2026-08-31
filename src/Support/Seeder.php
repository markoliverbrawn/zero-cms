<?php

declare(strict_types=1);

/**
 * File: src/Support/Seeder.php
 * Architectural Purpose: Global diagnostic tools, cryptographic security handlers, SMTP email transmitters, and text helpers.
 * Package: Zero\Support
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

// src/Support/Seeder.php

namespace Zero\Support;

use Exception;
use Zero\Core\App;
use Zero\Core\Env;
use Zero\Core\Storage\Storage;
use Zero\Database\DB;
use Zero\Support\Security;

/**
 * Class Seeder
 *
 * Executes a seed dataset against the database, and the extension points around it: per-field
 * filters, per-row processors, and post-table and post-run hooks, so a module can adjust or follow
 * up on seeded data without the seeder needing to know about that module.
 */
class Seeder
{
    protected static array $columnCache = [];
    protected array $data = [];
    protected static array $fieldFilters = [];
    protected array $mediaFilenameMap = [];
    protected static array $postRunHooks = [];
    protected static array $postTableHooks = [];
    protected static array $rowProcessors = [];
    protected array $seededSiteIds = [];
    protected string $uploadsDir;

    /**
     * Constructor supports passing either a JSON file path string, or a pre-defined array!
     *
     * @param string|array $source JSON file path string, or raw pre-compiled data array.
     */
    public function __construct($source)
    {
        $this->uploadsDir = Storage::getUploadsRoot();

        if (\is_array($source)) {
            $this->data = $source;
        } elseif (\is_string($source)) {
            $path = \realpath($source);
            if ($path === false || !\file_exists($path)) {
                throw new Exception("Seeder JSON source file not found at: {$source}");
            }
            $json = \file_get_contents($path);
            $this->data = \json_decode($json, true) ?? [];
        }

        // Register standard core-level handlers on first instantiation
        self::registerDefaultHooks();
    }

    /**
     * Compile the entire project into a clean distribution ZIP archive, excluding sensitive or storage files.
     */
    protected function createDistributionZip(): void
    {
        echo "Compiling system distribution ZIP package...\n";
        $outZipPath = $this->uploadsDir . '/zero-cms-distribution.zip';
        
        $zip = new \ZipArchive();
        if ($zip->open($outZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Unable to open/create zip file at: {$outZipPath}");
        }

        $sourcePath = \realpath(APPLICATION_ROOT);
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if ($file->isDir()) {
                continue;
            }

            $filePath = $file->getRealPath();
            $relativePath = \substr($filePath, \strlen($sourcePath) + 1);

            // Exclude sensitive files, storage, git, and archives
            if (
                \strpos($relativePath, '.env') !== false ||
                \strpos($relativePath, '.git/') === 0 ||
                \strpos($relativePath, 'storage/uploads/') === 0 ||
                \strpos($relativePath, '_archive/') === 0
            ) {
                continue;
            }

            $zip->addFile($filePath, $relativePath);
        }

        $zip->close();
    }

    /**
     * Get the unique site IDs that were seeded or resolved during execution.
     *
     * @return array List of seeded site UUIDs
     */
    public function getSeededSiteIds(): array
    {
        return \array_values($this->seededSiteIds);
    }

    /**
     * Query the DB table layout generically to get valid columns
     */
    protected function getTableColumns(string $table): array
    {
        if (isset(self::$columnCache[$table])) {
            return self::$columnCache[$table];
        }

        try {
            $stmt = DB::query("DESCRIBE {$table}");
            $columns = [];
            while ($row = $stmt->fetch()) {
                $columns[] = $row['Field'] ?? $row['column_name'] ?? '';
            }
            self::$columnCache[$table] = \array_filter($columns);
            return self::$columnCache[$table];
        } catch (\Exception $e) {
            // Fallback if DESCRIBE is not supported
            return [];
        }
    }

    /**
     * Register default core hooks to keep run() generic and decoupled
     */
    public static function registerDefaultHooks(): void
    {
        if (!empty(self::$fieldFilters) || !empty(self::$rowProcessors) || !empty(self::$postTableHooks)) {
            return; // Already registered
        }

        // 1. Field Filter: users api_token hashing
        self::registerFieldFilter('api_token', function ($value, $colName, $tableName) {
            if ($tableName === 'users' && !empty($value)) {
                return \hash('sha256', $value);
            }
            return $value;
        });

        // 2. Field Filter: automatic tenant path routing replacement
        self::registerFieldFilter('*', function ($value, $colName, $tableName, $row) {
            $siteId = $row['site_id'] ?? '';
            if (!empty($siteId) && \is_string($value) && \strpos($value, '/storage/uploads/') !== false) {
                if (\strpos($value, '/storage/uploads/' . $siteId . '/') !== false) {
                    return $value;
                }
                return \str_replace('/storage/uploads/', '/storage/uploads/' . $siteId . '/', $value);
            }
            return $value;
        });

        // 3. Row Processor: pages precedence auto-calculation
        self::registerRowProcessor('pages', function (&$row) {
            static $precedenceCounters = [];
            if (!isset($row['precedence'])) {
                $siteId = $row['site_id'] ?? 'default';
                if (!isset($precedenceCounters[$siteId])) {
                    $precedenceCounters[$siteId] = 10;
                }
                $row['precedence'] = $precedenceCounters[$siteId];
                $precedenceCounters[$siteId] += 10;
            }
        });

        // 4. Row Processor: media base64 decoding and physical copy
        self::registerRowProcessor('media', function (&$row, $seederInstance) {
            $siteId = $row['site_id'] ?? '';
            
            if (isset($row['content_base64'])) {
                $filename = $row['filename'];
                $content = \base64_decode($row['content_base64']);
                $physicalPath = $seederInstance->getUploadsDir() . '/' . $siteId . '/' . $filename;
                Storage::write($physicalPath, $content);
                
                if (!isset($row['path'])) {
                    $row['path'] = Storage::getUrl($physicalPath);
                }
                if (!isset($row['folder'])) {
                    $row['folder'] = '';
                }
                unset($row['content_base64']);
            } elseif (isset($row['filename']) && ($row['mime'] ?? '') !== 'directory') {
                $filename = $row['filename'];
                $physicalPath = $seederInstance->getUploadsDir() . '/' . $siteId . '/' . $filename;

                if (!isset($row['path']) || $row['path'] === '') {
                    $row['path'] = Storage::getUrl($physicalPath);
                }
                if (!isset($row['folder'])) {
                    $row['folder'] = '';
                }
            }
        });

        // 5. Post-Table Hook: pages homepage association
        self::registerPostTableHook('pages', function ($rows) {
            foreach ($rows as $row) {
                if (($row['slug'] ?? null) === '') {
                    $pageId = $row['id'];
                    $siteId = $row['site_id'];
                    DB::query("UPDATE sites SET homepage_id = ? WHERE id = ?", [$pageId, $siteId]);
                    echo "      [Self-Healing] Associated site homepage_id to page ID: {$pageId}\n";
                }
            }
        });

        // 6. Post-Run Hook: Search Indexing
        self::registerPostRunHook(function () {
            echo "Bulk-indexing seeded searchable records...\n";
            try {
                if (\class_exists('\\Zero\\Modules\\Search\\Services\\SearchService')) {
                    $searchables = \Zero\Modules\Search\Services\SearchService::getSearchables();
                    foreach (\array_keys($searchables) as $modelClass) {
                        if (\class_exists($modelClass)) {
                            $tableName = $modelClass::getTableName();
                            $rows = DB::query("SELECT * FROM {$tableName} WHERE deleted_at IS NULL")->fetchAll();
                            foreach ($rows as $row) {
                                $model = new $modelClass($row);
                                $model->indexInSearch();
                            }
                        }
                    }
                    echo "Successfully bulk-indexed all seeded searchable items!\n";
                }
            } catch (\Exception $e) {
                echo "Warning: Bulk-indexing failed: " . $e->getMessage() . "\n";
            }
        });

        // 7. Post-Run Hook: Admin Password Override from .env
        self::registerPostRunHook(function () {
            $adminPassword = Env::get('ADMIN_PASSWORD');
            if (!empty($adminPassword)) {
                echo "Applying custom ADMIN_PASSWORD override from .env...\n";
                try {
                    $hashedPassword = \password_hash($adminPassword, PASSWORD_DEFAULT);
                    DB::query(
                        "UPDATE users SET password_hash = ? WHERE (role = 'super_admin' OR username = 'admin') AND deleted_at IS NULL",
                        [$hashedPassword]
                    );
                    echo "      [Seeder-Hook] Successfully updated administrator account passwords to ADMIN_PASSWORD from .env!\n";
                } catch (\Exception $e) {
                    echo "      [Seeder-Hook-Warning] Failed to apply ADMIN_PASSWORD override: " . $e->getMessage() . "\n";
                }
            }
        });
    }

    /**
     * Register a callback to process specific fields generically.
     */
    public static function registerFieldFilter(string $columnName, callable $callback): void
    {
        self::$fieldFilters[$columnName][] = $callback;
    }

    /**
     * Register a callback to run after the entire seeder system finishes.
     */
    public static function registerPostRunHook(callable $callback): void
    {
        self::$postRunHooks[] = $callback;
    }

    /**
     * Register a callback to execute after a specific table finishes seeding.
     */
    public static function registerPostTableHook(string $tableName, callable $callback): void
    {
        self::$postTableHooks[$tableName][] = $callback;
    }

    /**
     * Register a custom processor for rows in a specific table.
     */
    public static function registerRowProcessor(string $tableName, callable $callback): void
    {
        self::$rowProcessors[$tableName][] = $callback;
    }

    /**
     * Retrieve the internal uploads directory.
     */
    public function getUploadsDir(): string
    {
        return $this->uploadsDir;
    }

    /**
     * Recursively resolve 'media_placeholder' filenames to their seeded media UUIDs and set 'media_id'.
     */
    protected function resolvePlaceholders(&$data): void
    {
        if (\is_array($data)) {
            if (isset($data['media_placeholder'])) {
                $filename = $data['media_placeholder'];
                if (isset($this->mediaFilenameMap[$filename])) {
                    $data['media_id'] = $this->mediaFilenameMap[$filename];
                }
            }
            foreach ($data as $key => &$value) {
                $this->resolvePlaceholders($value);
            }
        }
    }

    /**
     * Run the generic seeding processor for this JSON instance data
     */
    public function run(bool $cleanUploads = true, bool $generateZip = false): bool
    {
        $this->seededSiteIds = [];

        // Enforce a temporary context initially to satisfy active site checks for Storage exists() and cleanDirectory()
        if (\class_exists('\\Zero\\Core\\App') && empty(App::getCurrentSiteId())) {
            App::setCurrentSite(new \Zero\Models\Site([
                'id' => '00000000-0000-0000-0000-000000000000',
                'name' => 'Temporary Seeder Context',
                'domain' => 'localhost',
                'theme' => 'default'
            ]));
        }

        echo "Parsing seeder definition JSON...\n";
        $data = $this->data;

        // Discover all Site records first to populate siteIdMap generically
        $siteIdMap = [];
        if (!empty($data['sites'])) {
            echo "Seeding multi-tenant Site definitions...\n";
            $validSiteColumns = $this->getTableColumns('sites');

            foreach ($data['sites'] as $site) {
                $name = $site['name'];
                $domain = $site['domain'];
                $theme = $site['theme'] ?? 'default';
                $enabledModules = isset($site['enabled_modules']) ? \json_encode($site['enabled_modules']) : '[]';

                // Any other seed-provided field that's a genuine 'sites' column (timezone,
                // default_language, settings, ...) rides along generically, the same way every
                // other table's columns do below -- so a new column never has to be hand-added
                // here again.
                $extraColumns = [];
                $extraValues = [];
                foreach ($site as $key => $val) {
                    if (\in_array($key, ['id', 'name', 'domain', 'theme', 'enabled_modules'], true)) {
                        continue;
                    }
                    if (!empty($validSiteColumns) && !\in_array($key, $validSiteColumns, true)) {
                        continue;
                    }
                    $extraColumns[] = $key;
                    $extraValues[] = \is_array($val) ? \json_encode($val) : $val;
                }

                $existing = DB::query("SELECT id FROM sites WHERE domain = ? LIMIT 1", [$domain])->fetch();
                if ($existing) {
                    $siteId = $existing['id'];
                    $setSql = 'name = ?, theme = ?, enabled_modules = ?';
                    $setParams = [$name, $theme, $enabledModules];
                    foreach ($extraColumns as $i => $col) {
                        $setSql .= ", {$col} = ?";
                        $setParams[] = $extraValues[$i];
                    }
                    $setParams[] = $siteId;
                    DB::query("UPDATE sites SET {$setSql}, updated_at = NOW() WHERE id = ?", $setParams);
                } else {
                    $siteId = $site['id'] ?? Security::uuidv7();
                    $columns = \array_merge(['id', 'name', 'domain', 'theme', 'enabled_modules'], $extraColumns);
                    $values = \array_merge([$siteId, $name, $domain, $theme, $enabledModules], $extraValues);
                    $placeholders = \array_merge(\array_fill(0, \count($values), '?'), ['NOW()', 'NOW()']);
                    $columns[] = 'created_at';
                    $columns[] = 'updated_at';
                    DB::query(
                        "INSERT INTO sites (" . \implode(', ', $columns) . ") VALUES (" . \implode(', ', $placeholders) . ")",
                        $values
                    );
                }
                $siteIdMap[$domain] = $siteId;
                $this->seededSiteIds[$siteId] = $siteId;

                // Dynamically switch the current site to the seeded site Model
                $siteModel = new \Zero\Models\Site($existing ?: \array_merge([
                    'id' => $siteId,
                    'name' => $name,
                    'domain' => $domain,
                    'theme' => $theme,
                    'enabled_modules' => $enabledModules
                ], \array_combine($extraColumns, $extraValues)));
                App::setCurrentSite($siteModel);

                echo "Seeded Site: '{$name}' (ID: {$siteId}) [Domain: {$domain}, Theme: {$theme}]\n";
            }
        } else {
            $sites = DB::query("SELECT id, domain FROM sites")->fetchAll();
            foreach ($sites as $s) {
                $siteIdMap[$s['domain']] = $s['id'];
            }
        }

        // Ensure uploads directory exists
        if (!Storage::exists($this->uploadsDir)) {
            Storage::makeDirectory($this->uploadsDir);
        } elseif ($cleanUploads) {
            Storage::cleanDirectory($this->uploadsDir);
            echo "Cleaned all previous physical media files from uploads folder.\n";
        }

        // Loop through and seed all JSON tables dynamically and generically
        foreach ($data as $tableName => $rows) {
            if ($tableName === 'sites' || \str_ends_with($tableName, '_blueprint') || !\is_array($rows)) {
                continue;
            }

            echo "Seeding table '{$tableName}' dynamically...\n";
            $validColumns = $this->getTableColumns($tableName);

            $seededRows = [];
            foreach ($rows as $row) {
                // Resolve site_id from site_domain dynamically
                if (isset($row['site_domain'])) {
                    $domain = $row['site_domain'];
                    $row['site_id'] = $siteIdMap[$domain] ?? 1;
                    unset($row['site_domain']);
                }

                $siteId = $row['site_id'] ?? '';
                if (!empty($siteId)) {
                    $this->seededSiteIds[$siteId] = $siteId;
                    
                    if (App::getCurrentSiteId() !== $siteId) {
                        $siteRecord = DB::query("SELECT * FROM sites WHERE id = ? LIMIT 1", [$siteId])->fetch();
                        if ($siteRecord) {
                            App::setCurrentSite(new \Zero\Models\Site($siteRecord));
                        }
                    }
                }

                // Generically auto-generate UUIDv7 if not specified
                if (!isset($row['id'])) {
                    $row['id'] = Security::uuidv7();
                }

                // Apply registered Row Processors for this table
                if (isset(self::$rowProcessors[$tableName])) {
                    foreach (self::$rowProcessors[$tableName] as $processor) {
                        $processor($row, $this);
                    }
                }

                // Build a map of filename to media record ID
                if ($tableName === 'media' && isset($row['filename'])) {
                    $this->mediaFilenameMap[$row['filename']] = $row['id'];
                }

                // Recursively resolve any image_placeholder fields in any column values
                $this->resolvePlaceholders($row);

                $columns = [];
                $placeholders = [];
                $values = [];

                foreach ($row as $colName => $val) {
                    // Filter out any JSON properties that do not exist as columns in the DB table
                    if (!empty($validColumns) && !\in_array($colName, $validColumns)) {
                        continue;
                    }

                    // Apply registered Field Filters
                    if (isset(self::$fieldFilters[$colName])) {
                        foreach (self::$fieldFilters[$colName] as $filter) {
                            $val = $filter($val, $colName, $tableName, $row);
                        }
                    }
                    if (isset(self::$fieldFilters['*'])) {
                        foreach (self::$fieldFilters['*'] as $filter) {
                            $val = $filter($val, $colName, $tableName, $row);
                        }
                    }

                    $columns[] = $colName;
                    $placeholders[] = '?';
                    
                    if (\is_array($val)) {
                        $values[] = \json_encode($val);
                    } else {
                        $values[] = $val;
                    }
                }

                if (!\in_array('created_at', $columns)) {
                    $columns[] = 'created_at';
                    $placeholders[] = 'NOW()';
                }
                if (!\in_array('updated_at', $columns)) {
                    $columns[] = 'updated_at';
                    $placeholders[] = 'NOW()';
                }

                $colsSql = \implode(', ', $columns);
                $placeholdersSql = \implode(', ', $placeholders);

                $sql = "INSERT INTO {$tableName} ({$colsSql}) VALUES ({$placeholdersSql})";
                DB::query($sql, $values);

                $seededRows[] = $row;
            }

            // Apply post-table seeding hooks
            if (isset(self::$postTableHooks[$tableName])) {
                foreach (self::$postTableHooks[$tableName] as $hook) {
                    $hook($seededRows);
                }
            }

            DB::clearIdentityMap();
        }

        // Generate distribution ZIP package generically (only if requested and ZipArchive is active)
        if ($generateZip) {
            if (\class_exists('\\ZipArchive')) {
                try {
                    $this->createDistributionZip();
                } catch (\Throwable $e) {
                    echo "Warning: Unable to compile distribution ZIP: " . $e->getMessage() . "\n";
                }
            } else {
                echo "Skipping system distribution ZIP compilation (ZipArchive extension not installed).\n";
            }
        }

        echo "Data seeding finished successfully!\n";
        return true;
    }

    /**
     * Trigger all registered post-run seeder hooks.
     */
    public static function triggerPostRunHooks(): void
    {
        foreach (self::$postRunHooks as $hook) {
            $hook();
        }
    }
}
