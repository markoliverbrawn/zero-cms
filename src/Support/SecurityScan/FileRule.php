<?php

declare(strict_types=1);

/**
 * File: src/Support/SecurityScan/FileRule.php
 * Architectural Purpose: Contract for a rule that inspects one file at a time.
 * Package: Zero\Support\SecurityScan
 * Systemic Role: Implemented by each per-file check the Scanner runs.
 */

namespace Zero\Support\SecurityScan;

/**
 * Interface FileRule
 */
interface FileRule
{
    /**
     * Inspect one file and return its findings (ignore comments are applied by the Scanner).
     *
     * @param SourceFile $file Tokenised file.
     * @return array<int, Finding>
     */
    public function check(SourceFile $file): array;
}
