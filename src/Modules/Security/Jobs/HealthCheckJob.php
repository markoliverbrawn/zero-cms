<?php

declare(strict_types=1);

/**
 * File: src/Modules/Security/Jobs/HealthCheckJob.php
 * Architectural Purpose: Scheduled job running the deployment health checks for a site.
 * Package: Zero\Modules\Security\Jobs
 * Systemic Role: Registered with the Queue module's Scheduler; emails only when something needs attention.
 */

namespace Zero\Modules\Security\Jobs;

use Zero\Core\App;
use Zero\Core\Env;
use Zero\Core\Template;
use Zero\Interfaces\Job;
use Zero\Models\Site;
use Zero\Modules\Security\HealthChecks\CheckResult;
use Zero\Modules\Security\Services\HealthCheckRunner;
use Zero\Support\Emailer;

/**
 * Class HealthCheckJob
 *
 * Runs every health check for the current site and archives the run. Emails the site's report
 * recipient only when a check fails or any check's status changed since the previous run, so a
 * healthy, unchanged site produces no mail.
 */
class HealthCheckJob implements Job
{
    /**
     * Run the checks, archive the run, and notify when warranted.
     *
     * @param array $payload Unused.
     * @return void
     */
    public function execute(array $payload): void
    {
        $site = App::getCurrentSite();
        if (!$site) {
            return;
        }

        try {
            $run = HealthCheckRunner::run($site);
        } catch (\Exception $e) {
            \error_log('HealthCheckJob: health check run failed for site ' . $site->id . ': ' . $e->getMessage());
            return;
        }

        if (!HealthCheckRunner::shouldNotify($run)) {
            return;
        }
        $recipient = self::recipient($site);
        if ($recipient === '') {
            return;
        }

        $counts = \array_count_values(\array_column($run['results'], 'status'));
        $failing = (int) ($counts[CheckResult::FAIL] ?? 0);
        $warning = (int) ($counts[CheckResult::WARN] ?? 0);
        $summary = $failing > 0 ? "{$failing} failing" : ($warning > 0 ? "{$warning} warning" . ($warning === 1 ? '' : 's') : 'all passing');

        try {
            $html = Template::renderFile(APPLICATION_ROOT . '/src/Modules/Security/Views/health_check_email.php', [
                'results' => $run['results'],
                'status' => $run['status'],
                'changed' => $run['changed'],
                'siteName' => (string) ($site->name ?? ''),
                'siteDomain' => (string) ($site->domain ?? ''),
                'reportUrl' => $site->absoluteUrl('/admin/security/audit?id=' . \rawurlencode((string) $run['audit']->id)),
            ]);
            Emailer::send($recipient, 'Health check: ' . $summary . ' [' . ($site->domain ?? 'unknown') . ']', $html);
        } catch (\Exception $e) {
            \error_log('HealthCheckJob: failed to email health check for site ' . $site->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Who receives this site's health check email: its 'report_email' security setting when that
     * is a valid address, otherwise the installation-wide ADMIN_EMAIL.
     *
     * @param Site $site Site.
     * @return string The recipient, or '' if neither is configured.
     */
    public static function recipient($site): string
    {
        $configured = \trim((string) $site->getModuleSetting('security', 'report_email', ''));
        if ($configured !== '') {
            if (\filter_var($configured, FILTER_VALIDATE_EMAIL) !== false) {
                return $configured;
            }
            \error_log("HealthCheckJob: ignoring invalid report_email for site {$site->id}; using ADMIN_EMAIL.");
        }

        return \trim((string) Env::get('ADMIN_EMAIL', ''));
    }
}
