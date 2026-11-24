<?php

declare(strict_types=1);

/**
 * File: src/Modules/Security/HealthChecks/Checks/CoreVersionCheck.php
 * Architectural Purpose: Checks the installed core release is the latest.
 * Package: Zero\Modules\Security\HealthChecks\Checks
 * Systemic Role: One of the deployment health checks run by HealthCheckRunner.
 */

namespace Zero\Modules\Security\HealthChecks\Checks;

use Zero\Models\Site;
use Zero\Modules\Security\HealthChecks\CheckResult;
use Zero\Modules\Security\HealthChecks\HealthCheck;
use Zero\Modules\Security\HealthChecks\HttpProbe;
use Zero\Modules\Security\HealthChecks\ProjectRoot;

/**
 * Class CoreVersionCheck
 *
 * Reads the installed zero-cms-core version from Composer's installed.json and compares it with
 * the newest release tag on GitHub. Security fixes ship as core releases, so running behind
 * means running without them.
 */
class CoreVersionCheck implements HealthCheck
{
    private const PACKAGE = 'markoliverbrawn/zero-cms-core';
    private const TAGS = 'https://api.github.com/repos/markoliverbrawn/zero-cms/tags?per_page=100';

    /**
     * Decide the result from the installed version and the published tags.
     *
     * @param string $installed Installed version, e.g. "v2.1.2".
     * @param array<int, string> $tags Published tag names.
     * @return CheckResult
     */
    public static function evaluate(string $installed, array $tags): CheckResult
    {
        $latest = null;
        foreach ($tags as $tag) {
            if (\preg_match('/^v?\d+\.\d+\.\d+$/', $tag) === 1 && ($latest === null || \version_compare(\ltrim($tag, 'v'), \ltrim($latest, 'v'), '>'))) {
                $latest = $tag;
            }
        }
        if ($latest === null) {
            return CheckResult::warn('No core releases were found to compare against.');
        }
        if (\preg_match('/^v?\d+\.\d+\.\d+$/', $installed) !== 1) {
            return CheckResult::warn("Installed core version {$installed} is not a release; the latest release is {$latest}.");
        }
        if (\version_compare(\ltrim($installed, 'v'), \ltrim($latest, 'v'), '<')) {
            return CheckResult::warn("Core {$installed} is behind the latest release, {$latest}.");
        }

        return CheckResult::pass("Core {$installed} is the latest release.");
    }

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'core_version';
    }

    /**
     * The installed core version from Composer metadata, or null when core is not installed as a
     * Composer package (e.g. running from the core repository itself).
     *
     * @return string|null
     */
    public static function installedVersion(): ?string
    {
        if (!ProjectRoot::isSubProject()) {
            return null;
        }
        $path = ProjectRoot::get() . '/vendor/composer/installed.json';
        if (!\is_file($path)) {
            return null;
        }
        $json = \file_get_contents($path);
        $decoded = $json === false ? null : \json_decode($json, true);
        $packages = \is_array($decoded) ? ($decoded['packages'] ?? $decoded) : [];
        foreach ((array) $packages as $package) {
            if (\is_array($package) && ($package['name'] ?? '') === self::PACKAGE) {
                return (string) ($package['version'] ?? '');
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function label(): string
    {
        return 'Core version';
    }

    /**
     * {@inheritdoc}
     */
    public function run(Site $site, bool $isProduction): CheckResult
    {
        $installed = self::installedVersion();
        if ($installed === null) {
            return CheckResult::pass('Core is not installed through Composer here, so there is no release to compare.');
        }

        try {
            $tags = \array_map(static fn ($tag): string => (string) ($tag['name'] ?? ''), HttpProbe::getJson(self::TAGS));
        } catch (\RuntimeException $e) {
            return CheckResult::warn("Could not check core {$installed} against GitHub: " . $e->getMessage());
        }

        return self::evaluate($installed, $tags);
    }
}
