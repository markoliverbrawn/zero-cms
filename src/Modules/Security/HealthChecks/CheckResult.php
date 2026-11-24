<?php

declare(strict_types=1);

/**
 * File: src/Modules/Security/HealthChecks/CheckResult.php
 * Architectural Purpose: Immutable outcome of one deployment health check.
 * Package: Zero\Modules\Security\HealthChecks
 * Systemic Role: The unit the health check runner aggregates, stores and compares between runs.
 */

namespace Zero\Modules\Security\HealthChecks;

/**
 * Class CheckResult
 *
 * A pass, warn or fail status with a one-line, human-readable reason.
 */
final class CheckResult
{
    public const FAIL = 'fail';
    public const PASS = 'pass';
    public const WARN = 'warn';

    /**
     * Severity order used to pick the worst of several statuses.
     */
    private const RANK = [self::PASS => 0, self::WARN => 1, self::FAIL => 2];

    /**
     * @param string $status One of the PASS/WARN/FAIL constants.
     * @param string $message Why the check reached this status.
     */
    private function __construct(public readonly string $status, public readonly string $message)
    {
    }

    /**
     * A failing result: something that must be fixed.
     *
     * @param string $message Reason.
     * @return self
     */
    public static function fail(string $message): self
    {
        return new self(self::FAIL, $message);
    }

    /**
     * A passing result.
     *
     * @param string $message Reason.
     * @return self
     */
    public static function pass(string $message): self
    {
        return new self(self::PASS, $message);
    }

    /**
     * Whether $status is a known status value.
     *
     * @param string $status Candidate status.
     * @return bool
     */
    public static function isStatus(string $status): bool
    {
        return isset(self::RANK[$status]);
    }

    /**
     * A warning: worth attention, or the check could not be completed.
     *
     * @param string $message Reason.
     * @return self
     */
    public static function warn(string $message): self
    {
        return new self(self::WARN, $message);
    }

    /**
     * The most severe of the given statuses (PASS when there are none).
     *
     * @param array<int, string> $statuses Status values.
     * @return string
     */
    public static function worst(array $statuses): string
    {
        $worst = self::PASS;
        foreach ($statuses as $status) {
            if ((self::RANK[$status] ?? 0) > self::RANK[$worst]) {
                $worst = $status;
            }
        }

        return $worst;
    }
}
