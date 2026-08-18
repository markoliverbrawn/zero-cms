<?php

declare(strict_types=1);

/**
 * File: src/Modules/Blog/Models/Post.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Blog\Models
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Blog\Models;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Models\Page;
use Zero\Models\Traits\CascadesDeletes;
use Zero\Models\Traits\HasFeaturedImage;
use Zero\Models\Traits\IsModel;
use Zero\Modules\Blog\Models\Comment;
use Zero\Modules\Search\Traits\Searchable;
use Zero\Support\I18n;

/**
 * Class Post
 *
 * Active Record model for a blog article. Extends Page, so a post inherits slug addressing,
 * block-builder content, and tenant scoping, and adds the article-specific listing, ordering, and
 * frontend URL behaviour.
 */
class Post extends Page
{
    use HasFeaturedImage, IsModel, CascadesDeletes, Searchable {
        CascadesDeletes::delete insteadof IsModel;
        CascadesDeletes::forceDelete insteadof IsModel;
        IsModel::delete as traitDelete;
        IsModel::forceDelete as traitForceDelete;
    }

    protected static $tableName = 'blog_posts';
    protected static $modelType = 'post'; // Define type for Post model
    protected static $fillable = ['title', 'summary', 'slug', 'content', 'status', 'allow_comments', 'comment_notifiers', 'featured_image', 'exclude_from_search'];
    protected static array $cascadeDeletes = [
        Comment::class => 'post_id'
    ];

    public $comment_count = 0;
    public $allow_comments = 1;
    public $comment_notifiers;
    public $summary;
    public $featured_image;
    public $updated_at;
    public $deleted_at;

    /**
     * __construct processing implementation helper.
     *
     * @param mixed $data Argument descriptor.
     * @return mixed Response output.
     */
    public function __construct($data = [])
    {
        parent::__construct($data);
        if (!isset($data['comment_count']) && !empty($this->id)) {
            $this->comment_count = (int)DB::query("
                SELECT COUNT(*) FROM blog_comments 
                WHERE post_id = ? AND status = 'approved' AND deleted_at IS NULL
            ", [$this->id])->fetchColumn();
        }

        // Resolves the media ID of the featured image using our secure core trait
        $this->resolveFeaturedImage();
    }

    /**
     * Override all() to eagerly load comment_count and featured_image_path in a single combined query.
     * Prevents N+1 database queries on post lists, satisfying strict Section 18 eager loading guidelines.
     */
    public static function all(): array
    {
        $siteId = App::getCurrentSiteId();
        
        $whereSql = "WHERE blog_posts.site_id = ? AND blog_posts.deleted_at IS NULL";
        $params = [$siteId];

        $sql = "
            SELECT blog_posts.*, 
                   COUNT(blog_comments.id) AS comment_count,
                   media.path AS featured_image_path
            FROM blog_posts 
            LEFT JOIN blog_comments ON blog_comments.post_id = blog_posts.id 
                                   AND blog_comments.status = 'approved' 
                                   AND blog_comments.deleted_at IS NULL
            LEFT JOIN media ON media.id = blog_posts.featured_image
            {$whereSql} 
            GROUP BY blog_posts.id, media.path
            ORDER BY blog_posts.created_at DESC
        ";

        try {
            $stmt = DB::query($sql, $params);
            $results = [];
            while ($data = $stmt->fetch()) {
                $results[] = new static($data);
            }
            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Retrieves the config attribute value.
     *
     * @return mixed Response output.
     */
    public static function getConfig(): array
    {
        $config = parent::getConfig();
        // Remove fields that are page-specific and don't exist in blog_posts schema
        unset($config['controller']);
        unset($config['view']);
        unset($config['precedence']);
        unset($config['parent_path']);

        // Configure slug_part as the primary flat slug editor for blog posts
        $config['slug_part'] = [
            'type' => 'text', 
            'label' => I18n::t('slug'), 
            'editable' => true, 
            'listDisplay' => false,
            'helper_text' => 'The URL-friendly post path. If left blank, it is automatically generated from the title.'
        ];

        // Add summary field
        $config['summary'] = [
            'type' => 'textarea', 
            'label' => 'Summary', 
            'editable' => true, 
            'required' => true, 
            'listDisplay' => false,
            'helper_text' => 'A short, single-paragraph summary used in lists and blocks.'
        ];

        // Add featured image media selector field, mapped as secure 'image' type
        $config['featured_image'] = [
            'type' => 'image', 
            'label' => 'Featured Image', 
            'editable' => true, 
            'required' => false, 
            'listDisplay' => true,
            'helper_text' => 'The primary display thumbnail or hero image.'
        ];

        // Add distinct column for Comments count lookup
        $config['comment_count'] = ['type' => 'int', 'label' => 'Comments', 'editable' => false, 'listDisplay' => true];

        // Add Allow Comments toggle select dropdown field
        $config['allow_comments'] = [
            'type' => 'select', 
            'label' => 'Allow Comments', 
            'options' => [1 => 'Yes', 0 => 'No'], 
            'editable' => true, 
            'required' => true, 
            'listDisplay' => false
        ];

        // Fetch valid site admin/super-admin notification users
        $siteId = App::getCurrentSiteId();
        $notifiersOptions = [];
        try {
            $notifiers = DB::query("
                SELECT id, username, email FROM users 
                WHERE (site_id = ? OR role = 'super_admin') AND deleted_at IS NULL
            ", [$siteId])->fetchAll();
            foreach ($notifiers as $u) {
                $notifiersOptions[$u['id']] = $u['username'] . ' (' . $u['email'] . ')';
            }
        } catch (\Exception $e) {
            // Fallback if query fails (e.g. during migrations setup)
        }

        $config['comment_notifiers'] = [
            'type' => 'select', 
            'label' => 'Comment Notifiers', 
            'options' => $notifiersOptions, 
            'multiple' => true, 
            'editable' => true, 
            'required' => false, 
            'listDisplay' => false
        ];

        return $config;
    }

    /**
     * Resolves the public frontend routing URL for this blog post record.
     * Keeps method sorting alphabetically correct (getConfig -> getFrontendUrl).
     */
    public function getFrontendUrl(): string
    {
        $slug = $this->slug ?? '';
        return '/post/' . \ltrim($slug, '/');
    }

    /**
     * Disable orderable behavior for blog posts as they don't have precedence support in the schema.
     */
    public static function isOrderable(): bool
    {
        return false;
    }

    /**
     * Custom pagination for Posts to support dynamic sorting by virtual comment_count column via subquery.
     */
    public static function paginate($page = 1, $perPage = 10, $filters = [], $orderBy = 'created_at DESC')
    {
        $siteId = App::getCurrentSiteId();
        
        // Defensive whitelisting and column mapping of the ORDER BY clause
        $orderByParts = \explode(' ', \trim($orderBy));
        $cleanOrderBy = 'blog_posts.created_at DESC'; // Fallback

        if (!empty($orderByParts)) {
            $column = $orderByParts[0];
            $direction = isset($orderByParts[1]) ? \strtoupper($orderByParts[1]) : 'ASC';

            if ($column === 'comment_count') {
                $column = 'comment_count'; // Matches our SQL virtual column alias!
            } elseif (\strpos($column, 'blog_posts.') !== 0 && $column !== 'comment_count') {
                $column = 'blog_posts.' . $column;
            }

            if (\preg_match('/^[a-zA-Z0-9_\.]+$/', $column)) {
                if ($direction !== 'ASC' && $direction !== 'DESC') {
                    $direction = 'ASC';
                }
                $cleanOrderBy = "{$column} {$direction}";
            }
        }
        $orderBy = $cleanOrderBy;

        $deletedSql = !empty($filters['trash']) ? "blog_posts.deleted_at IS NOT NULL" : "blog_posts.deleted_at IS NULL";
        $where = ["blog_posts.site_id = ?", $deletedSql];
        $params = [$siteId];

        if (isset($filters['q']) && !empty($filters['q'])) {
            $where[] = "(blog_posts.title LIKE ? OR blog_posts.slug LIKE ?)";
            $qParam = '%' . $filters['q'] . '%';
            $params[] = $qParam;
            $params[] = $qParam;
        }

        $whereSql = 'WHERE ' . \implode(' AND ', $where);

        // Total count
        $totalStmt = DB::query("SELECT COUNT(*) as cnt FROM blog_posts {$whereSql}", $params);
        $total = $totalStmt->fetch();
        $totalCount = $total ? \intval($total['cnt']) : 0;

        $pages = \max(1, \ceil($totalCount / $perPage));
        $offset = ($page - 1) * $perPage;

        // Fetch paginated data using a JOIN to select comment_count and featured_image_path in a single query
        $sql = "
            SELECT blog_posts.*, 
                   COUNT(blog_comments.id) AS comment_count,
                   media.path AS featured_image_path
            FROM blog_posts 
            LEFT JOIN blog_comments ON blog_comments.post_id = blog_posts.id 
                                   AND blog_comments.status = 'approved' 
                                   AND blog_comments.deleted_at IS NULL
            LEFT JOIN media ON media.id = blog_posts.featured_image
            {$whereSql} 
            GROUP BY blog_posts.id, media.path
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
     * Custom save implementation to swap back-and-forth between path previews and media ID strings for database storage.
     */
    public function save(): bool
    {
        $originalFeaturedImage = $this->featured_image;
        if (!empty($this->featured_image_id)) {
            $this->featured_image = $this->featured_image_id;
        }
        
        $res = parent::save();
        
        $this->featured_image = $originalFeaturedImage;
        return $res !== false;
    }
}
