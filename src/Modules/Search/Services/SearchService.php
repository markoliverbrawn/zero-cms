<?php

declare(strict_types=1);

/**
 * File: src/Modules/Search/Services/SearchService.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Search\Services
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Search\Services;

use Zero\Core\App;
use Zero\Core\Env;
use Zero\Database\DB;
use Zero\Modules\Search\Drivers\DatabaseSearchDriver;
use Zero\Modules\Search\Interfaces\SearchDriverInterface;

/**
 * Class SearchService
 *
 * Front door to search. Holds the registry of searchable models, resolves the configured driver,
 * and exposes indexing, deletion, whole-index rebuilds, and querying, so callers depend on this
 * rather than on a particular driver.
 */
class SearchService
{
    protected static ?SearchDriverInterface $driver = null;
    protected static array $searchables = [];

    /**
     * Clear all search index entries for a specific site.
     *
     * @param string $siteId
     * @return void
     */
    public static function clear(string $siteId): void
    {
        self::getDriver()->clear($siteId);
    }

    /**
     * Delete an entry from the search index.
     *
     * @param string $modelType
     * @param string $modelId
     * @return void
     */
    public static function delete(string $modelType, string $modelId): void
    {
        self::getDriver()->delete($modelType, $modelId);
    }

    /**
     * Dynamically resolve and cache the active search driver.
     *
     * @return SearchDriverInterface
     */
    public static function getDriver(): SearchDriverInterface
    {
        if (self::$driver === null) {
            $driverName = Env::get('SEARCH_DRIVER', 'database');
            if ($driverName === 'database') {
                self::$driver = new DatabaseSearchDriver();
            } else {
                throw new \Exception("Unsupported search driver configuration: " . $driverName);
            }
        }
        return self::$driver;
    }

    /**
     * Get all registered searchable models/providers (Retained for backward compatibility).
     *
     * @return array
     */
    public static function getSearchables(): array
    {
        return self::$searchables;
    }

    /**
     * Index a model's searchable fields.
     *
     * @param string $siteId
     * @param string $modelType
     * @param string $modelId
     * @param string $title
     * @param string $content
     * @param string $url
     * @return void
     */
    public static function index(
        string $siteId,
        string $modelType,
        string $modelId,
        string $title,
        string $content,
        string $url
    ): void {
        self::getDriver()->index($siteId, $modelType, $modelId, $title, $content, $url);
    }

    /**
     * Register a model to be included in search (Retained for backward compatibility).
     *
     * @param string $modelClass The fully qualified class name of the Model.
     * @param array $config Configuration options
     * @return void
     */
    public static function register(string $modelClass, array $config = []): void
    {
        self::$searchables[$modelClass] = \array_merge([
            'type_label' => 'Item',
            'search_fields' => ['title', 'content'],
            'title_field' => 'title',
            'content_field' => 'content',
            'status_field' => 'status',
            'route_prefix' => '/'
        ], $config);
    }

    /**
     * Clear and bulk re-index all registered searchable models dynamically.
     * Loops through all registered models and indexed their active rows.
     *
     * @return array Array containing the counts of successfully indexed items by model.
     */
    public static function reindexAll(): array
    {
        $siteId = App::getCurrentSiteId();
        self::clear($siteId);

        $counts = [];
        foreach (self::getSearchables() as $modelClass => $config) {
            if (\class_exists($modelClass)) {
                $tableName = $modelClass::getTableName();
                $rows = DB::query("SELECT * FROM {$tableName} WHERE site_id = ? AND deleted_at IS NULL", [$siteId])->fetchAll();
                
                $indexed = 0;
                foreach ($rows as $row) {
                    $model = new $modelClass($row);
                    $model->indexInSearch();
                    $indexed++;
                }
                $counts[$modelClass] = $indexed;
            }
        }
        return $counts;
    }

    /**
     * Perform global search over the active index driver.
     * Returns an array with results and total count.
     *
     * @param string $query
     * @param array $options
     * @return array
     */
    public static function search(string $query, array $options = []): array
    {
        return self::getDriver()->search($query, $options);
    }
}
