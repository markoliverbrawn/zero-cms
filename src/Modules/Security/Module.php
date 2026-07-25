<?php

namespace Zero\Modules\Security;

use Zero\Interfaces\Module as ModuleInterface;
use Zero\Core\App;
use Zero\Modules\Security\Controllers\ChangePasswordController;
use Zero\Modules\Security\Controllers\SecurityAuditController;
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
        \Zero\Http\Router::register([
            '#^/admin/change-password$#' => ChangePasswordController::class,
            '#^/admin/list/security_audits$#' => SecurityAuditController::class,
            '#^/admin/security/audit$#' => SecurityAuditController::class,
        ], null, 'admin');
    }
}
