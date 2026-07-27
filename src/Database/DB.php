<?php

namespace Zero\Database;

use Exception;
use PDO;
use PDOException;
use Zero\Core\Env;
use Zero\Support\Logger;

class DB
{
    protected static $pdo = null;
    protected static $queryLog = [];
    protected static $totalQueryTime = 0;
    protected static $columnCache = [];
    protected static $identityMap = [];

    /**
     * Clear the static column schema cache completely.
     */
    public static function clearColumnCache(): void
    {
        self::$columnCache = [];
    }

    /**
     * Clear the static identity map cache completely (Garbage Collection).
     */
    public static function clearIdentityMap(): void
    {
        self::$identityMap = [];
    }

    public static function getIdentity(string $table, string $id)
    {
        return self::$identityMap[$table][$id] ?? null;
    }

    /**
     * Get the active PDO MySQL database connection.
     */
    public static function getPDO()
    {
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
            error_log("Database connection failed: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            
            if (php_sapi_name() === 'cli') {
                throw $e; // In CLI, throw the exception so calling scripts can catch it and terminate with clean exit codes!
            }
            
            http_response_code(500);
            echo "A database error occurred. Please try again later.";
            exit;
        }
    }

    public static function getQueryCount(): int
    {
        return count(self::$queryLog);
    }

    public static function getQueryLog(): array
    {
        return self::$queryLog;
    }

    public static function getTotalQueryTime(): float
    {
        return self::$totalQueryTime;
    }

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
     * Execute a raw MySQL prepared statement.
     */
    public static function query($sql, $params = [])
    {
        $start = microtime(true);
        
        $stmt = self::getPDO()->prepare($sql);
        $stmt->execute($params);
        
        $duration = microtime(true) - $start;
        self::$totalQueryTime += $duration;
        self::$queryLog[] = [
            'sql' => $sql,
            'params' => $params,
            'duration' => $duration
        ];
        
        return $stmt;
    }

    public static function setIdentity(string $table, string $id, $record)
    {
        // Limit the static cache size per table to 1000 items to prevent memory ballooning
        if (isset(self::$identityMap[$table]) && count(self::$identityMap[$table]) >= 1000) {
            // Evict the oldest item in the cache (FIFO / LRU style)
            array_shift(self::$identityMap[$table]);
        }
        self::$identityMap[$table][$id] = $record;
    }
}
