<?php

declare(strict_types=1);

/**
 * File: src/Support/SecurityScan/Rules/ObjectInjectionRule.php
 * Architectural Purpose: Flags unserialize() calls that can create objects.
 * Package: Zero\Support\SecurityScan\Rules
 * Systemic Role: Blocks PHP object injection.
 */

namespace Zero\Support\SecurityScan\Rules;

use Zero\Support\SecurityScan\FileRule;
use Zero\Support\SecurityScan\Finding;
use Zero\Support\SecurityScan\SourceFile;

/**
 * Class ObjectInjectionRule
 *
 * Reports calls to the unserialize() function unless they pass an allowed_classes option.
 */
final class ObjectInjectionRule implements FileRule
{
    public const ID = 'object-injection';

    /**
     * {@inheritdoc}
     */
    public function check(SourceFile $file): array
    {
        $findings = [];
        foreach ($file->tokens as $i => $token) {
            if (!$file->isName($i, 'unserialize') || ($file->tokens[$i + 1][0] ?? null) !== '('
                || \in_array($file->tokens[$i - 1][0] ?? null, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true)) {
                continue;
            }
            $close = $file->matchingClose($i + 1);
            $hasAllowedClasses = false;
            for ($k = $i + 2; $k < $close; $k++) {
                if (\str_contains($file->tokens[$k][1], 'allowed_classes')) {
                    $hasAllowedClasses = true;
                    break;
                }
            }
            if (!$hasAllowedClasses) {
                $findings[] = new Finding(self::ID, Finding::HIGH, $file->path, $token[2],
                    "unserialize() without ['allowed_classes' => false] can instantiate arbitrary classes from its input. Use json_decode(), or pass allowed_classes.",
                    $file->lineText($token[2]));
            }
        }

        return $findings;
    }
}
