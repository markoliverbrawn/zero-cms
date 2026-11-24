<?php

declare(strict_types=1);

/**
 * File: src/Support/SecurityScan/Finding.php
 * Architectural Purpose: Value object for one security-scan finding.
 * Package: Zero\Support\SecurityScan
 * Systemic Role: Carries a rule hit from the scanner to the CLI report and the baseline file.
 */

namespace Zero\Support\SecurityScan;

/**
 * Class Finding
 *
 * One rule hit at one source location, with a fingerprint that survives unrelated line shifts so a
 * baseline keeps matching after code above it moves.
 */
final class Finding
{
    public const HIGH = 'high';
    public const MEDIUM = 'medium';
    public const LOW = 'low';

    /**
     * @param string $rule Rule identifier, e.g. 'dynamic-php-path'.
     * @param string $severity One of the HIGH/MEDIUM/LOW constants.
     * @param string $file Path relative to the scan root.
     * @param int $line 1-based line number.
     * @param string $message What is wrong and what would make it safe.
     * @param string $code The trimmed source line, for the report and the fingerprint.
     */
    public function __construct(
        public readonly string $rule,
        public readonly string $severity,
        public readonly string $file,
        public readonly int $line,
        public readonly string $message,
        public readonly string $code
    ) {
    }

    /**
     * Stable identity for baselines: rule, file and the whitespace-normalised source line, but not
     * the line number.
     *
     * @return string
     */
    public function fingerprint(): string
    {
        return \hash('sha256', $this->rule . "\0" . $this->file . "\0" . (string) \preg_replace('/\s+/', ' ', $this->code));
    }

    /**
     * Array form for JSON output.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rule' => $this->rule,
            'severity' => $this->severity,
            'file' => $this->file,
            'line' => $this->line,
            'message' => $this->message,
            'code' => $this->code,
            'fingerprint' => $this->fingerprint(),
        ];
    }
}
