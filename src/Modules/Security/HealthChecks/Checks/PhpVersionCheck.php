<?php

declare(strict_types=1);

/**
 * File: src/Modules/Security/HealthChecks/Checks/PhpVersionCheck.php
 * Architectural Purpose: Checks the running PHP branch is supported and fully patched.
 * Package: Zero\Modules\Security\HealthChecks\Checks
 * Systemic Role: One of the deployment health checks run by HealthCheckRunner.
 */

namespace Zero\Modules\Security\HealthChecks\Checks;

use Zero\Models\Site;
use Zero\Modules\Security\HealthChecks\CheckResult;
use Zero\Modules\Security\HealthChecks\HealthCheck;
use Zero\Modules\Security\HealthChecks\HttpProbe;

/**
 * Class PhpVersionCheck
 *
 * Compares PHP_VERSION with php.net's list of supported branches: a branch missing from the list
 * is unsupported (fail); a version behind its branch's latest release is missing fixes (warn).
 */
class PhpVersionCheck implements HealthCheck
{
    private const FEED = 'https://www.php.net/releases/active.php';

    /**
     * Decide the result from php.net's active-releases feed.
     *
     * @param string $version Running PHP version.
     * @param array<mixed> $feed Decoded feed: {major: {"major.minor": {version, tags}}}.
     * @return CheckResult
     */
    public static function evaluate(string $version, array $feed): CheckResult
    {
        $parts = \explode('.', $version);
        $branch = ($parts[0] ?? '') . '.' . ($parts[1] ?? '');
        $release = $feed[$parts[0] ?? ''][$branch] ?? null;
        if (!\is_array($release) || !isset($release['version'])) {
            return CheckResult::fail("PHP {$branch} is no longer supported by the PHP project; upgrade to a supported branch.");
        }

        $latest = (string) $release['version'];
        $securityOnly = \in_array('security', (array) ($release['tags'] ?? []), true) ? ' (security-fix-only branch)' : '';
        if (\version_compare($version, $latest, '<')) {
            return CheckResult::warn("PHP {$version} is behind the latest {$branch} release, {$latest}{$securityOnly}.");
        }

        return CheckResult::pass("PHP {$version} is the latest {$branch} release{$securityOnly}.");
    }

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'php_version';
    }

    /**
     * {@inheritdoc}
     */
    public function label(): string
    {
        return 'PHP version';
    }

    /**
     * {@inheritdoc}
     */
    public function run(Site $site, bool $isProduction): CheckResult
    {
        try {
            return self::evaluate(PHP_VERSION, HttpProbe::getJson(self::FEED));
        } catch (\RuntimeException $e) {
            return CheckResult::warn('Could not check PHP ' . PHP_VERSION . ' against php.net: ' . $e->getMessage());
        }
    }
}
