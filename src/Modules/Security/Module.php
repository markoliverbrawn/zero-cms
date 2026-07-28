<?php

namespace Zero\Modules\Security;

use Zero\Core\App;
use Zero\Core\Env;
use Zero\Http\Router;
use Zero\Interfaces\Module as ModuleInterface;
use Zero\Modules\Queue\Support\Scheduler;
use Zero\Modules\Security\Controllers\ChangePasswordController;
use Zero\Modules\Security\Controllers\SecurityAuditController;
use Zero\Modules\Security\Jobs\SecurityAuditJob;
use Zero\Modules\Security\Models\AuditLog;
use Zero\Modules\Security\Models\SecurityAudit;

class Module implements ModuleInterface
{
    public function getAccentColor(): string
    {
        return '#ef4444';
    }

    public function getDashboardWidgetView(): ?string
    {
        return 'dashboard-widget';
    }

    public function getId(): string
    {
        return 'security';
    }

    public function getMigrationClass(): ?string
    {
        // Database migration is handled sequentially by global migrate schemas
        return null;
    }

    public function getRoutes(): array
    {
        // Return empty array here so the dynamic discovery loop doesn't register under 'security' (which is disabled in DB seeders)
        return [];
    }

    public function init()
    {
        // Register the modular active record models dynamically on bootstrap
        App::registerModel('audit_logs', AuditLog::class);
        App::registerModel('security_audits', SecurityAudit::class);

        // Explicitly register our security controllers under 'admin' context to bypass DB enabled_modules constraints
        Router::register([
            '#^/admin/change-password$#' => ChangePasswordController::class,
            '#^/admin/list/security_audits$#' => SecurityAuditController::class,
            '#^/admin/security/audit$#' => SecurityAuditController::class,
        ], null, 'admin');

        // Register dynamic automated security audit job if backend Scheduler is present
        if (class_exists(Scheduler::class)) {
            $schedule = Env::get('SECURITY_AUDIT_SCHEDULE', 'daily');
            Scheduler::register(SecurityAuditJob::class, [], $schedule);
        }
    }
}
