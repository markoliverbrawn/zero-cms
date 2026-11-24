<?php

declare(strict_types=1);

/**
 * File: src/Modules/Security/HealthChecks/Checks/FailedLoginCheck.php
 * Architectural Purpose: Checks for a spike in failed sign-ins.
 * Package: Zero\Modules\Security\HealthChecks\Checks
 * Systemic Role: One of the deployment health checks run by HealthCheckRunner.
 */

namespace Zero\Modules\Security\HealthChecks\Checks;

use Zero\Database\DB;
use Zero\Models\Site;
use Zero\Modules\Security\HealthChecks\CheckResult;
use Zero\Modules\Security\HealthChecks\HealthCheck;

/**
 * Class FailedLoginCheck
 *
 * Counts this site's failed authentication events in the last 24 hours and warns when they
 * exceed the site's 'failed_login_threshold' security setting.
 */
class FailedLoginCheck implements HealthCheck
{
    public const DEFAULT_THRESHOLD = 20;

    /**
     * Audit log actions that record a failed authentication attempt.
     */
    private const ACTIONS = [
        'login_failed',
        'frontend_login_failed',
        'password_reset_failed',
        'password_reset_request_failed',
        'registration_failed',
    ];

    /**
     * Decide the result from the count.
     *
     * @param int $count Failed attempts in the last 24 hours.
     * @param int $threshold Count above which to warn.
     * @return CheckResult
     */
    public static function evaluate(int $count, int $threshold): CheckResult
    {
        if ($count > $threshold) {
            return CheckResult::warn("{$count} failed sign-in attempts in the last 24 hours (threshold {$threshold}). See Security Logs.");
        }

        return CheckResult::pass("{$count} failed sign-in attempts in the last 24 hours (threshold {$threshold}).");
    }

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'failed_logins';
    }

    /**
     * {@inheritdoc}
     */
    public function label(): string
    {
        return 'Failed sign-ins';
    }

    /**
     * {@inheritdoc}
     */
    public function run(Site $site, bool $isProduction): CheckResult
    {
        $threshold = (int) $site->getModuleSetting('security', 'failed_login_threshold', self::DEFAULT_THRESHOLD);
        if ($threshold < 1) {
            $threshold = self::DEFAULT_THRESHOLD;
        }

        try {
            $placeholders = \implode(', ', \array_fill(0, \count(self::ACTIONS), '?'));
            // security-scan-ignore: sql-interpolation -- $placeholders is only '?' marks; values are bound
            $count = (int) DB::query(
                "SELECT COUNT(*) FROM audit_logs
                 WHERE site_id = ? AND action IN ($placeholders) AND created_at >= ? AND deleted_at IS NULL",
                \array_merge([$site->id], self::ACTIONS, [\gmdate('Y-m-d H:i:s', \time() - 86400)])
            )->fetchColumn();
        } catch (\Exception $e) {
            return CheckResult::warn('Could not count failed sign-ins: ' . $e->getMessage());
        }

        return self::evaluate($count, $threshold);
    }
}
