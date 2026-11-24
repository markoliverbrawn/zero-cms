<?php

declare(strict_types=1);

/**
 * File: src/Modules/Security/HealthChecks/Checks/DefaultPasswordCheck.php
 * Architectural Purpose: Checks no account still uses the seeded default password.
 * Package: Zero\Modules\Security\HealthChecks\Checks
 * Systemic Role: One of the deployment health checks run by HealthCheckRunner.
 */

namespace Zero\Modules\Security\HealthChecks\Checks;

use Zero\Database\DB;
use Zero\Models\Site;
use Zero\Modules\Security\HealthChecks\CheckResult;
use Zero\Modules\Security\HealthChecks\HealthCheck;

/**
 * Class DefaultPasswordCheck
 *
 * Looks for accounts on this site, or global accounts, whose password hash is still the one the
 * seeders ship with. Fails in production, warns elsewhere.
 */
class DefaultPasswordCheck implements HealthCheck
{
    /**
     * Hash of the default password the seeders assign.
     */
    private const DEFAULT_HASH = '$2y$10$2tdsRK0UD/QvrVPFoz1WZOtodh33dRR1jfRzQbkDDpUuBfHZJPzhC';

    /**
     * Decide the result from the accounts found.
     *
     * @param array<int, string> $usernames Accounts still on the default password.
     * @param bool $isProduction Whether the installation runs in production.
     * @return CheckResult
     */
    public static function evaluate(array $usernames, bool $isProduction): CheckResult
    {
        if ($usernames === []) {
            return CheckResult::pass('No account uses the default password.');
        }
        $message = 'Accounts still using the default password: ' . \implode(', ', $usernames) . '.';

        return $isProduction ? CheckResult::fail($message) : CheckResult::warn($message);
    }

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'default_passwords';
    }

    /**
     * {@inheritdoc}
     */
    public function label(): string
    {
        return 'Default passwords';
    }

    /**
     * {@inheritdoc}
     */
    public function run(Site $site, bool $isProduction): CheckResult
    {
        try {
            // Global (site_id NULL) super admins can sign in to every site, so they count too.
            $rows = DB::query(
                "SELECT username FROM users WHERE password_hash = ? AND (site_id = ? OR site_id IS NULL) AND deleted_at IS NULL",
                [self::DEFAULT_HASH, $site->id]
            )->fetchAll();
        } catch (\Exception $e) {
            return CheckResult::warn('Could not check passwords: ' . $e->getMessage());
        }

        return self::evaluate(\array_map('strval', \array_column($rows, 'username')), $isProduction);
    }
}
