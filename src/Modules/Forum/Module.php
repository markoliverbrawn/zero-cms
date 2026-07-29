<?php

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

class Module implements ModuleInterface
{
    public function getAccentColor(): string
    {
        return '#6366f1';
    }

    public function getDashboardWidgetView(): ?string
    {
        return null;
    }

    public function getId(): string
    {
        return 'forum';
    }

    public function getMigrationClass(): ?string
    {
        return 'Zero\\Modules\\Forum\\Database\\Migrations\\CreateForumTables';
    }

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

    public function init()
    {
        App::registerThemeFallback('forum');
        App::registerModel('forum_boards', Models\ForumBoard::class);
        App::registerModel('forum_threads', Models\ForumThread::class);
        App::registerModel('forum_posts', Models\ForumPost::class);
    }
}
