<?php
// src/Support/Seeder.php

namespace Zero\Support;

use Zero\Database\DB;
use Zero\Core\Storage\Storage;
use Exception;

class Seeder
{
    protected $data = [];
    protected $uploadsDir;
    protected static $columnCache = [];
    protected $mediaFilenameMap = [];

    /**
     * Constructor supports passing either a JSON file path string, or a pre-defined array!
     *
     * @param string|array $source JSON file path string, or raw pre-compiled data array.
     */
    public function __construct($source)
    {
        $this->uploadsDir = APPLICATION_ROOT . '/public/storage/uploads';

        if (is_array($source)) {
            $this->data = $source;
        } elseif (is_string($source)) {
            $path = realpath($source);
            if ($path === false || !file_exists($path)) {
                throw new Exception("Seeder JSON source file not found at: {$source}");
            }
            $json = file_get_contents($path);
            $this->data = json_decode($json, true) ?? [];
        }
    }

    /**
     * Compile the entire project into a clean distribution ZIP archive, excluding sensitive or storage files.
     */
    protected function createDistributionZip()
    {
        echo "Compiling system distribution ZIP package...\n";
        $outZipPath = $this->uploadsDir . '/zero-cms-distribution.zip';
        
        $zip = new \ZipArchive();
        if ($zip->open($outZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Unable to open/create zip file at: {$outZipPath}");
        }

        $sourcePath = realpath(APPLICATION_ROOT);
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if ($file->isDir()) {
                continue;
            }

            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($sourcePath) + 1);

            // Exclude sensitive files, storage, git, and archives
            if (
                strpos($relativePath, '.env') !== false ||
                strpos($relativePath, '.git/') === 0 ||
                strpos($relativePath, 'storage/uploads/') === 0 ||
                strpos($relativePath, '_archive/') === 0 ||
                strpos($relativePath, 'seeders/documentation/pack_svgs.php') !== false ||
                strpos($relativePath, 'seeders/documentation/add_') !== false ||
                strpos($relativePath, 'seeders/documentation/update_') !== false ||
                strpos($relativePath, 'seeders/documentation/nest_') !== false ||
                strpos($relativePath, 'seeders/documentation/restructure_') !== false
            ) {
                continue;
            }

            $zip->addFile($filePath, $relativePath);
        }

        $zip->close();
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
            self::$columnCache[$table] = array_filter($columns);
            return self::$columnCache[$table];
        } catch (\Exception $e) {
            // Fallback if DESCRIBE is not supported
            return [];
        }
    }

    /**
     * Recursively resolve 'media_placeholder' filenames to their seeded media UUIDs and set 'media_id'.
     */
    protected function resolvePlaceholders(&$data)
    {
        if (is_array($data)) {
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

    public function run(bool $cleanUploads = true): bool
    {
        echo "Parsing seeder definition JSON...\n";
        $data = $this->data;

        // Step 1: Discover all Site records first to populate siteIdMap generically
        $siteIdMap = [];
        if (!empty($data['sites'])) {
            echo "Seeding multi-tenant Site definitions...\n";
            foreach ($data['sites'] as $site) {
                $name = $site['name'];
                $domain = $site['domain'];
                $theme = $site['theme'] ?? 'default';
                $enabledModules = isset($site['enabled_modules']) ? json_encode($site['enabled_modules']) : '[]';
                
                // Self-healing: Check if site already exists to prevent duplication
                $existing = DB::query("SELECT id FROM sites WHERE domain = ? LIMIT 1", [$domain])->fetch();
                if ($existing) {
                    $siteId = $existing['id'];
                    DB::query("UPDATE sites SET name = ?, theme = ?, enabled_modules = ?, updated_at = NOW() WHERE id = ?", [$name, $theme, $enabledModules, $siteId]);
                } else {
                    $siteId = $site['id'] ?? Security::uuidv7();
                    DB::query(
                        "INSERT INTO sites (id, name, domain, theme, enabled_modules, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                        [$siteId, $name, $domain, $theme, $enabledModules]
                    );
                }
                $siteIdMap[$domain] = $siteId;
                echo "Seeded Site: '{$name}' (ID: {$siteId}) [Domain: {$domain}, Theme: {$theme}]\n";
            }
        } else {
            // Pre-populate siteIdMap from existing database sites to ensure we can resolve domains generically
            $sites = DB::query("SELECT id, domain FROM sites")->fetchAll();
            foreach ($sites as $s) {
                $siteIdMap[$s['domain']] = $s['id'];
            }
        }

        // Ensure uploads directory exists
        if (!Storage::exists($this->uploadsDir)) {
            Storage::makeDirectory($this->uploadsDir);
        } elseif ($cleanUploads) {
            // Clean/remove all previous physical media files from the folder before seeding!
            Storage::cleanDirectory($this->uploadsDir);
            echo "Cleaned all previous physical media files from uploads folder.\n";
        }

        // Step 2: Loop through and seed all JSON tables dynamically and generically
        foreach ($data as $tableName => $rows) {
            // Sites are already handled above in Step 1
            if ($tableName === 'sites') {
                continue;
            }

            if (!is_array($rows)) {
                continue;
            }

            echo "Seeding table '{$tableName}' dynamically...\n";
            $validColumns = $this->getTableColumns($tableName);

            $precedenceCounters = [];
            foreach ($rows as $row) {
                // Automatically assign sequential display precedence if not explicitly defined
                if ($tableName === 'pages' && !isset($row['precedence'])) {
                    $siteId = $row['site_id'] ?? 1;
                    if (!isset($precedenceCounters[$siteId])) {
                        $precedenceCounters[$siteId] = 10;
                    }
                    $row['precedence'] = $precedenceCounters[$siteId];
                    $precedenceCounters[$siteId] += 10;
                }
                // Resolve site_id from site_domain dynamically
                if (isset($row['site_domain'])) {
                    $domain = $row['site_domain'];
                    $row['site_id'] = $siteIdMap[$domain] ?? 1;
                    unset($row['site_domain']);
                }

                // Automate physical multi-tenant file path translation for all seeded column fields
                $siteId = $row['site_id'] ?? '';
                if (!empty($siteId)) {
                    foreach ($row as $colName => $val) {
                        if (is_string($val) && strpos($val, '/storage/uploads/') !== false) {
                            $row[$colName] = str_replace('/storage/uploads/', '/storage/uploads/' . $siteId . '/', $val);
                        }
                    }
                }

                // If this is a physical media item (with content_base64), decode and write it to disk first!
                if (isset($row['content_base64'])) {
                    $filename = $row['filename'];
                    $content = base64_decode($row['content_base64']);
                    $physicalPath = $this->uploadsDir . '/' . $siteId . '/' . $filename;
                    Storage::write($physicalPath, $content);
                    
                    // Assign default path and folder values if not already present
                    if (!isset($row['path'])) {
                        $row['path'] = '/storage/uploads/' . $siteId . '/' . $filename;
                    }
                    if (!isset($row['folder'])) {
                        $row['folder'] = '';
                    }
                    unset($row['content_base64']);
                } elseif ($tableName === 'media' && isset($row['filename']) && ($row['mime'] ?? '') !== 'directory') {
                    // Check if physical file exists in the seed images or videos directory
                    $filename = $row['filename'];
                    $seedImgPath = APPLICATION_ROOT . '/seeders/data/images/' . $filename;
                    if (!file_exists($seedImgPath)) {
                        $seedImgPath = APPLICATION_ROOT . '/seeders/data/videos/' . $filename;
                    }
                    
                    if (file_exists($seedImgPath)) {
                        $content = file_get_contents($seedImgPath);
                        $physicalPath = $this->uploadsDir . '/' . $siteId . '/' . $filename;
                        Storage::write($physicalPath, $content);
                    }
                    
                    // Assign default path and folder values if not already present
                    if (!isset($row['path']) || $row['path'] === '') {
                        $row['path'] = '/storage/uploads/' . $siteId . '/' . $filename;
                    }
                    if (!isset($row['folder'])) {
                        $row['folder'] = '';
                    }
                }

                // Generically auto-generate UUIDv7 if not specified
                if (!isset($row['id'])) {
                    $row['id'] = Security::uuidv7();
                }

                // Build a map of filename to media record ID
                if ($tableName === 'media' && isset($row['filename'])) {
                    $this->mediaFilenameMap[$row['filename']] = $row['id'];
                }

                // Recursively resolve any image_placeholder fields in any column values
                $this->resolvePlaceholders($row);

                // Self-healing: If seeding a homepage page record (slug is empty ""), automatically update the Site model to register its homepage_id!
                if ($tableName === 'pages' && $row['slug'] === '') {
                    $pageId = $row['id'];
                    $pSiteId = $row['site_id'];
                    DB::query("UPDATE sites SET homepage_id = ? WHERE id = ?", [$pageId, $pSiteId]);
                    echo "      [Self-Healing] Associated site homepage_id to page ID: {$pageId}\n";
                }

                $columns = [];
                $placeholders = [];
                $values = [];

                foreach ($row as $colName => $val) {
                    // Filter out any JSON properties that do not exist as columns in the DB table!
                    if (!empty($validColumns) && !in_array($colName, $validColumns)) {
                        continue;
                    }

                    // Hardened API Token secure hashing during database seeding operations
                    if ($tableName === 'users' && $colName === 'api_token' && !empty($val)) {
                        $val = hash('sha256', $val);
                    }

                    $columns[] = $colName;
                    $placeholders[] = '?';
                    
                    // Automatically serialize nested arrays (e.g. blocks/content) generically into JSON strings!
                    if (is_array($val)) {
                        $values[] = json_encode($val);
                    } else {
                        $values[] = $val;
                    }
                }

                // Append created_at and updated_at automatically if they are missing
                if (!in_array('created_at', $columns)) {
                    $columns[] = 'created_at';
                    $placeholders[] = 'NOW()';
                }
                if (!in_array('updated_at', $columns)) {
                    $columns[] = 'updated_at';
                    $placeholders[] = 'NOW()';
                }

                $colsSql = implode(', ', $columns);
                $placeholdersSql = implode(', ', $placeholders);

                $sql = "INSERT INTO {$tableName} ({$colsSql}) VALUES ({$placeholdersSql})";
                DB::query($sql, $values);
            }

            // Garbage Collection: Clear active record identity caching to preserve CLI memory
            DB::clearIdentityMap();
        }

        // Step 3: Bulk-index seeded records across all sites
        echo "Bulk-indexing seeded searchable records...\n";
        try {
            // 1. Pages
            if (class_exists('\\Zero\\Models\\Page')) {
                $rows = DB::query("SELECT * FROM pages WHERE deleted_at IS NULL")->fetchAll();
                foreach ($rows as $row) {
                    $model = new \Zero\Models\Page($row);
                    $model->indexInSearch();
                }
            }
            // 2. Blog Posts
            if (class_exists('\\Zero\\Modules\\Blog\\Models\\Post')) {
                $rows = DB::query("SELECT * FROM blog_posts WHERE deleted_at IS NULL")->fetchAll();
                foreach ($rows as $row) {
                    $model = new \Zero\Modules\Blog\Models\Post($row);
                    $model->indexInSearch();
                }
            }
            // 3. Products
            if (class_exists('\\Zero\\Modules\\Shop\\Models\\Product')) {
                $rows = DB::query("SELECT * FROM shop_products WHERE deleted_at IS NULL")->fetchAll();
                foreach ($rows as $row) {
                    $model = new \Zero\Modules\Shop\Models\Product($row);
                    $model->indexInSearch();
                }
            }
            echo "Successfully bulk-indexed all seeded searchable items!\n";
        } catch (\Exception $e) {
            echo "Warning: Bulk-indexing failed: " . $e->getMessage() . "\n";
        }

        // Generate distribution ZIP package generically
        try {
            $this->createDistributionZip();
        } catch (\Exception $e) {
            echo "Warning: Unable to compile distribution ZIP: " . $e->getMessage() . "\n";
        }

        echo "Data seeding finished successfully!\n";
        return true;
    }
}
