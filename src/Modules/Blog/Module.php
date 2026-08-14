<?php

declare(strict_types=1);

/**
 * File: src/Modules/Blog/Module.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Blog
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

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
use Zero\Support\I18n;
use Zero\Support\Seeder;

/**
 * Class Module
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class Module implements ModuleInterface
{
    /**
     * Retrieves the accent color attribute value.
     *
     * @return string Response output.
     */
    public function getAccentColor(): string
    {
        return '#3b82f6';
    }

    /**
     * Retrieves the dashboard widget view attribute value.
     *
     * @return string Response output.
     */
    public function getDashboardWidgetView(): ?string
    {
        return 'dashboard-widget';
    }

    /**
     * Retrieves the id attribute value.
     *
     * @return string Response output.
     */
    public function getId(): string
    {
        return 'blog';
    }

    /**
     * Retrieves the migration class attribute value.
     *
     * @return string Response output.
     */
    public function getMigrationClass(): ?string
    {
        return Migration::class;
    }

    /**
     * Retrieves the routes attribute value.
     *
     * @return mixed Response output.
     */
    public function getRoutes(): array
    {
        return [
            '#^/post/([a-zA-Z0-9\-]+)$#' => PostViewController::class,
            '#^/api/v1/posts(?:/(.*))?$#' => PostsController::class,
            '#^/api/v1/blog/comments/submit$#' => CommentsController::class
        ];
    }

    /**
     * Init processing implementation helper.
     *
     * @return mixed Response output.
     */
    public function init()
    {
        App::registerModel('posts', Post::class);
        App::registerModel('comments', Comment::class);

        // Register daily scheduled task to automatically purge rejected or spam comments (older than 7 days)
        Scheduler::register(Jobs\PurgeOldCommentsJob::class, [], 'daily');

        App::registerModuleStylesheet('blog', APPLICATION_ROOT . '/public/assets/css/blocks/latest_articles.css');
        App::registerModuleStylesheet('blog', APPLICATION_ROOT . '/public/assets/css/blocks/sub_pages.css');

        App::registerAdminSidebarLink('content', [
            'title' => I18n::t('manage_posts'),
            'url' => '/admin/list/posts',
            'icon' => 'edit-3',
            'module_dependency' => 'blog',
            'precedence' => 10
        ]);

        App::registerAdminSidebarLink('content', [
            'title' => 'Manage Comments',
            'url' => '/admin/list/comments',
            'icon' => 'message-square',
            'module_dependency' => 'blog',
            'precedence' => 20
        ]);

        App::registerBlock('latest_articles', [
            'label' => 'Latest Blog Articles',
            'description' => 'Showcases the newest published articles from your blog dynamically with layouts and options.',
            'icon' => 'edit-3',
            'admin_view' => \dirname(__FILE__) . '/Views/blocks/admin/latest_articles.php',
            'frontend_view' => \dirname(__FILE__) . '/Views/blocks/frontend/latest_articles.php'
        ]);

        if (\class_exists(SearchService::class)) {
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
