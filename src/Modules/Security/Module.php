<?php
/**
 * File: src/Modules/Security/Module.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Security
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

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
        return '#ef4444';
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
        return 'security';
    }

    /**
     * Retrieves the migration class attribute value.
     *
     * @return string Response output.
     */
    public function getMigrationClass(): ?string
    {
        // Database migration is handled sequentially by global migrate schemas
        return null;
    }

    /**
     * Retrieves the routes attribute value.
     *
     * @return mixed Response output.
     */
    public function getRoutes(): array
    {
        // Return empty array here so the dynamic discovery loop doesn't register under 'security' (which is disabled in DB seeders)
        return [];
    }

    /**
     * Init processing implementation helper.
     *
     * @return mixed Response output.
     */
    public function init()
    {
        // Register the modular active record models dynamically on bootstrap
        App::registerModel('audit_logs', AuditLog::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class);
        App::registerModel('security_audits', SecurityAudit::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class);

        // Explicitly register our security controllers under 'admin' context to bypass DB enabled_modules constraints
        Router::register([
            '#^/admin/change-password$#' => ChangePasswordController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/admin/list/security_audits$#' => SecurityAuditController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/admin/security/audit$#' => SecurityAuditController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
        ], null, 'admin');

        // Register dynamic automated security audit job if backend Scheduler is present
        if (class_exists(Scheduler::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class)) {
            $schedule = Env::get('SECURITY_AUDIT_SCHEDULE', 'daily');
            Scheduler::register(SecurityAuditJob::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class, [], $schedule);
        }
    }
}
