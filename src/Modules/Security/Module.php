<?php

declare(strict_types=1);

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
use Zero\Models\Site;
use Zero\Modules\Queue\Support\Scheduler;
use Zero\Modules\Security\Controllers\ChangePasswordController;
use Zero\Modules\Security\Controllers\SecurityAuditController;
use Zero\Modules\Security\Jobs\SecurityAuditJob;
use Zero\Modules\Security\Models\AuditLog;
use Zero\Modules\Security\Models\SecurityAudit;

/**
 * Class Module
 *
 * Module contract implementation for the Security module: the audit tooling and its scheduled job,
 * the audit log screens, the password-change flow, and the CSP and forced-password-change
 * middleware.
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
     * Retrieves the name attribute value.
     *
     * @return string Response output.
     */
    public function getName(): string
    {
        return 'Security';
    }

    /**
     * Retrieves the description attribute value.
     *
     * @return string Response output.
     */
    public function getDescription(): string
    {
        return 'Hardening & AI threat auditing';
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
        App::registerModel('audit_logs', AuditLog::class);
        App::registerModel('security_audits', SecurityAudit::class);
        App::registerCascadeDelete(Site::class, SecurityAudit::class, 'site_id');

        // Register the RBAC permission keys this module owns and grant them to the 'admin' role
        // (super_admin already has universal access via its wildcard). audit.purge_global is
        // intentionally left ungranted here -- it stays super_admin-only.
        App::registerPermission('audit.manage', ['admin']);
        App::registerPermission('audit.purge', ['admin']);
        App::registerPermission('security.audit', ['admin']);
        App::registerModelPermission('audit_logs', 'audit.manage');
        App::registerModelPermission('security_audits', 'security.audit');

        // Security-owned admin sidebar links, gated by the permissions registered above
        App::registerAdminSidebarLink('security', [
            'title' => 'Security Logs',
            'url' => '/admin/list/audit_logs',
            'icon' => 'clock',
            'permission' => 'audit.manage',
            'precedence' => 20
        ]);

        App::registerAdminSidebarLink('security', [
            'title' => 'Security Audits',
            'url' => '/admin/list/security_audits',
            'icon' => 'clipboard',
            'permission' => 'security.audit',
            'precedence' => 30
        ]);

        // Explicitly register our security controllers under 'admin' context to bypass DB enabled_modules constraints
        Router::register([
            '#^/admin/change-password$#' => ChangePasswordController::class,
            '#^/admin/list/security_audits$#' => SecurityAuditController::class,
            '#^/admin/security/audit$#' => SecurityAuditController::class,
        ], null, 'admin');

        // Per-site audit schedule and report recipient. The audit inspects the shared codebase and
        // server rather than anything tenant-specific, so a multi-site install will usually leave
        // it on for one site and switch the rest off. SECURITY_AUDIT_SCHEDULE / ADMIN_EMAIL remain
        // the defaults for sites that have never saved these settings.
        App::registerModuleSettings('security', [
            'audit_schedule' => [
                'type' => 'select',
                'label' => 'Automated Audit Schedule',
                'options' => [
                    Scheduler::OFF => 'Off',
                    'hourly' => 'Hourly',
                    'daily' => 'Daily',
                    'weekly' => 'Weekly',
                ],
                'default' => Env::get('SECURITY_AUDIT_SCHEDULE', 'daily'),
                'helper_text' => 'How often the security audit runs for this site and emails its report. The audit checks the whole installation, so on a multi-site install it only needs to run on one site.'
            ],
            'report_email' => [
                'type' => 'email',
                'label' => 'Audit Report Recipient',
                'default' => '',
                'helper_text' => 'Where audit reports are emailed. Leave blank to use the installation\'s ADMIN_EMAIL.'
            ]
        ]);

        // Register dynamic automated security audit job if backend Scheduler is present
        if (\class_exists(Scheduler::class)) {
            Scheduler::register(
                SecurityAuditJob::class,
                [],
                static fn(Site $site): string => (string)$site->getModuleSetting('security', 'audit_schedule', Env::get('SECURITY_AUDIT_SCHEDULE', 'daily'))
            );
        }
    }
}
