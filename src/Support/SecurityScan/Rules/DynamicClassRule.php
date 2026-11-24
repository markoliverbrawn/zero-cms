<?php

declare(strict_types=1);

/**
 * File: src/Support/SecurityScan/Rules/DynamicClassRule.php
 * Architectural Purpose: Flags classes instantiated or called by a name held in a variable.
 * Package: Zero\Support\SecurityScan\Rules
 * Systemic Role: Catches request- or data-chosen class names reaching `new` (Page.controller).
 */

namespace Zero\Support\SecurityScan\Rules;

use Zero\Support\SecurityScan\FileRule;
use Zero\Support\SecurityScan\Finding;
use Zero\Support\SecurityScan\SourceFile;

/**
 * Class DynamicClassRule
 *
 * Reports `new $class` and `$class::member` where $class was neither validated (e.g.
 * is_subclass_of, an is*() allow-list check) nor read from code-defined data such as a registry
 * getter, class constant or static property. class_exists() alone is not validation: it says
 * nothing about which class an attacker picked.
 */
final class DynamicClassRule implements FileRule
{
    public const ID = 'dynamic-class';

    /**
     * {@inheritdoc}
     */
    public function check(SourceFile $file): array
    {
        $findings = [];
        $tokens = $file->tokens;
        foreach ($tokens as $i => $token) {
            $var = null;
            if ($token[0] === T_NEW && ($tokens[$i + 1][0] ?? null) === T_VARIABLE) {
                $var = $tokens[$i + 1];
            } elseif ($token[0] === T_VARIABLE && ($tokens[$i + 1][0] ?? null) === T_DOUBLE_COLON
                && ($tokens[$i - 1][0] ?? null) !== T_OBJECT_OPERATOR) {
                $var = $token;
            }
            if ($var === null || $var[1] === '$this') {
                continue;
            }
            if ($file->isValidatedBefore($var[1], $i) || $file->isAssignedFromTrustedSource($var[1], $i)) {
                continue;
            }
            $findings[] = new Finding(self::ID, Finding::HIGH, $file->path, $var[2],
                "Class named by {$var[1]} is used without validating which class it is. If the name can come from a request or stored data, an attacker chooses what gets instantiated. Check it against an allow-list or the expected interface (is_subclass_of) first.",
                $file->lineText($var[2]));
        }

        return $findings;
    }
}
