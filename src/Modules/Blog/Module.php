<?php

namespace Zero\Modules\Blog;

use Zero\Core\App;
use Zero\Interfaces\Module as ModuleInterface;
use Zero\Modules\Blog\Controllers\Api\CommentsController;
use Zero\Modules\Blog\Controllers\Api\PostsController;
use Zero\Modules\Blog\Controllers\PostViewController;
use Zero\Modules\Blog\Database\Migration;
use Zero\Modules\Blog\Models\Comment;
use Zero\Modules\Blog\Models\Post;
use Zero\Modules\Queue\Support\Scheduler;
use Zero\Modules\Search\Services\SearchService;

class Module implements ModuleInterface
{

    public function getDashboardWidgetView(): ?string
    {
        return 'dashboard-widget';
    }

    
    public function getId(): string
    {
        return 'blog';
    }

    

    public function getMigrationClass(): ?string
    {
        return Migration::class;
    }

    

    public function getRoutes(): array
    {
        return [
            '#^/post/([a-zA-Z0-9\-]+)$#' => PostViewController::class,
            '#^/api/v1/posts(?:/(.*))?$#' => PostsController::class,
            '#^/api/v1/blog/comments/submit$#' => CommentsController::class
        ];
    }

    

    public function init()
    {
        App::registerModel('posts', Post::class);
        App::registerModel('comments', Comment::class);

        // Register daily scheduled task to automatically purge rejected or spam comments (older than 7 days)
        Scheduler::register(Jobs\PurgeOldCommentsJob::class, [], 'daily');

        App::registerBlock('latest_articles', [
            'label' => 'Latest Blog Articles',
            'description' => 'Showcases the newest published articles from your blog dynamically with layouts and options.',
            'icon' => 'edit-3',
            'admin_view' => dirname(__FILE__) . '/Views/blocks/admin/latest_articles.php',
            'frontend_view' => dirname(__FILE__) . '/Views/blocks/frontend/latest_articles.php'
        ]);

        if (class_exists(SearchService::class)) {
            SearchService::register(Post::class, [
                'type_label' => 'Blog Post',
                'search_fields' => ['title', 'content', 'summary'],
                'title_field' => 'title',
                'content_field' => 'content',
                'status_field' => 'status'
            ]);
        }
    }
}
