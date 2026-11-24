<?php

declare(strict_types=1);

/**
 * File: src/Support/SecurityScan/Rules/TimingCompareRule.php
 * Architectural Purpose: Flags secrets compared with == / === instead of hash_equals().
 * Package: Zero\Support\SecurityScan\Rules
 * Systemic Role: Keeps token, signature and key comparisons constant-time.
 */

namespace Zero\Support\SecurityScan\Rules;

use Zero\Support\SecurityScan\FileRule;
use Zero\Support\SecurityScan\Finding;
use Zero\Support\SecurityScan\SourceFile;

/**
 * Class TimingCompareRule
 *
 * Reports ==, ===, != and !== where one operand is a variable named like a secret (token,
 * signature, secret, csrf, api key, hmac) and the other is not a literal. Each comparison is judged
 * on its own; a hash_equals() elsewhere in the file does not excuse it.
 */
final class TimingCompareRule implements FileRule
{
    public const ID = 'timing-compare';

    /**
     * {@inheritdoc}
     */
    public function check(SourceFile $file): array
    {
        $findings = [];
        $tokens = $file->tokens;
        $operators = [T_IS_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL, T_IS_NOT_IDENTICAL];
        foreach ($tokens as $i => $token) {
            if (!\in_array($token[0], $operators, true)) {
                continue;
            }
            $left = $tokens[$i - 1] ?? null;
            $right = $tokens[$i + 1] ?? null;
            foreach ([[$left, $right], [$right, $left]] as [$operand, $other]) {
                if ($operand === null || $operand[0] !== T_VARIABLE
                    || \preg_match('/(token|signature|secret|csrf|api_?key|hmac)/i', $operand[1]) !== 1) {
                    continue;
                }
                if ($other === null || $this->isLiteral($other)) {
                    continue;
                }
                $findings[] = new Finding(self::ID, Finding::MEDIUM, $file->path, $token[2],
                    "{$operand[1]} is compared with a non-constant-time operator. Use hash_equals(\$known, \$supplied), after checking both are strings.",
                    $file->lineText($token[2]));
                break;
            }
        }

        return $findings;
    }

    /**
     * Whether a token is a literal (string, number, true/false/null).
     *
     * @param array{0: int|string, 1: string, 2: int} $token
     * @return bool
     */
    private function isLiteral(array $token): bool
    {
        return \in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER], true)
            || ($token[0] === T_STRING && \in_array(\strtolower($token[1]), ['true', 'false', 'null'], true));
    }
}
