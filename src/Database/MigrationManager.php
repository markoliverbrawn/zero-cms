<?php

namespace Zero\Database;

use Zero\Database\DB;

class MigrationManager
{
    /**
     * Run all registered migrations down (Drop/rollback latest batch).
     */
    public static function down()
    {
        echo "==================================================\n";
        echo "DATABASE MIGRATIONS REVERSION: DOWN\n";
        echo "==================================================\n";

        DB::clearColumnCache();
        DB::query("SET FOREIGN_KEY_CHECKS = 0;");

        // 1. Check if migrations tracking table exists
        $hasTable = DB::query("SHOW TABLES LIKE 'migrations'")->fetch();
        if (!$hasTable) {
            echo "No migrations tracking table found. Wiping core and module tables manually...\n";
            $tables = ['shop_order_items', 'shop_orders', 'shop_product_variants', 'shop_products', 'shop_categories', 'blog_posts', 'audit_logs', 'password_resets', 'media', 'pages', 'users', 'sites'];
            foreach ($tables as $table) {
                DB::query("DROP TABLE IF EXISTS {$table};");
            }
            DB::query("SET FOREIGN_KEY_CHECKS = 1;");
            echo "Database schemas manually wiped cleanly.\n\n";
            return;
        }

        // 2. Fetch the latest batch of migrations
        $latestBatch = DB::query("SELECT MAX(batch) FROM migrations")->fetchColumn();
        if (!$latestBatch) {
            echo "No migrations have been run yet. Nothing to revert.\n";
            DB::query("SET FOREIGN_KEY_CHECKS = 1;");
            return;
        }

        // 3. Query migrations from the latest batch in reverse chronological order
        $stmt = DB::query("SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC", [$latestBatch]);
        $migrationsToRevert = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // 4. Fetch the dynamic migrations map to match namespaces
        $migrationsList = self::getMigrations();

        // 5. Run down() method for each migration in the batch in reverse order
        foreach ($migrationsToRevert as $name) {
            $meta = $migrationsList[$name] ?? null;
            if ($meta) {
                $file = $meta['file'];
                $class = $meta['class'];

                if (file_exists($file)) {
                    require_once $file;
                }

                if (class_exists($class)) {
                    echo "Reverting sequential migration: {$name} (Batch {$latestBatch})...\n";
                    $instance = new $class();
                    $instance->down();

                    // Delete the tracking record
                    DB::query("DELETE FROM migrations WHERE migration = ?", [$name]);
                }
            }
        }

        // Drop migrations table itself if all migrations have been completely rolled back
        $remaining = DB::query("SELECT COUNT(*) FROM migrations")->fetchColumn();
        if ($remaining == 0) {
            DB::query("DROP TABLE IF EXISTS migrations;");
            echo "All migrations have been completely rolled back. Cleaned up migrations table.\n";
        }

        DB::query("SET FOREIGN_KEY_CHECKS = 1;");
        echo "Database schemas reverted cleanly.\n\n";
    }
/**
     * Dynamically discover and register all sequential migrations.
     * Maps sequential filenames (e.g. "0001_CreateCoreTables") to an array with the physical file path
     * and the resolved fully qualified class name.
     *
     * @return array
     */
    public static function getMigrations(): array
    {
        $migrations = [];

        // 1. Discover Core Migrations on disk
        $corePath = APPLICATION_ROOT . '/src/Database/Migrations/[0-9]*_*.php';
        $coreFiles = glob($corePath);
        if (is_array($coreFiles)) {
            foreach ($coreFiles as $file) {
                $filename = basename($file, '.php'); // e.g. "0001_CreateCoreTables"
                
                // Extract class name by removing sequential prefix (e.g. "0001_")
                $className = preg_replace('/^\d+_/', '', $filename);
                $classNamespace = "\\Zero\\Database\\Migrations\\" . $className;

                $migrations[$filename] = [
                    'file' => $file,
                    'class' => $classNamespace
                ];
            }
        }

        // 2. Discover Extensible Module Migrations on disk
        $modulePath = APPLICATION_ROOT . '/src/Modules/*/Database/Migrations/[0-9]*_*.php';
        $moduleFiles = glob($modulePath);
        if (is_array($moduleFiles)) {
            foreach ($moduleFiles as $file) {
                $filename = basename($file, '.php'); // e.g. "0002_CreateBlogTables"
                $className = preg_replace('/^\d+_/', '', $filename);

                // Extract module name dynamically from path to construct correct PSR-4 namespace
                if (preg_match('/src\/Modules\/([a-zA-Z0-9]+)\//', $file, $matches)) {
                    $moduleName = $matches[1];
                    $classNamespace = "\\Zero\\Modules\\{$moduleName}\\Database\\Migrations\\" . $className;

                    $migrations[$filename] = [
                        'file' => $file,
                        'class' => $classNamespace
                    ];
                }
            }
        }

        // 3. Sort keys alphabetically/chronologically to guarantee sequential order!
        ksort($migrations);

        return $migrations;
    }

    /**
     * Run all pending dynamic sequential migrations up.
     */
    public static function up()
    {
        echo "==================================================\n";
        echo "DATABASE MIGRATIONS HANDSHAKE: UP\n";
        echo "==================================================\n";

        DB::clearColumnCache();
        DB::query("SET FOREIGN_KEY_CHECKS = 0;");

        // 1. Ensure migrations tracking table exists
        DB::query("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT NOT NULL,
                run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // 2. Fetch already executed migrations
        $executed = DB::query("SELECT migration FROM migrations")->fetchAll(\PDO::FETCH_COLUMN);

        // 3. Determine next batch number
        $currentBatch = DB::query("SELECT MAX(batch) FROM migrations")->fetchColumn();
        $nextBatch = $currentBatch ? (int)$currentBatch + 1 : 1;

        // 4. Fetch dynamic sequential migrations map
        $migrationsList = self::getMigrations();

        // 5. Run any pending migrations in sequential order
        $runCount = 0;
        foreach ($migrationsList as $name => $meta) {
            if (in_array($name, $executed)) {
                continue; // Already run, skip!
            }

            $file = $meta['file'];
            $class = $meta['class'];

            // Manually require the file on disk to support sequential prefixes
            if (file_exists($file)) {
                require_once $file;
            }

            if (class_exists($class)) {
                echo "Running sequential migration: {$name}...\n";
                $instance = new $class();
                $instance->up();

                // Log it as executed in the migrations table
                DB::query(
                    "INSERT INTO migrations (migration, batch) VALUES (?, ?)",
                    [$name, $nextBatch]
                );
                $runCount++;
            } else {
                echo "Error: Class '{$class}' could not be loaded from file '{$file}' for migration '{$name}'.\n";
            }
        }

        DB::query("SET FOREIGN_KEY_CHECKS = 1;");
        
        if ($runCount > 0) {
            echo "Database Migrations completed with 100% SUCCESS! (Batch {$nextBatch}, {$runCount} migrations run)\n";
        } else {
            echo "Nothing to migrate. Database is completely up to date.\n";
        }
        echo "==================================================\n\n";
    }

    }
