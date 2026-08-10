<?php

declare(strict_types=1);

/**
 * Zero CMS - Core Database Connection Engine
 *
 * This class handles PDO connections, dynamic socket fallbacks, prepared statement query logs,
 * dynamic columns/metadata caching, Active Record identity mappings, and lazy test-suite DB setups.
 *
 * PHP version 8.3
 *
 * @package    Zero\Database
 * @author     Zero CMS Team
 * @copyright  2026 Zero CMS
 */

namespace Zero\Database;

use Exception;
use PDO;
use PDOException;
use Zero\Core\Env;
use Zero\Support\Logger;

/**
 * Class DB
 *
 * The central database management utility supporting high-performance connection, caching, and logs.
 */
class DB
{
    protected static $pdo = null;
    protected static $queryLog = [];
    protected static $totalQueryTime = 0;
    protected static $columnCache = [];
    protected static $identityMap = [];
    protected static $testDbInitialized = false;

    /**
     * Clear the static column schema cache completely.
     *
     * @return void
     */
    public static function clearColumnCache(): void
    {
        self::$columnCache = [];
    }

    /**
     * Clear the static identity map cache completely (Garbage Collection).
     *
     * @return void
     */
    public static function clearIdentityMap(): void
    {
        self::$identityMap = [];
    }

    /**
     * Fetch a cached Active Record instance from the static identity map.
     *
     * @param string $table The database table name.
     * @param string $id    The primary UUID key.
     * @return mixed|null   The cached record instance, or null.
     */
    public static function getIdentity(string $table, string $id)
    {
        return self::$identityMap[$table][$id] ?? null;
    }

    /**
     * Get the active PDO MySQL database connection.
     * Evaluates and lazy-initializes isolated testing database environments dynamically.
     *
     * @return PDO The active database transceiver.
     * @throws PDOException On connection or handshake failures.
     */
    public static function getPDO()
    {
        // 1. Intercept test runs to dynamically initialize test schemas on-demand
        if (\defined('TEST_SUITE_RUNNING') && TEST_SUITE_RUNNING && !self::$testDbInitialized) {
            self::$testDbInitialized = true;
            self::initializeTestDatabase();
        }

        if (self::$pdo) {
            return self::$pdo;
        }

        $db = Env::get('DB_NAME', 'cms');
        $user = Env::get('DB_USER', 'root');
        $pass = Env::get('DB_PASS', '');

        $socket = Env::get('DB_SOCKET', '');
        if ($socket) {
            $dsn = "mysql:unix_socket={$socket};dbname={$db};charset=utf8mb4";
        } else {
            $host = Env::get('DB_HOST', '127.0.0.1');
            $port = Env::get('DB_PORT', '3306');
            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        }

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$pdo = $pdo;
            return $pdo;
        } catch (PDOException $e) {
            // Write to local server error log instead of DB to prevent infinite recursion loop
            \error_log("Database connection failed: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            
            if (\php_sapi_name() === 'cli') {
                throw $e; // In CLI, throw the exception so calling scripts can catch it and terminate with clean exit codes!
            }
            
            \http_response_code(500);
            echo "A database error occurred. Please try again later.";
            exit;
        }
    }

    /**
     * Get the total count of executed prepared statements logged.
     *
     * @return int The log list length count.
     */
    public static function getQueryCount(): int
    {
        return \count(self::$queryLog);
    }

    /**
     * Retrieve the database query logs array.
     *
     * @return array List of compiled SQL statement telemetry dictionary entries.
     */
    public static function getQueryLog(): array
    {
        return self::$queryLog;
    }

    /**
     * Get the aggregated query execution duration.
     *
     * @return float The sum duration in seconds.
     */
    public static function getTotalQueryTime(): float
    {
        return self::$totalQueryTime;
    }

    /**
     * Verify the existence of a given column in a database table schema.
     *
     * @param string $table  The DB table name.
     * @param string $column The target column name.
     * @return bool          True if column exists, else false.
     */
    public static function hasColumn(string $table, string $column): bool
    {
        $key = "{$table}.{$column}";
        if (isset(self::$columnCache[$key])) {
            return self::$columnCache[$key];
        }

        try {
            self::getPDO()->query("SELECT {$column} FROM {$table} LIMIT 1");
            self::$columnCache[$key] = true;
        } catch (Exception $e) {
            self::$columnCache[$key] = false;
        }

        return self::$columnCache[$key];
    }

    /**
     * Dynamically initializes the isolated test database on-demand during testing.
     * Prevents database connection overhead and table truncation latency for pure unit tests.
     *
     * @return void
     */
    protected static function initializeTestDatabase(): void
    {
        $user = Env::get('DB_USER', 'root');
        $pass = Env::get('DB_PASS', '');
        $testDb = Env::get('DB_NAME');

        $socket = Env::get('DB_SOCKET', '');
        if ($socket) {
            $rawDsn = "mysql:unix_socket={$socket};charset=utf8mb4";
            $dsn = "mysql:unix_socket={$socket};dbname={$testDb};charset=utf8mb4";
        } else {
            $host = Env::get('DB_HOST', '127.0.0.1');
            $port = Env::get('DB_PORT', '3306');
            $rawDsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $dsn = "mysql:host={$host};port={$port};dbname={$testDb};charset=utf8mb4";
        }

        try {
            $rawPdo = new PDO($rawDsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $rawPdo->exec("CREATE DATABASE IF NOT EXISTS `" . \str_replace("`", "``", $testDb) . "`");
        } catch (PDOException $e) {
            echo "Fatal Error ensuring test database exists: " . $e->getMessage() . "\n";
            exit(1);
        }

        try {
            // Always run pending migrations to ensure all core & module schemas are present
            \ob_start();
            MigrationManager::up();
            \ob_end_clean();

            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // Rapid truncate to guarantee 100% clean test isolation on every run
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

            $tables = ['pages', 'blog_posts', 'audit_logs', 'password_resets', 'sites', 'blog_comments', 'form_submissions', 'queue_jobs', 'queue_scheduled_tasks'];
            foreach ($tables as $t) {
                $stmt = $pdo->query("SHOW TABLES LIKE '{$t}'");
                if ($stmt->fetch()) {
                    $pdo->exec("TRUNCATE TABLE `{$t}`");
                }
            }

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            
            // Set static connection instance so it is reused
            self::$pdo = $pdo;
        } catch (\Exception $e) {
            echo "Fatal Error initializing test database schemas: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    /**
     * Execute a raw MySQL prepared statement.
     *
     * @param string $sql    The SQL query statement with placeholders.
     * @param array  $params Optional bind parameters.
     * @return \PDOStatement The executed prepared statement.
     */
    public static function query($sql, $params = [])
    {
        $start = \microtime(true);
        
        $stmt = self::getPDO()->prepare($sql);
        $stmt->execute($params);
        
        $duration = \microtime(true) - $start;
        self::$totalQueryTime += $duration;
        self::$queryLog[] = [
            'sql' => $sql,
            'params' => $params,
            'duration' => $duration
        ];
        
        return $stmt;
    }

    /**
     * Cache an Active Record model instance inside the static identity map.
     *
     * @param string $table  The DB table name.
     * @param string $id     The primary UUID key.
     * @param mixed  $record The Active Record instance.
     * @return void
     */
    public static function setIdentity(string $table, string $id, $record)
    {
        // Limit the static cache size per table to 1000 items to prevent memory ballooning
        if (isset(self::$identityMap[$table]) && \count(self::$identityMap[$table]) >= 1000) {
            // Evict the oldest item in the cache (FIFO / LRU style)
            \array_shift(self::$identityMap[$table]);
        }
        self::$identityMap[$table][$id] = $record;
    }
}
