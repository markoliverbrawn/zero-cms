<?php

namespace Zero\Modules\Queue;

use Zero\Core\App;
use Zero\Interfaces\Module as ModuleInterface;
use Zero\Modules\Queue\Controllers\QueueApiController;
use Zero\Modules\Queue\Jobs\PurgeOldLogsJob;
use Zero\Modules\Queue\Models\QueueJob;
use Zero\Modules\Queue\Support\Scheduler;

class Module implements ModuleInterface
{
    public function getAccentColor(): string
    {
        return '#475569';
    }

    public function getDashboardWidgetView(): ?string
    {
        return 'dashboard-widget';
    }

    public function getId(): string
    {
        return 'queue';
    }

    public function getMigrationClass(): ?string
    {
        return null;
    }

    public function getRoutes(): array
    {
        return [
            '#^/api/v1/queue/process$#' => QueueApiController::class
        ];
    }

    public function init()
    {
        App::registerModel('queue_jobs', QueueJob::class);

        // Register hourly scheduled task to automatically purge old tenant audit logs (older than 1 year)
        Scheduler::register(PurgeOldLogsJob::class, [], 'hourly');

        App::registerAdminSidebarSection('queue', [
            'title' => 'Job Queue',
            'icon' => 'clock',
            'module_dependency' => 'queue',
            'is_system' => true,
            'precedence' => 420
        ]);

        App::registerAdminSidebarLink('queue', [
            'title' => 'Manage Queue',
            'url' => '/admin/list/queue_jobs',
            'icon' => 'clipboard',
            'module_dependency' => 'queue',
            'precedence' => 10
        ]);
    }
}
