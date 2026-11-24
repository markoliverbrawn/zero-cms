<?php

declare(strict_types=1);

/**
 * File: src/Modules/Security/Services/HealthCheckRunner.php
 * Architectural Purpose: Runs every deployment health check for a site and archives the result.
 * Package: Zero\Modules\Security\Services
 * Systemic Role: Shared by the scheduled HealthCheckJob, the admin "Run now" action and bin/health-check.
 */

namespace Zero\Modules\Security\Services;

use Zero\Core\Env;
use Zero\Database\DB;
use Zero\Models\Site;
use Zero\Modules\Security\HealthChecks\CheckResult;
use Zero\Modules\Security\HealthChecks\Checks\CoreVersionCheck;
use Zero\Modules\Security\HealthChecks\Checks\DefaultPasswordCheck;
use Zero\Modules\Security\HealthChecks\Checks\FailedLoginCheck;
use Zero\Modules\Security\HealthChecks\Checks\InstallFileCheck;
use Zero\Modules\Security\HealthChecks\Checks\PhpVersionCheck;
use Zero\Modules\Security\HealthChecks\Checks\ProxySecretCheck;
use Zero\Modules\Security\HealthChecks\Checks\SecurityHeadersCheck;
use Zero\Modules\Security\HealthChecks\HealthCheck;
use Zero\Modules\Security\Models\SecurityAudit;

/**
 * Class HealthCheckRunner
 *
 * Executes the checks, stores one security_audits row per run (status + JSON results), and
 * reports whether the outcome changed since the site's previous run so callers can decide
 * whether to notify anyone.
 */
class HealthCheckRunner
{
    /**
     * The checks to run, in display order.
     *
     * @return array<int, HealthCheck>
     */
    public static function checks(): array
    {
        return [
            new ProxySecretCheck(),
            new PhpVersionCheck(),
            new SecurityHeadersCheck(),
            new DefaultPasswordCheck(),
            new InstallFileCheck(),
            new CoreVersionCheck(),
            new FailedLoginCheck(),
        ];
    }

    /**
     * Whether any check's status differs between two runs' results. A first run (no previous
     * results) counts as a change.
     *
     * @param array<int, array{id: string, status: string}>|null $previous Earlier results.
     * @param array<int, array{id: string, status: string}> $current Latest results.
     * @return bool
     */
    public static function hasChanged(?array $previous, array $current): bool
    {
        if ($previous === null) {
            return true;
        }
        $map = static function (array $results): array {
            $statuses = [];
            foreach ($results as $result) {
                $statuses[(string) ($result['id'] ?? '')] = (string) ($result['status'] ?? '');
            }
            \ksort($statuses);
            return $statuses;
        };

        return $map($previous) !== $map($current);
    }

    /**
     * Whether the installation runs in production. Anything other than an explicit development,
     * local or test environment is treated as production.
     *
     * @return bool
     */
    public static function isProduction(): bool
    {
        $environment = \strtolower((string) Env::get('ENVIRONMENT', 'production'));

        return !\in_array($environment, ['dev', 'development', 'local', 'test', 'testing'], true);
    }

    /**
     * The results of a site's most recent health check run, or null if it has none.
     *
     * @param string $siteId Site id.
     * @return array<int, array{id: string, label: string, status: string, message: string}>|null
     */
    public static function latestResults(string $siteId): ?array
    {
        $row = DB::query(
            "SELECT results FROM security_audits
             WHERE site_id = ? AND results IS NOT NULL AND deleted_at IS NULL
             ORDER BY created_at DESC LIMIT 1",
            [$siteId]
        )->fetch();
        if (!$row) {
            return null;
        }
        $decoded = \json_decode((string) $row['results'], true);

        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * Run every check for a site, archive the run, and report the outcome.
     *
     * @param Site $site Site to check; must be the current tenant so the archived row is scoped to it.
     * @param string|null $userId Who triggered the run (null for the scheduler or CLI).
     * @return array{audit: SecurityAudit, status: string, results: array<int, array{id: string, label: string, status: string, message: string}>, changed: bool}
     * @throws \Exception When the run cannot be archived.
     */
    public static function run(Site $site, ?string $userId = null): array
    {
        $isProduction = self::isProduction();
        $previous = self::latestResults((string) $site->id);

        $results = [];
        foreach (self::checks() as $check) {
            try {
                $result = $check->run($site, $isProduction);
            } catch (\Throwable $e) {
                $result = CheckResult::warn('The check could not run: ' . $e->getMessage());
            }
            $results[] = ['id' => $check->id(), 'label' => $check->label(), 'status' => $result->status, 'message' => $result->message];
        }
        $status = CheckResult::worst(\array_column($results, 'status'));

        $audit = new SecurityAudit([
            'user_id' => $userId,
            'status' => $status,
            'environment' => $isProduction ? 'production' : \strtolower((string) Env::get('ENVIRONMENT', 'production')),
            'results' => \json_encode($results),
        ]);
        $audit->save();

        return ['audit' => $audit, 'status' => $status, 'results' => $results, 'changed' => self::hasChanged($previous, $results)];
    }

    /**
     * Whether a run warrants an email: something is failing, or any status changed since the
     * previous run (including recoveries).
     *
     * @param array{status: string, changed: bool} $run Outcome from run().
     * @return bool
     */
    public static function shouldNotify(array $run): bool
    {
        return $run['status'] === CheckResult::FAIL || $run['changed'];
    }
}
