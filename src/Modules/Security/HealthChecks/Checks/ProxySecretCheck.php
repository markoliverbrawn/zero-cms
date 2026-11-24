<?php

declare(strict_types=1);

/**
 * File: src/Modules/Security/HealthChecks/Checks/ProxySecretCheck.php
 * Architectural Purpose: Checks TRUSTED_PROXY_SECRET is configured.
 * Package: Zero\Modules\Security\HealthChecks\Checks
 * Systemic Role: One of the deployment health checks run by HealthCheckRunner.
 */

namespace Zero\Modules\Security\HealthChecks\Checks;

use Zero\Core\Env;
use Zero\Models\Site;
use Zero\Modules\Security\HealthChecks\CheckResult;
use Zero\Modules\Security\HealthChecks\HealthCheck;

/**
 * Class ProxySecretCheck
 *
 * With TRUSTED_PROXY_SECRET empty, core trusts X-Forwarded-Host and X-Forwarded-Proto from any
 * client (Security::resolveTrustedHost / resolveScheme), so a forged header can change the host
 * used in generated links. Fails in production, warns elsewhere.
 */
class ProxySecretCheck implements HealthCheck
{
    /**
     * Decide the result for a configured secret.
     *
     * @param string $secret TRUSTED_PROXY_SECRET value.
     * @param bool $isProduction Whether the installation runs in production.
     * @return CheckResult
     */
    public static function evaluate(string $secret, bool $isProduction): CheckResult
    {
        if ($secret !== '') {
            return CheckResult::pass('TRUSTED_PROXY_SECRET is set, so forwarded host and scheme headers are only trusted from the proxy.');
        }
        $message = 'TRUSTED_PROXY_SECRET is empty, so X-Forwarded-Host and X-Forwarded-Proto are trusted from any client.';

        return $isProduction ? CheckResult::fail($message) : CheckResult::warn($message);
    }

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'proxy_secret';
    }

    /**
     * {@inheritdoc}
     */
    public function label(): string
    {
        return 'Trusted proxy secret';
    }

    /**
     * {@inheritdoc}
     */
    public function run(Site $site, bool $isProduction): CheckResult
    {
        return self::evaluate((string) Env::get('TRUSTED_PROXY_SECRET', ''), $isProduction);
    }
}
