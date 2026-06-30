<?php

namespace Zero\Modules\Search;

use Zero\Core\App;
use Zero\Database\DB;

class SearchService
{
    protected static array $searchables = [];

    /**
     * Get all registered searchable models/providers.
     *
     * @return array
     */
    public static function getSearchables(): array
    {
        return self::$searchables;
    }

    /**
     * Register a model to be included in the global site search.
     *
     * @param string $modelClass The fully qualified class name of the Model.
     * @param array $config Configuration options (type_label, search_fields, title_field, content_field, status_field, route_prefix)
     * @return void
     */
    public static function register(string $modelClass, array $config = []): void
    {
        self::$searchables[$modelClass] = array_merge([
            'type_label' => 'Item',
            'search_fields' => ['title', 'content'],
            'title_field' => 'title',
            'content_field' => 'content',
            'status_field' => 'status',
            'route_prefix' => '/'
        ], $config);
    }

    /**
     * Perform the global search across all registered searchables.
     *
     * @param string $query The search query string.
     * @return array Array of normalized search result arrays.
     */
    public static function search(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $siteId = App::getCurrentSiteId();
        $results = [];

        foreach (self::$searchables as $modelClass => $config) {
            if (!class_exists($modelClass)) {
                continue;
            }

            // Check if active site has this module enabled
            // Pages is core, other models are modular. If module is not enabled for this site, skip it!
            $isModuleEnabled = true;
            if (str_contains($modelClass, 'Zero\Modules\\')) {
                // Extract module name from namespace, e.g., Zero\Modules\Shop\Models\Product -> 'shop'
                preg_match('/Zero\\\\Modules\\\\([a-zA-Z0-9]+)/', $modelClass, $matches);
                if (!empty($matches[1])) {
                    $moduleName = strtolower($matches[1]);
                    // Let's resolve correct module ID to match against site enabled_modules (which has 'shop' or 'blog')
                    $moduleId = $moduleName;
                    if ($moduleId === 'search') {
                        $moduleId = 'site-search';
                    }
                    $site = App::getCurrentSite();
                    if ($site) {
                        $enabled = json_decode($site->enabled_modules ?? '[]', true);
                        if (is_array($enabled)) {
                            $isModuleEnabled = in_array($moduleId, $enabled);
                        }
                    }
                }
            }
            if (!$isModuleEnabled) {
                continue;
            }

            // Build search SQL dynamically to be extremely decoupled and clean!
            $tableName = null;
            if (property_exists($modelClass, 'tableName')) {
                $ref = new \ReflectionClass($modelClass);
                $props = $ref->getStaticProperties();
                $tableName = $props['tableName'] ?? null;
            }

            if (!$tableName) {
                continue;
            }

            $searchFields = $config['search_fields'];
            $titleField = $config['title_field'];
            $contentField = $config['content_field'];
            $statusField = $config['status_field'];

            // We only search published items!
            $where = [
                "deleted_at IS NULL",
                "site_id = ?"
            ];
            $params = [$siteId];

            if ($statusField) {
                $where[] = "{$statusField} = 'published'";
            }

            // Check for exclude_from_search column
            $fillable = [];
            $ref = new \ReflectionClass($modelClass);
            $props = $ref->getStaticProperties();
            $fillable = $props['fillable'] ?? [];
            if (in_array('exclude_from_search', $fillable)) {
                $where[] = "exclude_from_search = 0";
            }

            // Build dynamic search condition
            $searchConditions = [];
            foreach ($searchFields as $field) {
                $searchConditions[] = "{$field} LIKE ?";
                $params[] = '%' . $query . '%';
            }

            if (!empty($searchConditions)) {
                $where[] = "(" . implode(' OR ', $searchConditions) . ")";
            }

            $sql = "SELECT * FROM {$tableName} WHERE " . implode(' AND ', $where);
            
            try {
                $stmt = DB::query($sql, $params);
                while ($row = $stmt->fetch()) {
                    $item = new $modelClass($row);
                    // Double check tenant matching (extra layer of security)
                    if (isset($item->site_id) && $item->site_id !== $siteId) {
                        continue;
                    }

                    // Normalize result
                    $results[] = [
                        'id' => $item->id,
                        'title' => $item->$titleField ?? '',
                        'content' => $item->$contentField ?? '',
                        'url' => method_exists($item, 'getFrontendUrl') ? $item->getFrontendUrl() : $config['route_prefix'] . ($item->slug ?? ''),
                        'type_label' => $config['type_label'],
                        'slug' => $item->slug ?? ''
                    ];
                }
            } catch (\Exception $e) {
                \Zero\Support\Logger::log(
                    null,
                    'search_failed',
                    $modelClass,
                    null,
                    ['error' => $e->getMessage()]
                );
            }
        }

        return $results;
    }
}
