<?php

declare(strict_types=1);

/**
 * File: src/Support/SecurityScan/Rules/DynamicPhpPathRule.php
 * Architectural Purpose: Flags .php file paths built from unvalidated variables.
 * Package: Zero\Support\SecurityScan\Rules
 * Systemic Role: Catches template/include path traversal ('/blocks/' . $type . '.php').
 */

namespace Zero\Support\SecurityScan\Rules;

use Zero\Support\SecurityScan\FileRule;
use Zero\Support\SecurityScan\Finding;
use Zero\Support\SecurityScan\SourceFile;

/**
 * Class DynamicPhpPathRule
 *
 * Finds string expressions ending in '.php' that concatenate a variable, and reports each variable
 * that was neither passed through a validator nor read from code-defined data beforehand. Leading
 * directory variables (names ending Dir/Path/Root/Base/Directory/Folder) are treated as the base
 * and not checked: the risk is in the part that names the file.
 */
final class DynamicPhpPathRule implements FileRule
{
    public const ID = 'dynamic-php-path';

    /**
     * {@inheritdoc}
     */
    public function check(SourceFile $file): array
    {
        $findings = [];
        foreach ($file->tokens as $i => $token) {
            $endsPhp = ($token[0] === T_CONSTANT_ENCAPSED_STRING && \preg_match('/\.php[\'"]$/', $token[1]) === 1)
                || ($token[0] === T_ENCAPSED_AND_WHITESPACE && \str_ends_with($token[1], '.php'));
            // Only a literal that is concatenated onto ('...' . '/x.php') or interpolated into a
            // string names a built path; a standalone 'x.php' compared or defaulted to does not.
            $concatenated = $token[0] === T_ENCAPSED_AND_WHITESPACE || ($file->tokens[$i - 1][0] ?? null) === '.';
            if (!$endsPhp || !$concatenated) {
                continue;
            }

            [$from, $to] = $file->expressionAround($i);
            $reported = [];
            for ($k = $from; $k <= $to; $k++) {
                $var = $file->tokens[$k];
                // Static properties (self::$x / static::$x) are code-owned, not input.
                if ($var[0] !== T_VARIABLE || $var[1] === '$this' || isset($reported[$var[1]])
                    || ($file->tokens[$k - 1][0] ?? null) === T_DOUBLE_COLON) {
                    continue;
                }
                if (\preg_match('/(dir|path|root|base|directory|folder)$/i', $var[1]) === 1) {
                    continue;
                }
                if ($file->isValidatedBefore($var[1], $k) || $file->isAssignedFromTrustedSource($var[1], $k)) {
                    continue;
                }
                $reported[$var[1]] = true;
                $findings[] = new Finding(self::ID, Finding::HIGH, $file->path, $token[2],
                    "A .php path is built from {$var[1]}, which is not validated first: a value containing '../' would include any .php file on disk. Validate it (a registry lookup, or a pattern such as ^[A-Za-z0-9_-]+\$) before building the path.",
                    $file->lineText($token[2]));
            }
        }

        return $findings;
    }
}
