<?php

declare(strict_types=1);

/**
 * File: src/Modules/Blog/Models/Comment.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Blog\Models
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Blog\Models;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Interfaces\Model;
use Zero\Models\Traits\IsModel;

/**
 * Class Comment
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class Comment implements Model
{
    use IsModel;

    public $site_id;
    public $post_id;
    public $post_title;
    public $author_name;
    public $author_email;
    public $content;
    public $status;
    public $created_at;
    public $updated_at;
    public $deleted_at;

    protected static string $tableName = 'blog_comments';
    protected static array $fillable = ['post_id', 'author_name', 'author_email', 'content', 'status'];

    /**
     * __construct processing implementation helper.
     *
     * @param array $data Argument descriptor.
     * @return mixed Response output.
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (\property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        
        // Dynamically resolve and cache the human-readable Post Title on the server-side
        // if not already pre-hydrated via eager loading!
        if (empty($this->post_title) && !empty($this->post_id)) {
            $post = DB::query("SELECT title FROM blog_posts WHERE id = ? LIMIT 1", [$this->post_id])->fetch();
            if ($post) {
                $this->post_title = $post['title'];
            }
        }
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
            'post_id' => ['type' => 'text', 'label' => 'Post ID', 'editable' => false, 'listDisplay' => false],
            'post_title' => ['type' => 'text', 'label' => 'Post', 'editable' => false, 'listDisplay' => true, 'searchable' => true],
            'author_name' => ['type' => 'text', 'label' => 'Author Name', 'editable' => true, 'listDisplay' => true, 'searchable' => true],
            'author_email' => ['type' => 'text', 'label' => 'Author Email', 'editable' => true, 'listDisplay' => true, 'searchable' => true],
            'content' => ['type' => 'textarea', 'label' => 'Comment', 'editable' => true, 'listDisplay' => false],
            'status' => [
                'type' => 'select',
                'label' => 'Status',
                'options' => [
                    'pending' => 'Pending Moderation',
                    'approved' => 'Approved / Published',
                    'rejected' => 'Rejected',
                    'spam' => 'Spam'
                ],
                'editable' => true,
                'listDisplay' => true,
                'listView' => 'fields/status',
                'required' => true
            ],
            'created_at' => ['type' => 'datetime', 'label' => 'Created At', 'editable' => false, 'listDisplay' => true]
        ];
    }

    /**
     * Fetch all approved comments for a specific blog post.
     */
    public static function getForPost(string $postId): array
    {
        $siteId = App::getCurrentSiteId();
        $query = "
            SELECT * FROM blog_comments 
            WHERE site_id = ? AND post_id = ? AND status = 'approved' AND deleted_at IS NULL 
            ORDER BY created_at ASC
        ";
        return DB::query($query, [$siteId, $postId])->fetchAll(\PDO::FETCH_CLASS, self::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class);
    }
/**
     * Custom pagination for Comments to support left-joins and searching by dynamic Post Title.
     */
    public static function paginate($page = 1, $perPage = 10, $filters = [], $orderBy = 'created_at DESC')
    {
        // Defensive whitelisting and column mapping of the ORDER BY clause
        $orderByParts = \explode(' ', \trim($orderBy));
        $cleanOrderBy = 'blog_comments.created_at DESC'; // Fallback

        if (!empty($orderByParts)) {
            $column = $orderByParts[0];
            $direction = isset($orderByParts[1]) ? \strtoupper($orderByParts[1]) : 'ASC';

            if ($column === 'post_title') {
                $column = 'blog_posts.title';
            } elseif (\strpos($column, 'blog_comments.') !== 0 && $column !== 'title') {
                $column = 'blog_comments.' . $column;
            }

            if (\preg_match('/^[a-zA-Z0-9_\.]+$/', $column)) {
                if ($direction !== 'ASC' && $direction !== 'DESC') {
                    $direction = 'ASC';
                }
                $cleanOrderBy = "{$column} {$direction}";
            }
        }
        $orderBy = $cleanOrderBy;

        $where = [];
        $params = [];

        // Multi-tenant isolation filter
        $where[] = "blog_comments.site_id = ?";
        $params[] = App::getCurrentSiteId();

        // Soft delete generic exclusion filter
        if (!empty($filters['trash'])) {
            $where[] = "blog_comments.deleted_at IS NOT NULL";
        } else {
            $where[] = "blog_comments.deleted_at IS NULL";
        }

        // Search filter matching both comment attributes and post titles!
        if (isset($filters['q']) && !empty($filters['q'])) {
            $where[] = "(
                blog_comments.author_name LIKE ? OR 
                blog_comments.author_email LIKE ? OR 
                blog_comments.content LIKE ? OR 
                blog_posts.title LIKE ?
            )";
            $qParam = '%' . $filters['q'] . '%';
            $params[] = $qParam;
            $params[] = $qParam;
            $params[] = $qParam;
            $params[] = $qParam;
        }

        $whereSql = '';
        if ($where) {
            $whereSql = 'WHERE ' . \implode(' AND ', $where);
        }

        // Total count (using left join to ensure we can count matching search rows)
        $totalQuery = "
            SELECT COUNT(*) as cnt 
            FROM blog_comments 
            LEFT JOIN blog_posts ON blog_comments.post_id = blog_posts.id 
            {$whereSql}
        ";
        $totalStmt = DB::query($totalQuery, $params);
        $total = $totalStmt->fetch();
        $totalCount = $total ? \intval($total['cnt']) : 0;

        $pages = \max(1, \ceil($totalCount / $perPage));
        $offset = ($page - 1) * $perPage;

        // Fetch paginated data (using left join to select post title as well!)
        $sql = "
            SELECT blog_comments.*, blog_posts.title AS post_title 
            FROM blog_comments 
            LEFT JOIN blog_posts ON blog_comments.post_id = blog_posts.id 
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

    }
