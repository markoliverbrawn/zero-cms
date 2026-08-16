<?php

declare(strict_types=1);

/**
 * File: src/Modules/Queue/Module.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Queue
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Queue;

use Zero\Core\App;
use Zero\Interfaces\Module as ModuleInterface;
use Zero\Modules\Queue\Controllers\QueueApiController;
use Zero\Modules\Queue\Jobs\PurgeOldLogsJob;
use Zero\Modules\Queue\Models\QueueJob;
use Zero\Modules\Queue\Support\Scheduler;

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
        return '#475569';
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
        return 'queue';
    }

    /**
     * Retrieves the migration class attribute value.
     *
     * @return string Response output.
     */
    public function getMigrationClass(): ?string
    {
        return null;
    }

    /**
     * Retrieves the routes attribute value.
     *
     * @return mixed Response output.
     */
    public function getRoutes(): array
    {
        return [
            '#^/api/v1/queue/process$#' => QueueApiController::class
        ];
    }

    /**
     * Init processing implementation helper.
     *
     * @return mixed Response output.
     */
    public function init()
    {
        App::registerModel('queue_jobs', QueueJob::class);

        // Register hourly scheduled task to automatically purge old tenant audit logs (retention
        // window configurable via the 'audit_log_retention_days' site setting below)
        Scheduler::register(PurgeOldLogsJob::class, [], 'hourly');

        App::registerModuleSettings('queue', [
            'audit_log_retention_days' => [
                'type' => 'number',
                'label' => 'Audit Log Retention (Days)',
                'default' => 365,
                'required' => true,
                'helper_text' => 'Audit log entries older than this are permanently purged automatically. Adjust to match your compliance/retention requirements.'
            ]
        ]);

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
