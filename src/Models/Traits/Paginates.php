<?php

declare(strict_types=1);

/**
 * File: src/Models/Traits/Paginates.php
 * Architectural Purpose: Active Record data model or behavioral trait wrapping database schema representation with tenant-scoping.
 * Package: Zero\Models\Traits
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Models\Traits;

use Zero\Core\App;
use Zero\Database\DB;

/**
 * Trait Paginates
 *
 * Defines systemic behavioral interface contract mechanisms.
 */
trait Paginates
{
    /**
     * Generalised pagination for any model.
     *
     * @param int $page Current page number
     * @param int $perPage Items per page
     * @param array $filters Query search filters
     * @param string $orderBy SQL order clause (default: 'created_at DESC')
     * @return array
     */
    public static function paginate($page = 1, $perPage = 10, $filters = [], $orderBy = 'created_at DESC')
    {
        // Defensive whitelisting and sanitization of the ORDER BY clause
        $orderByParts = \explode(' ', \trim($orderBy));
        $cleanOrderBy = 'created_at DESC'; // Fallback default

        if (!empty($orderByParts)) {
            $column = $orderByParts[0];
            $direction = isset($orderByParts[1]) ? \strtoupper($orderByParts[1]) : 'ASC';

            // Ensure column contains ONLY standard letters, numbers, underscores, and dots (e.g. table.col)
            if (\preg_match('/^[a-zA-Z0-9_\.]+$/', $column)) {
                // Ensure direction is strictly ASC or DESC
                if ($direction !== 'ASC' && $direction !== 'DESC') {
                    $direction = 'ASC';
                }
                $cleanOrderBy = "{$column} {$direction}";
            }
        }
        $orderBy = $cleanOrderBy;

        $where = [];
        $params = [];

        // Multi-tenant isolation filter (exclude sites table)
        $tableName = static::$tableName ?? \strtolower((new \ReflectionClass(static::class))->getShortName()) . 's';
        if ($tableName !== 'sites') {
            if ($tableName === 'users') {
                // Show users belonging to this site AND global super-admins (site_id IS NULL)
                $where[] = "(site_id = ? OR site_id IS NULL)";
                $params[] = App::getCurrentSiteId();
            } else {
                $where[] = "site_id = ?";
                $params[] = App::getCurrentSiteId();
            }
        }

        // Soft delete generic exclusion filter!
        if (!empty($filters['trash'])) {
            $where[] = "deleted_at IS NOT NULL";
        } else {
            $where[] = "deleted_at IS NULL";
        }

        // Restrict guests to view 'published' elements only for pages and posts
        $isAdmin = isset($_SESSION['user_id']);
        if (($tableName === 'pages' || $tableName === 'posts') && !$isAdmin) {
            $where[] = "status = 'published'";
        }

        if (isset($filters['q']) && !empty($filters['q'])) {
            $config = \method_exists(static::class, 'getConfig') ? static::getConfig() : [];
            $searchWhere = [];
            foreach ($config as $fieldname => $value) {
                if ($value['searchable'] ?? false) {
                    $searchWhere[] = "{$fieldname} LIKE ?";
                    $params[] = '%' . $filters['q'] . '%';
                }
            }
            if ($searchWhere) {
                $where[] = '(' . \implode(' OR ', $searchWhere) . ')';
            }
        }

        $whereSql = '';
        if ($where) {
            $whereSql = 'WHERE ' . \implode(' AND ', $where);
        }

        // Total count
        $totalStmt = DB::query("SELECT COUNT(*) as cnt FROM {$tableName} {$whereSql}", $params);
        $total = $totalStmt->fetch();
        $totalCount = $total ? \intval($total['cnt']) : 0;

        $pages = \max(1, \ceil($totalCount / $perPage));
        $offset = ($page - 1) * $perPage;

        // Fetch paginated data
        $sql = "SELECT * FROM {$tableName} {$whereSql} ORDER BY {$orderBy} LIMIT $perPage OFFSET $offset";
        $stmt = DB::query($sql, $params);
        $results = [];
        while ($data = $stmt->fetch()) {
            $results[] = new static($data);
        }
        return [
            'data' => $results,
            'currentPage' => $page,
            'perPage' => $perPage,
            'totalPages' => $pages,
            'totalCount' => $totalCount,
            'query' => $filters['q'] ?? '',
        ];
    }
}
