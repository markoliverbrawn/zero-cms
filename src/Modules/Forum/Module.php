<?php

declare(strict_types=1);

/**
 * File: src/Modules/Forum/Module.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Forum
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Forum;

use Zero\Core\App;
use Zero\Interfaces\Module as ModuleInterface;
use Zero\Modules\Admin\Controllers\FrontendForgotController;
use Zero\Modules\Admin\Controllers\FrontendLoginController;
use Zero\Modules\Admin\Controllers\FrontendResetController;
use Zero\Modules\Admin\Controllers\RegisterController;
use Zero\Modules\Forum\Controllers\BoardViewController;
use Zero\Modules\Forum\Controllers\ForumHomeController;
use Zero\Modules\Forum\Controllers\ModerateController;
use Zero\Modules\Forum\Controllers\ReplyCreateController;
use Zero\Modules\Forum\Controllers\ThreadCreateController;
use Zero\Modules\Forum\Controllers\ThreadViewController;
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
        return '#6366f1';
    }

    /**
     * Retrieves the dashboard widget view attribute value.
     *
     * @return string Response output.
     */
    public function getDashboardWidgetView(): ?string
    {
        return null;
    }

    /**
     * Retrieves the id attribute value.
     *
     * @return string Response output.
     */
    public function getId(): string
    {
        return 'forum';
    }

    /**
     * Retrieves the migration class attribute value.
     *
     * @return string Response output.
     */
    public function getMigrationClass(): ?string
    {
        return 'Zero\\Modules\\Forum\\Database\\Migrations\\CreateForumTables';
    }

    /**
     * Retrieves the routes attribute value.
     *
     * @return mixed Response output.
     */
    public function getRoutes(): array
    {
        return [
            // Standard frontend auth fallbacks in case Shop module is not enabled
            '#^/login$#' => FrontendLoginController::class,
            '#^/register$#' => RegisterController::class,
            '#^/forgot$#' => FrontendForgotController::class,
            '#^/reset$#' => FrontendResetController::class,

            // Forum paths
            '#^/forum$#' => ForumHomeController::class,
            '#^/forum/board/([a-zA-Z0-9\-]+)$#' => BoardViewController::class,
            '#^/forum/thread/([a-zA-Z0-9\-]+)$#' => ThreadViewController::class,
            '#^/forum/board/([a-zA-Z0-9\-]+)/create$#' => ThreadCreateController::class,
            '#^/forum/thread/([a-zA-Z0-9\-]+)/reply$#' => ReplyCreateController::class,
            '#^/forum/thread/([a-zA-Z0-9\-]+)/moderate$#' => ModerateController::class,
        ];
    }

    /**
     * Init processing implementation helper.
     *
     * @return mixed Response output.
     */
    public function init()
    {
        App::registerThemeFallback('forum');
        App::registerModel('forum_boards', Models\ForumBoard::class);
        App::registerModel('forum_threads', Models\ForumThread::class);
        App::registerModel('forum_posts', Models\ForumPost::class);

        App::registerAdminSidebarSection('forum', [
            'title' => 'Forum Management',
            'icon' => 'users',
            'module_dependency' => 'forum',
            'precedence' => 300
        ]);

        App::registerAdminSidebarLink('forum', [
            'title' => 'Manage Boards',
            'url' => '/admin/list/forum_boards',
            'icon' => 'layout',
            'module_dependency' => 'forum',
            'precedence' => 10
        ]);

        App::registerAdminSidebarLink('forum', [
            'title' => 'Manage Threads',
            'url' => '/admin/list/forum_threads',
            'icon' => 'message-square',
            'module_dependency' => 'forum',
            'precedence' => 20
        ]);

        App::registerAdminSidebarLink('forum', [
            'title' => 'Manage Posts',
            'url' => '/admin/list/forum_posts',
            'icon' => 'message-circle',
            'module_dependency' => 'forum',
            'precedence' => 30
        ]);
    }
}
