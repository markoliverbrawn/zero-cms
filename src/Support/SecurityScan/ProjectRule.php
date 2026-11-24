<?php

declare(strict_types=1);

/**
 * File: src/Support/SecurityScan/ProjectRule.php
 * Architectural Purpose: Contract for a rule that needs every scanned file at once.
 * Package: Zero\Support\SecurityScan
 * Systemic Role: Implemented by cross-file checks such as route-to-controller authorisation.
 */

namespace Zero\Support\SecurityScan;

/**
 * Interface ProjectRule
 */
interface ProjectRule
{
    /**
     * Whether checkProject() needs this file. The scanner keeps only files some project rule
     * needs, so large files no rule needs (generated seed data, say) are freed once checked.
     *
     * @param SourceFile $file Source.
     * @return bool
     */
    public function needs(SourceFile $file): bool;

    /**
     * Inspect all scanned files together and return findings.
     *
     * @param array<string, SourceFile> $files Files keyed by relative path.
     * @return array<int, Finding>
     */
    public function checkProject(array $files): array;
}
