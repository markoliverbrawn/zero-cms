<?php

namespace Zero\Modules\Search\Drivers;

use Zero\Database\DB;
use Zero\Modules\Search\Interfaces\SearchDriverInterface;
use Zero\Support\Security;
use Zero\Support\Logger;

class DatabaseSearchDriver implements SearchDriverInterface
{
    /**
     * Clear all search index records for a specific site.
     */
    public function clear(string $siteId): void
    {
        try {
            DB::query("DELETE FROM search_index WHERE site_id = ?", [$siteId]);
        } catch (\Exception $e) {
            Logger::log(
                null,
                'search_index_clear_failed',
                'search',
                null,
                ['error' => $e->getMessage(), 'site_id' => $siteId]
            );
        }
    }

    /**
     * Delete an entry from the search index.
     */
    public function delete(string $modelType, string $modelId): void
    {
        try {
            DB::query("DELETE FROM search_index WHERE model_type = ? AND model_id = ?", [
                $modelType,
                $modelId
            ]);
        } catch (\Exception $e) {
            Logger::log(
                null,
                'search_index_delete_failed',
                'search',
                $modelId,
                ['error' => $e->getMessage(), 'model_type' => $modelType]
            );
        }
    }

    /**
     * Index or update a model's searchable fields.
     */
    public function index(
        string $siteId,
        string $modelType,
        string $modelId,
        string $title,
        string $content,
        string $url
    ): void {
        try {
            $id = Security::uuidv7();
            $now = gmdate('Y-m-d H:i:s');

            $sql = "
                INSERT INTO search_index (id, site_id, model_type, model_id, title, content, url, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    title = VALUES(title),
                    content = VALUES(content),
                    url = VALUES(url),
                    updated_at = VALUES(updated_at)
            ";

            DB::query($sql, [
                $id,
                $siteId,
                $modelType,
                $modelId,
                $title,
                $content,
                $url,
                $now,
                $now
            ]);
        } catch (\Exception $e) {
            Logger::log(
                null,
                'search_indexing_failed',
                'search',
                $modelId,
                ['error' => $e->getMessage(), 'model_type' => $modelType, 'site_id' => $siteId]
            );
        }
    }

    /**
     * Perform global search with title boosting and pagination.
     */
    public function search(string $query, array $options = []): array
    {
        $query = trim($query);
        if ($query === '') {
            return [
                'results' => [],
                'total' => 0
            ];
        }

        $siteId = \Zero\Core\App::getCurrentSiteId();
        $limit = isset($options['limit']) ? (int)$options['limit'] : 10;
        $offset = isset($options['offset']) ? (int)$options['offset'] : 0;

        try {
            // 1. Get total matching count
            $countSql = "
                SELECT COUNT(*) AS total 
                FROM search_index 
                WHERE site_id = ? 
                  AND (title LIKE ? OR content LIKE ?)
            ";
            $countParams = [
                $siteId,
                '%' . $query . '%',
                '%' . $query . '%'
            ];
            $stmt = DB::query($countSql, $countParams);
            $total = (int)($stmt->fetch()['total'] ?? 0);

            if ($total === 0) {
                return [
                    'results' => [],
                    'total' => 0
                ];
            }

            // 2. Fetch paginated search results with Title Hit Boosting
            $sql = "
                SELECT * FROM search_index 
                WHERE site_id = ? 
                  AND (title LIKE ? OR content LIKE ?)
                ORDER BY 
                  (CASE WHEN title LIKE ? THEN 1 ELSE 0 END) DESC,
                  (CASE WHEN title = ? THEN 1 ELSE 0 END) DESC,
                  updated_at DESC
                LIMIT {$limit} OFFSET {$offset}
            ";

            $params = [
                $siteId,
                '%' . $query . '%',
                '%' . $query . '%',
                '%' . $query . '%',
                $query
            ];

            $stmt = DB::query($sql, $params);
            $results = [];

            while ($row = $stmt->fetch()) {
                // Normalize result to retain type_label dynamically
                $typeLabel = 'Item';
                if ($row['model_type'] === 'page') {
                    $typeLabel = 'Page';
                } elseif ($row['model_type'] === 'post') {
                    $typeLabel = 'Blog Post';
                } elseif ($row['model_type'] === 'product') {
                    $typeLabel = 'Product';
                }

                $results[] = [
                    'id' => $row['model_id'],
                    'title' => $row['title'],
                    'content' => $row['content'],
                    'url' => $row['url'],
                    'type_label' => $typeLabel,
                    'model_type' => $row['model_type']
                ];
            }

            return [
                'results' => $results,
                'total' => $total
            ];
        } catch (\Exception $e) {
            Logger::log(
                null,
                'search_query_failed',
                'search',
                null,
                ['error' => $e->getMessage(), 'query' => $query]
            );

            return [
                'results' => [],
                'total' => 0
            ];
        }
    }
}
