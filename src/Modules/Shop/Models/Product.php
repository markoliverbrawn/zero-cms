<?php

namespace Zero\Modules\Shop\Models;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Interfaces\Model;
use Zero\Models\Media;
use Zero\Models\Traits\CascadesDeletes;
use Zero\Models\Traits\HasSlug;
use Zero\Models\Traits\IsModel;
use Zero\Models\Traits\Paginates;
use Zero\Modules\Shop\Models\ProductVariant;
use Zero\Support\I18n;
use Zero\Support\Security;

class Product implements Model
{
    use IsModel, HasSlug, Paginates, CascadesDeletes {
        CascadesDeletes::delete insteadof IsModel;
        CascadesDeletes::forceDelete insteadof IsModel;
        IsModel::delete as traitDelete;
        IsModel::forceDelete as traitForceDelete;
    }

    protected static $tableName = 'shop_products';
    protected static $modelType = 'product';
    protected static $fillable = ['category_id', 'title', 'slug', 'sku', 'description', 'price', 'compare_at_price', 'main_image', 'media_ids', 'status', 'exclude_from_search'];
    protected static array $cascadeDeletes = [
        ProductVariant::class => 'product_id'
    ];

    public $id;
    public $site_id;
    public $category_id;
    public $title;
    public $slug;
    public $sku;
    public $description;
    public $price;
    public $compare_at_price;
    public $main_image; // Dynamically contains path for views compatibility
    public $media_ids;
    public $status;
    public $exclude_from_search = 0;
    public $created_at;
    public $updated_at;

    // Eager-loading properties
    public $main_image_path;
    public $main_image_id;

    public function __construct($data = [])
    {
        // Hydrate all matching DB fields
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        // Custom Hydration Decoupling Logic:
        // If main_image_path was eager loaded, map it to main_image (so that views get the path)
        // and store the media ID in main_image_id.
        if (!empty($this->main_image_path)) {
            $this->main_image_id = $this->main_image;
            $this->main_image = $this->main_image_path;
        } else {
            // Otherwise, fetch it dynamically if main_image contains a 36-char media ID
            if (!empty($this->main_image) && strlen($this->main_image) === 36) {
                $this->main_image_id = $this->main_image;
                $media = Media::find($this->main_image);
                if ($media) {
                    $this->main_image_path = $media->path;
                    $this->main_image = $media->path;
                }
            }
        }
    }

    protected function createRecord()
    {
        if (empty($this->id)) {
            $this->id = Security::uuidv7();
        }

        $fields = ['id'];
        $placeholders = ['?'];
        $values = [$this->id];

        foreach (static::$fillable as $field) {
            if (isset($this->$field)) {
                $fields[] = $field;
                $placeholders[] = '?';
                $values[] = $this->$field;
            }
        }

        $fields[] = 'created_at';
        $placeholders[] = 'NOW()';
        $fields[] = 'updated_at';
        $placeholders[] = 'NOW()';

        $fields[] = 'site_id';
        $placeholders[] = '?';
        $values[] = $this->site_id ?? App::getCurrentSiteId();

        $sql = "INSERT INTO shop_products (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        DB::query($sql, $values);
        return $this->id;
    }

    public static function find($id)
    {
        $stmt = DB::query("
            SELECT shop_products.*, media.path AS main_image_path 
            FROM shop_products 
            LEFT JOIN media ON shop_products.main_image = media.id 
            WHERE shop_products.id = ? AND shop_products.deleted_at IS NULL
            LIMIT 1
        ", [$id]);
        $data = $stmt->fetch();
        if ($data) {
            return new static($data);
        }
        return null;
    }

    public static function findBySlug($slug)
    {
        $siteId = App::getCurrentSiteId();
        $stmt = DB::query("
            SELECT shop_products.*, media.path AS main_image_path 
            FROM shop_products 
            LEFT JOIN media ON shop_products.main_image = media.id 
            WHERE shop_products.slug = ? AND shop_products.site_id = ? AND shop_products.deleted_at IS NULL
            LIMIT 1
        ", [$slug, $siteId]);
        $data = $stmt->fetch();
        if ($data) {
            return new static($data);
        }
        return null;
    }

    /**
     * Retrieve the associated category.
     */
    public function getCategory()
    {
        return $this->category_id ? Category::find($this->category_id) : null;
    }

    public static function getConfig(): array
    {
        $categories = [];
        if (class_exists(Category::class)) {
            $allCats = Category::all();
            foreach ($allCats as $cat) {
                $categories[$cat->id] = $cat->title;
            }
        }

        return [
            'id' => ['type' => 'text', 'label' => 'ID', 'editable' => false, 'listDisplay' => false],
            'title' => ['type' => 'text', 'label' => I18n::t('product_name'), 'editable' => true, 'required' => true, 'listDisplay' => true, 'searchable' => true, 'width' => 'full'],
            'slug' => ['type' => 'text', 'label' => 'Slug', 'editable' => false, 'listDisplay' => true],
            'category_id' => [
                'type' => 'select', 
                'label' => I18n::t('category'), 
                'options' => $categories, 
                'editable' => true, 
                'required' => false, 
                'listDisplay' => false,
                'width' => 'half'
            ],
            'sku' => ['type' => 'text', 'label' => I18n::t('base_sku'), 'editable' => true, 'required' => false, 'listDisplay' => true, 'width' => 'half'],
            'price' => ['type' => 'number', 'label' => 'Price', 'editable' => true, 'required' => true, 'listDisplay' => true, 'width' => 'half'],
            'compare_at_price' => ['type' => 'number', 'label' => I18n::t('compare_at_price'), 'editable' => true, 'required' => false, 'listDisplay' => false, 'width' => 'half'],
            'main_image' => ['type' => 'image', 'label' => I18n::t('primary_image_path'), 'editable' => true, 'required' => false, 'listDisplay' => true, 'section' => 'side', 'width' => 'full'],
            'media_ids' => ['type' => 'text', 'label' => 'Media IDs (comma separated)', 'editable' => true, 'required' => false, 'listDisplay' => false, 'section' => 'side', 'width' => 'full'],
            'description' => ['type' => 'textarea', 'label' => I18n::t('rich_description'), 'editable' => true, 'required' => false, 'listDisplay' => false, 'width' => 'full'],
            'status' => [
                'type' => 'select',
                'label' => 'Status',
                'options' => [
                    'published' => 'Published',
                    'draft' => 'Draft',
                    'archived' => 'Archived'
                ],
                'editable' => true,
                'listDisplay' => true,
                'listView' => 'fields/status',
                'required' => true,
                'section' => 'side',
                'width' => 'full'
            ],
            'exclude_from_search' => [
                'type' => 'select',
                'label' => 'Exclude from Search',
                'options' => [
                    1 => 'Yes',
                    0 => 'No'
                ],
                'editable' => true,
                'listDisplay' => false,
                'required' => true,
                'section' => 'side',
                'width' => 'full'
            ]
        ];
    }

    /**
     * Resolves the public frontend routing URL for this product record.
     * Keeps method sorting alphabetically correct (getConfig -> getFrontendUrl).
     */
    public function getFrontendUrl(): string
    {
        $slug = $this->slug ?? '';
        return '/shop/product/' . ltrim($slug, '/');
    }

    /**
     * Retrieve all secondary gallery images associated with this product.
     */
    public function getSecondaryImages(): array
    {
        if (empty($this->media_ids)) {
            return [];
        }
        $ids = array_filter(array_map('trim', explode(',', $this->media_ids)));
        if (empty($ids)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return DB::query("
            SELECT * FROM media 
            WHERE id IN ($placeholders)
        ", $ids)->fetchAll();
    }

    /**
     * Retrieve all variants for this product.
     */
    public function getVariants(): array
    {
        return ProductVariant::where('product_id', $this->id);
    }

    /**
     * Eager-Loaded Paginated Query mapping (Exactly ONE query executed to include image details)
     */
    public static function paginate($page = 1, $perPage = 10, $filters = [], $orderBy = 'created_at DESC')
    {
        $siteId = App::getCurrentSiteId();
        $params = [$siteId];
        
        $deletedSql = !empty($filters['trash']) ? "shop_products.deleted_at IS NOT NULL" : "shop_products.deleted_at IS NULL";
        $where = "WHERE shop_products.site_id = ? AND {$deletedSql}";

        if (!empty($filters['q'])) {
            $where .= " AND (shop_products.title LIKE ? OR shop_products.sku LIKE ?)";
            $params[] = '%' . $filters['q'] . '%';
            $params[] = '%' . $filters['q'] . '%';
        }

        // Apply custom alias mapping for ordering
        if (strpos($orderBy, 'created_at') === 0) {
            $orderBy = 'shop_products.created_at ' . (str_ends_with($orderBy, 'DESC') ? 'DESC' : 'ASC');
        } elseif (strpos($orderBy, 'title') === 0) {
            $orderBy = 'shop_products.title ' . (str_ends_with($orderBy, 'DESC') ? 'DESC' : 'ASC');
        } elseif (strpos($orderBy, 'price') === 0) {
            $orderBy = 'shop_products.price ' . (str_ends_with($orderBy, 'DESC') ? 'DESC' : 'ASC');
        } elseif (strpos($orderBy, 'status') === 0) {
            $orderBy = 'shop_products.status ' . (str_ends_with($orderBy, 'DESC') ? 'DESC' : 'ASC');
        } elseif (strpos($orderBy, 'sku') === 0) {
            $orderBy = 'shop_products.sku ' . (str_ends_with($orderBy, 'DESC') ? 'DESC' : 'ASC');
        }

        // Total count
        $totalStmt = DB::query("SELECT COUNT(*) as cnt FROM shop_products $where", $params);
        $totalCount = intval($totalStmt->fetch()['cnt'] ?? 0);

        $pages = max(1, ceil($totalCount / $perPage));
        $offset = ($page - 1) * $perPage;

        // Fetch paginated data with EAGER LOAD main_image_path in exactly ONE query!
        $sql = "
            SELECT shop_products.*, media.path AS main_image_path 
            FROM shop_products 
            LEFT JOIN media ON shop_products.main_image = media.id 
            $where 
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
            'perPage' => $perPage,
            'totalPages' => $pages,
            'totalCount' => $totalCount,
            'query' => $filters['q'] ?? '',
        ];
    }

    public function save()
    {
        // Swap main_image path with media ID for DB storage
        $originalMainImage = $this->main_image;
        if (!empty($this->main_image_id)) {
            $this->main_image = $this->main_image_id;
        }

        if ($this->id) {
            $exists = DB::query("SELECT id FROM shop_products WHERE id = ? LIMIT 1", [$this->id])->fetch();
            if ($exists) {
                $result = $this->updateRecord();
            } else {
                $result = $this->createRecord();
            }
        } else {
            $result = $this->createRecord();
        }

        // Restore path for runtime view templates
        $this->main_image = $originalMainImage;
        return $result;
    }

    protected function updateRecord()
    {
        $set = [];
        $values = [];

        foreach (static::$fillable as $field) {
            if (isset($this->$field)) {
                $set[] = "$field = ?";
                $values[] = $this->$field;
            }
        }

        $set[] = "updated_at = NOW()";
        $values[] = $this->id;

        $sql = "UPDATE shop_products SET " . implode(', ', $set) . " WHERE id = ?";
        DB::query($sql, $values);
        return $this->id;
    }
}
