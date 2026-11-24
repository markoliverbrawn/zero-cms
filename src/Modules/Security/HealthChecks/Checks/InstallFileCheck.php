<?php

declare(strict_types=1);

/**
 * File: src/Modules/Security/HealthChecks/Checks/InstallFileCheck.php
 * Architectural Purpose: Checks no web-runnable installer is left on disk.
 * Package: Zero\Modules\Security\HealthChecks\Checks
 * Systemic Role: One of the deployment health checks run by HealthCheckRunner.
 */

namespace Zero\Modules\Security\HealthChecks\Checks;

use Zero\Models\Site;
use Zero\Modules\Security\HealthChecks\CheckResult;
use Zero\Modules\Security\HealthChecks\HealthCheck;
use Zero\Modules\Security\HealthChecks\ProjectRoot;

/**
 * Class InstallFileCheck
 *
 * An install.php that is not locked to the CLI could reinitialise the installation from a
 * browser. One that refuses non-CLI execution is harmless and passes.
 */
class InstallFileCheck implements HealthCheck
{
    /**
     * Decide the result from the installers found.
     *
     * @param array<string, string> $files Relative path => file contents, for each install.php found.
     * @return CheckResult
     */
    public static function evaluate(array $files): CheckResult
    {
        $unlocked = [];
        foreach ($files as $path => $content) {
            if (\strpos($content, "php_sapi_name() !== 'cli'") === false) {
                $unlocked[] = $path;
            }
        }

        if ($unlocked !== []) {
            return CheckResult::fail('Installer not locked to the command line: ' . \implode(', ', $unlocked) . '. Delete it.');
        }
        if ($files !== []) {
            return CheckResult::pass('Installer present but locked to the command line: ' . \implode(', ', \array_keys($files)) . '.');
        }

        return CheckResult::pass('No installer file found.');
    }

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'install_file';
    }

    /**
     * {@inheritdoc}
     */
    public function label(): string
    {
        return 'Installer file';
    }

    /**
     * {@inheritdoc}
     */
    public function run(Site $site, bool $isProduction): CheckResult
    {
        $roots = ProjectRoot::isSubProject()
            ? ['' => ProjectRoot::get(), 'core/' => APPLICATION_ROOT]
            : ['' => APPLICATION_ROOT];

        $files = [];
        foreach ($roots as $label => $root) {
            foreach (['install.php', 'etc/install.php', 'public/install.php'] as $relative) {
                $path = $root . '/' . $relative;
                if (!\is_file($path)) {
                    continue;
                }
                $content = \file_get_contents($path);
                if ($content === false) {
                    return CheckResult::warn("Could not read {$label}{$relative}.");
                }
                $files[$label . $relative] = $content;
            }
        }

        return self::evaluate($files);
    }
}
