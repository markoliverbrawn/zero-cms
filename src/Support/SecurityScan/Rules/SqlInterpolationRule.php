<?php

declare(strict_types=1);

/**
 * File: src/Support/SecurityScan/Rules/SqlInterpolationRule.php
 * Architectural Purpose: Flags variables interpolated into DB::query() SQL text.
 * Package: Zero\Support\SecurityScan\Rules
 * Systemic Role: Keeps request data on bound placeholders, never in the SQL string.
 */

namespace Zero\Support\SecurityScan\Rules;

use Zero\Support\SecurityScan\FileRule;
use Zero\Support\SecurityScan\Finding;
use Zero\Support\SecurityScan\SourceFile;

/**
 * Class SqlInterpolationRule
 *
 * Reports variables in DB::query()'s first argument unless they are the conventional names of
 * SQL fragments assembled from placeholders ($sql, $where, $placeholders, ...), or were checked
 * by a validator (Security::isSafeSqlIdentifier/isKnownSqlTable/isKnownSqlColumn, in_array, ...)
 * or read from code-defined data first.
 */
final class SqlInterpolationRule implements FileRule
{
    public const ID = 'sql-interpolation';

    /**
     * Names conventionally holding placeholder-only SQL fragments built in code.
     */
    private const FRAGMENT_NAMES = [
        '$sql', '$query', '$where', '$whereSql', '$selectSql', '$deleteSql', '$updateSql', '$totalSql',
        '$totalQuery', '$countSql', '$placeholders', '$params', '$values', '$limit', '$offset',
    ];

    /**
     * {@inheritdoc}
     */
    public function check(SourceFile $file): array
    {
        $findings = [];
        $tokens = $file->tokens;
        foreach ($tokens as $i => $token) {
            if (!$file->isName($i, 'DB') || ($tokens[$i + 1][0] ?? null) !== T_DOUBLE_COLON || !$file->isName($i + 2, 'query')
                || ($tokens[$i + 3][0] ?? null) !== '(') {
                continue;
            }
            $close = $file->matchingClose($i + 3);
            $depth = 0;
            for ($k = $i + 4; $k < $close; $k++) {
                $id = $tokens[$k][0];
                if ($id === '(' || $id === '[') {
                    $depth++;
                } elseif ($id === ')' || $id === ']') {
                    $depth--;
                } elseif ($id === ',' && $depth === 0) {
                    break; // end of the SQL argument
                }
                // Static properties (static::$tableName) are code-owned, not input.
                if ($id !== T_VARIABLE || \in_array($tokens[$k][1], self::FRAGMENT_NAMES, true)
                    || ($tokens[$k - 1][0] ?? null) === T_DOUBLE_COLON) {
                    continue;
                }
                $var = $tokens[$k][1];
                if ($file->isValidatedBefore($var, $i) || $file->isAssignedFromTrustedSource($var, $i)) {
                    continue;
                }
                $findings[] = new Finding(self::ID, Finding::HIGH, $file->path, $token[2],
                    "{$var} is interpolated into SQL text. Pass values as bound '?' parameters; identifiers must be checked first (Security::isSafeSqlIdentifier / isKnownSqlTable / isKnownSqlColumn).",
                    $file->lineText($token[2]));
                break;
            }
        }

        return $findings;
    }
}
