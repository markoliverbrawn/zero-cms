<?php

declare(strict_types=1);

/**
 * File: src/Support/SecurityScan/Rules/UnscopedLookupRule.php
 * Architectural Purpose: Flags record lookups by id in controllers that skip tenant scoping.
 * Package: Zero\Support\SecurityScan\Rules
 * Systemic Role: Catches cross-tenant IDOR (Model::find($id) on a request-supplied id).
 */

namespace Zero\Support\SecurityScan\Rules;

use Zero\Support\SecurityScan\FileRule;
use Zero\Support\SecurityScan\Finding;
use Zero\Support\SecurityScan\SourceFile;

/**
 * Class UnscopedLookupRule
 *
 * In controllers, reports ::find() / ::findTrashed() called with a variable id. Those lookups are
 * not tenant-scoped, so an id from another site loads that site's record; use
 * findInCurrentSite($id[, $includeTrashed]) instead.
 */
final class UnscopedLookupRule implements FileRule
{
    public const ID = 'unscoped-lookup';

    /**
     * {@inheritdoc}
     */
    public function check(SourceFile $file): array
    {
        if (\preg_match('#/Controllers/#', '/' . $file->path) !== 1) {
            return [];
        }

        $findings = [];
        foreach ($file->tokens as $i => $token) {
            if ($token[0] !== T_DOUBLE_COLON || !$file->isName($i + 1, 'find', 'findTrashed') || ($file->tokens[$i + 2][0] ?? null) !== '(') {
                continue;
            }
            $close = $file->matchingClose($i + 2);
            for ($k = $i + 3; $k < $close; $k++) {
                if ($file->tokens[$k][0] === T_VARIABLE) {
                    $findings[] = new Finding(self::ID, Finding::HIGH, $file->path, $token[2],
                        'Record looked up by id without tenant scoping: an id belonging to another site loads that site\'s record. Use findInCurrentSite() (or check the record\'s site_id) for any id that can come from a request.',
                        $file->lineText($token[2]));
                    break;
                }
            }
        }

        return $findings;
    }
}
