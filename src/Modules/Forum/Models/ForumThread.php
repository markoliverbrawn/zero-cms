<?php

declare(strict_types=1);

/**
 * File: src/Modules/Forum/Models/ForumThread.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Forum\Models
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Forum\Models;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Interfaces\Model as ModelInterface;
use Zero\Models\Traits\CascadesDeletes;
use Zero\Models\Traits\HasSlug;
use Zero\Models\Traits\IsModel;
use Zero\Models\User;
use Zero\Modules\Forum\Models\ForumBoard;
use Zero\Modules\Forum\Models\ForumPost;

/**
 * Class ForumThread
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class ForumThread implements ModelInterface
{
    use IsModel, HasSlug, CascadesDeletes {
        CascadesDeletes::delete insteadof IsModel;
        CascadesDeletes::forceDelete insteadof IsModel;
        IsModel::delete as traitDelete;
        IsModel::forceDelete as traitForceDelete;
        IsModel::paginate as traitPaginate;
    }

    protected static $tableName = 'forum_threads';
    protected static $modelType = null;
    protected static $fillable = ['board_id', 'user_id', 'title', 'slug', 'status', 'views_count'];
    protected static array $cascadeDeletes = [
        ForumPost::class => 'thread_id'
    ];

    public $id;
    public $site_id;
    public $board_id;
    public $user_id;
    public $title;
    public $slug;
    public $status;
    public $views_count;
    public $created_at;
    public $updated_at;

    public $replies_count;
    public $author_username;

    /**
     * __construct processing implementation helper.
     *
     * @param mixed $data Argument descriptor.
     * @return mixed Response output.
     */
    public function __construct($data = [])
    {
        foreach ($data as $key => $value) {
            if (\property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        // Map from array if passed, but NEVER execute database queries in the constructor.
        if (isset($data['replies_count'])) {
            $this->replies_count = (int)$data['replies_count'];
        }
        if (isset($data['author_username'])) {
            $this->author_username = $data['author_username'];
        }
    }

    /**
     * Retrieves the author username attribute value.
     *
     * @return string Response output.
     */
    public function getAuthorUsername(): string
    {
        if (!empty($this->author_username)) {
            return $this->author_username;
        }
        $author = User::find($this->user_id);
        $this->author_username = $author ? $author->username : 'Guest';
        return $this->author_username;
    }

    /**
     * Retrieves the board threads attribute value.
     *
     * @param string $boardId Argument descriptor.
     * @return mixed Response output.
     */
    public static function getBoardThreads(string $boardId): array
    {
        $sql = "
            SELECT 
                forum_threads.*,
                users.username AS author_username,
                COUNT(CASE WHEN forum_posts.parent_id IS NOT NULL THEN 1 END) AS replies_count
            FROM forum_threads
            LEFT JOIN users ON forum_threads.user_id = users.id
            LEFT JOIN forum_posts ON forum_posts.thread_id = forum_threads.id AND forum_posts.deleted_at IS NULL
            WHERE forum_threads.board_id = ?
              AND forum_threads.site_id = ?
              AND forum_threads.deleted_at IS NULL
            GROUP BY forum_threads.id, users.username
            ORDER BY CASE WHEN forum_threads.status = 'pinned' THEN 1 ELSE 0 END DESC, forum_threads.created_at DESC
        ";
        
        $params = [$boardId, App::getCurrentSiteId()];
        $stmt = DB::query($sql, $params);
        $results = [];
        $userIds = [];
        
        while ($data = $stmt->fetch()) {
            $thread = new static($data);
            $results[] = $thread;
            if (!empty($thread->user_id)) {
                $userIds[] = $thread->user_id;
            }
        }
        
        // Eager load User models directly into the globally centralized DB identity map cache
        if (!empty($userIds)) {
            $userIds = \array_unique($userIds);
            $placeholders = \implode(',', \array_fill(0, \count($userIds), '?'));
            $usersData = DB::query(
                "SELECT * FROM users WHERE id IN ($placeholders) AND deleted_at IS NULL",
                $userIds
            )->fetchAll();
            
            foreach ($usersData as $userData) {
                $userModel = new User($userData);
                DB::setIdentity('users', $userModel->id, $userModel);
            }
        }
        
        return $results;
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
            'title' => ['type' => 'text', 'label' => 'Thread Title', 'editable' => true, 'required' => true, 'listDisplay' => true, 'searchable' => true],
            'board_id' => [
                'type' => 'readonly',
                'label' => 'Board',
                'editable' => true,
                'required' => true,
                'listDisplay' => true,
                'listView' => 'fields/forum_board'
            ],
            'user_id' => [
                'type' => 'readonly',
                'label' => 'Created By',
                'editable' => true,
                'required' => true,
                'listDisplay' => true,
                'listView' => 'fields/forum_user'
            ],
            'slug' => ['type' => 'text', 'label' => 'Slug', 'editable' => false, 'listDisplay' => true],
            'status' => [
                'type' => 'select',
                'label' => 'Status',
                'editable' => true,
                'required' => true,
                'listDisplay' => true,
                'options' => [
                    'published' => 'Published',
                    'locked' => 'Locked',
                    'pinned' => 'Pinned'
                ]
            ],
            'views_count' => ['type' => 'number', 'label' => 'Views Count', 'editable' => false, 'listDisplay' => true]
        ];
    }

    /**
     * Retrieves the posts attribute value.
     *
     * @return mixed Response output.
     */
    public function getPosts(): array
    {
        $postsData = DB::query(
            "SELECT * FROM forum_posts WHERE thread_id = ? AND deleted_at IS NULL ORDER BY created_at ASC",
            [$this->id]
        )->fetchAll();

        $posts = [];
        $userIds = [];
        foreach ($postsData as $row) {
            $posts[] = new ForumPost($row);
            if (!empty($row['user_id'])) {
                $userIds[] = $row['user_id'];
            }
        }

        // Eager load User models directly into the globally centralized DB identity map cache to prevent N+1 queries
        if (!empty($userIds)) {
            $userIds = \array_values(\array_unique($userIds));
            $placeholders = \implode(',', \array_fill(0, \count($userIds), '?'));
            $usersData = DB::query(
                "SELECT * FROM users WHERE id IN ($placeholders) AND deleted_at IS NULL",
                $userIds
            )->fetchAll();
            
            foreach ($usersData as $userData) {
                $userModel = new User($userData);
                DB::setIdentity('users', $userModel->id, $userModel);
            }
        }

        return $posts;
    }

    /**
     * Retrieves the replies count attribute value.
     *
     * @return int Response output.
     */
    public function getRepliesCount(): int
    {
        if (isset($this->replies_count)) {
            return (int)$this->replies_count;
        }
        $cnt = DB::query(
            "SELECT COUNT(*) FROM forum_posts WHERE thread_id = ? AND parent_id IS NOT NULL AND deleted_at IS NULL",
            [$this->id]
        )->fetchColumn();
        $this->replies_count = \intval($cnt);
        return $this->replies_count;
    }

    /**
     * Paginate processing implementation helper.
     *
     * @param mixed $page Argument descriptor.
     * @param mixed $perPage Argument descriptor.
     * @param mixed $filters Argument descriptor.
     * @param mixed $orderBy Argument descriptor.
     * @return mixed Response output.
     */
    public static function paginate($page = 1, $perPage = 10, $filters = [], $orderBy = 'created_at DESC')
    {
        $pagination = self::traitPaginate($page, $perPage, $filters, $orderBy);
        $records = $pagination['data'] ?? [];
        
        if (!empty($records)) {
            $userIds = [];
            $boardIds = [];
            foreach ($records as $thread) {
                if (!empty($thread->user_id)) {
                    $userIds[] = $thread->user_id;
                }
                if (!empty($thread->board_id)) {
                    $boardIds[] = $thread->board_id;
                }
            }
            
            // Eager load User models directly into the globally centralized DB identity map cache
            if (!empty($userIds)) {
                $userIds = \array_unique($userIds);
                $placeholders = \implode(',', \array_fill(0, \count($userIds), '?'));
                $usersData = DB::query(
                    "SELECT * FROM users WHERE id IN ($placeholders) AND deleted_at IS NULL",
                    $userIds
                )->fetchAll();
                
                foreach ($usersData as $userData) {
                    $userModel = new User($userData);
                    DB::setIdentity('users', $userModel->id, $userModel);
                }
            }
            
            // Eager load ForumBoard models directly into the globally centralized DB identity map cache
            if (!empty($boardIds)) {
                $boardIds = \array_unique($boardIds);
                $placeholders = \implode(',', \array_fill(0, \count($boardIds), '?'));
                $boardsData = DB::query(
                    "SELECT * FROM forum_boards WHERE id IN ($placeholders) AND deleted_at IS NULL",
                    $boardIds
                )->fetchAll();
                
                foreach ($boardsData as $boardData) {
                    $boardModel = new ForumBoard($boardData);
                    DB::setIdentity('forum_boards', $boardModel->id, $boardModel);
                }
            }
        }
        
        return $pagination;
    }
}
