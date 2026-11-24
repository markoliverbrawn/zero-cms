<?php

declare(strict_types=1);

/**
 * File: src/Modules/Security/HealthChecks/HealthCheck.php
 * Architectural Purpose: Contract for one deployment health check.
 * Package: Zero\Modules\Security\HealthChecks
 * Systemic Role: Implemented by each check the HealthCheckRunner executes on a schedule or on demand.
 */

namespace Zero\Modules\Security\HealthChecks;

use Zero\Models\Site;

/**
 * Interface HealthCheck
 *
 * A check inspects one aspect of a live deployment and reports a CheckResult. Checks must not
 * throw: anything they cannot determine (a network failure, say) is reported as a warning.
 */
interface HealthCheck
{
    /**
     * Stable identifier, used to compare results between runs.
     *
     * @return string
     */
    public function id(): string;

    /**
     * Short human-readable name.
     *
     * @return string
     */
    public function label(): string;

    /**
     * Run the check for a site.
     *
     * @param Site $site Site being checked.
     * @param bool $isProduction Whether the installation runs in production.
     * @return CheckResult
     */
    public function run(Site $site, bool $isProduction): CheckResult;
}
