<?php

declare(strict_types=1);

/**
 * File: src/Modules/Shop/Models/Category.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Shop\Models
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Shop\Models;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Interfaces\Model;
use Zero\Models\Media;
use Zero\Models\Traits\HasSlug;
use Zero\Models\Traits\IsModel;

/**
 * Class Category
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class Category implements Model
{
    use IsModel, HasSlug;

    protected static $tableName = 'shop_categories';
    protected static $modelType = 'category';
    protected static $fillable = ['site_id', 'title', 'slug', 'description', 'image'];

    public $id;
    public $site_id;
    public $title;
    public $slug;
    public $description;
    public $image; // DB value (storing media ID or path) and dynamically mapped path
    public $image_id;
    public $image_path;
    public $created_at;
    public $updated_at;

    /**
     * __construct processing implementation helper.
     *
     * @param mixed $data Argument descriptor.
     * @return mixed Response output.
     */
    public function __construct($data = [])
    {
        // Hydrate all matching DB fields
        foreach ($data as $key => $value) {
            if (\property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        // Custom hydration logic: resolve the image path if storing a UUID media ID
        if (!empty($this->image_path)) {
            $this->image_id = $this->image;
            $this->image = $this->image_path;
        } elseif (!empty($this->image) && \strlen($this->image) === 36) {
            $this->image_id = $this->image;
            $media = Media::find($this->image);
            if ($media) {
                $this->image_path = $media->path;
                $this->image = $media->path;
            }
        }
    }

    /**
     * Override IsModel::all() to eager-load category image paths in a single query,
     * preventing N+1 database queries on category listing loops.
     */
    public static function all(): array
    {
        $siteId = App::getCurrentSiteId();
        $sql = "
            SELECT shop_categories.*, media.path AS image_path 
            FROM shop_categories 
            LEFT JOIN media ON shop_categories.image = media.id 
            WHERE shop_categories.site_id = ? AND shop_categories.deleted_at IS NULL
            ORDER BY shop_categories.title ASC
        ";
        $stmt = DB::query($sql, [$siteId]);
        $results = [];
        while ($data = $stmt->fetch()) {
            $results[] = new static($data);
        }
        return $results;
    }

    /**
     * Override HasSlug::findBySlug() to eager-load category image path in a single query,
     * preventing N+1 database queries on individual category resolutions.
     */
    public static function findBySlug($slug)
    {
        $siteId = App::getCurrentSiteId();
        $stmt = DB::query("
            SELECT shop_categories.*, media.path AS image_path 
            FROM shop_categories 
            LEFT JOIN media ON shop_categories.image = media.id 
            WHERE shop_categories.slug = ? AND shop_categories.site_id = ? AND shop_categories.deleted_at IS NULL
            LIMIT 1
        ", [$slug, $siteId]);
        $data = $stmt->fetch();
        if ($data) {
            return new static($data);
        }
        return null;
    }

    /**
     * Retrieves the config attribute value.
     *
     * @return mixed Response output.
     */
    public static function getConfig(): array
    {
        return [
            'id' => ['type' => 'text', 'label' => 'ID', 'editable' => false, 'listDisplay' => false],
            'title' => ['type' => 'text', 'label' => 'Category Name', 'editable' => true, 'required' => true, 'listDisplay' => true, 'searchable' => true],
            'slug' => ['type' => 'text', 'label' => 'Slug', 'editable' => false, 'listDisplay' => true],
            'description' => ['type' => 'textarea', 'label' => 'Description', 'editable' => true, 'listDisplay' => true],
            'image' => [
                'type' => 'image',
                'label' => 'Category Image',
                'editable' => true,
                'required' => false,
                'listDisplay' => true,
                'listView' => 'fields/favicon' // Renders it dynamically inside table previews!
            ],
            'created_at' => ['type' => 'datetime', 'label' => 'Created At', 'editable' => false, 'listDisplay' => true]
        ];
    }

    /**
     * Custom Category pagination to eagerly load image_path via JOIN, preventing N+1 queries in listings.
     */
    public static function paginate($page = 1, $perPage = 10, $filters = [], $orderBy = 'created_at DESC')
    {
        $siteId = App::getCurrentSiteId();
        
        // Defensive whitelisting and column mapping of the ORDER BY clause
        $orderByParts = \explode(' ', \trim($orderBy));
        $cleanOrderBy = 'shop_categories.created_at DESC'; // Fallback default

        if (!empty($orderByParts)) {
            $column = $orderByParts[0];
            $direction = isset($orderByParts[1]) ? \strtoupper($orderByParts[1]) : 'ASC';

            if ($column === 'image_path') {
                $column = 'image_path';
            } elseif (\strpos($column, 'shop_categories.') !== 0 && $column !== 'image_path') {
                $column = 'shop_categories.' . $column;
            }

            if (\preg_match('/^[a-zA-Z0-9_\.]+$/', $column)) {
                if ($direction !== 'ASC' && $direction !== 'DESC') {
                    $direction = 'ASC';
                }
                $cleanOrderBy = "{$column} {$direction}";
            }
        }
        $orderBy = $cleanOrderBy;

        $where = ["shop_categories.site_id = ?"];
        $params = [$siteId];

        // Soft delete generic exclusion filter!
        if (!empty($filters['trash'])) {
            $where[] = "shop_categories.deleted_at IS NOT NULL";
        } else {
            $where[] = "shop_categories.deleted_at IS NULL";
        }

        if (isset($filters['q']) && !empty($filters['q'])) {
            $where[] = "(shop_categories.title LIKE ? OR shop_categories.description LIKE ?)";
            $qParam = '%' . $filters['q'] . '%';
            $params[] = $qParam;
            $params[] = $qParam;
        }

        $whereSql = 'WHERE ' . \implode(' AND ', $where);

        // Total count
        $totalStmt = DB::query("SELECT COUNT(*) as cnt FROM shop_categories {$whereSql}", $params);
        $total = $totalStmt->fetch();
        $totalCount = $total ? \intval($total['cnt']) : 0;

        $pages = \max(1, \ceil($totalCount / $perPage));
        $offset = ($page - 1) * $perPage;

        // Fetch paginated data with EAGER LOAD image_path in exactly ONE query!
        $sql = "
            SELECT shop_categories.*, media.path AS image_path
            FROM shop_categories 
            LEFT JOIN media ON shop_categories.image = media.id
            {$whereSql} 
            ORDER BY {$orderBy} 
            LIMIT $perPage OFFSET $offset
        ";
        $stmt = DB::query($sql, $params);
        $results = [];
        while ($data = $stmt->fetch()) {
            $results[] = new static($data);
        }

        return [
            'data' => $results,
            'currentPage' => $page,
            'totalPages' => $pages,
            'totalItems' => $totalCount,
            'query' => $filters['q'] ?? ''
        ];
    }

    /**
     * Override IsModel::where() to eager-load category image paths in a single query,
     * preventing N+1 database queries on custom filtered category searches.
     */
    public static function where(string $column, $value, string $options = ''): array
    {
        $siteId = App::getCurrentSiteId();
        
        // Handle table prefix dynamically for columns
        $columnSql = (\strpos($column, '.') === false) ? "shop_categories.{$column}" : $column;
        $sql = "
            SELECT shop_categories.*, media.path AS image_path 
            FROM shop_categories 
            LEFT JOIN media ON shop_categories.image = media.id 
            WHERE {$columnSql} = ? AND shop_categories.site_id = ? AND shop_categories.deleted_at IS NULL
        ";
        $params = [$value, $siteId];

        if (!empty($options)) {
            $options = \str_replace('ORDER BY ', 'ORDER BY shop_categories.', $options);
            $sql .= " " . $options;
        }

        $stmt = DB::query($sql, $params);
        $results = [];
        while ($data = $stmt->fetch()) {
            $results[] = new static($data);
        }
        return $results;
    }
}
