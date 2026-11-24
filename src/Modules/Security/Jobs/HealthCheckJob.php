<?php

declare(strict_types=1);

/**
 * File: src/Modules/Security/Jobs/SecurityAuditJob.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Security\Jobs
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Security\Jobs;

use ReflectionClass;
use Zero\Core\App;
use Zero\Core\Env;
use Zero\Core\Template;
use Zero\Interfaces\Job;
use Zero\Modules\Security\Controllers\SecurityAuditController;
use Zero\Modules\Security\Models\SecurityAudit;
use Zero\Support\Emailer;

/**
 * Class SecurityAuditJob
 *
 * Scheduled job that runs the security audit unattended and emails the resulting report to the
 * configured administrator.
 */
class SecurityAuditJob implements Job
{
    /**
     * Executes the background security audit, archives the report, and dispatches email notifications.
     *
     * @param array $payload
     * @return void
     */
    public function execute(array $payload): void
    {
        $site = App::getCurrentSite();
        if (!$site) {
            return;
        }

        // 1. Core audit orchestration via reflection on SecurityAuditController
        $controller = new SecurityAuditController();
        $reflector = new ReflectionClass(SecurityAuditController::class);

        $collectMethod = $reflector->getMethod('collectTelemetry');
        $collectMethod->setAccessible(true);
        $telemetry = $collectMethod->invoke($controller);

        $runMethod = $reflector->getMethod('runAudit');
        $runMethod->setAccessible(true);
        $report = $runMethod->invoke($controller, $telemetry);

        $score = $telemetry['calculated_score'] ?? 100;

        // 2. Archive this security audit historically in the database
        try {
            $audit = new SecurityAudit([
                'user_id' => null, // Executed by system background daemon
                'score' => $score,
                'environment' => $telemetry['environment'] ?? 'production',
                'telemetry' => \json_encode($telemetry),
                'report' => $report
            ]);
            $audit->save();
        } catch (\Exception $e) {
            \error_log('SecurityAuditJob: failed to archive audit report: ' . $e->getMessage());
        }

        // 3. Email the report to this site's configured recipient, else ADMIN_EMAIL
        $adminEmail = self::recipient($site);
        if (!empty($adminEmail)) {
            try {
                $htmlBody = Template::renderFile(
                    APPLICATION_ROOT . '/src/Modules/Security/Views/security_audit_report.php',
                    [
                        'report' => $report,
                        'score' => $score,
                        'siteName' => $site->name ?? '',
                        'siteDomain' => $site->domain ?? ''
                    ]
                );

                $subject = "Zero CMS Security Telemetry: Score {$score}/100 [Domain: " . ($site->domain ?? 'Unknown') . "]";
                Emailer::send($adminEmail, $subject, $htmlBody);
            } catch (\Exception $e) {
                // Fail gracefully during background execution
            }
        }
    }

    /**
     * Resolve who receives this site's audit report: its 'report_email' security setting when that
     * is a valid address, otherwise the installation-wide ADMIN_EMAIL.
     *
     * @param \Zero\Models\Site $site
     * @return string The recipient, or '' if neither is configured.
     */
    public static function recipient($site): string
    {
        $configured = \trim((string)$site->getModuleSetting('security', 'report_email', ''));
        if ($configured !== '') {
            if (\filter_var($configured, FILTER_VALIDATE_EMAIL) !== false) {
                return $configured;
            }
            \error_log("SecurityAuditJob: ignoring invalid report_email for site {$site->id}; using ADMIN_EMAIL.");
        }

        return \trim((string)Env::get('ADMIN_EMAIL', ''));
    }
}
