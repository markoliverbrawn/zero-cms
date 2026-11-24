<?php

declare(strict_types=1);

/**
 * File: src/Support/SecurityScan/Rules/HostHeaderLinkRule.php
 * Architectural Purpose: Flags request-host-derived URLs in code that sends email.
 * Package: Zero\Support\SecurityScan\Rules
 * Systemic Role: Catches password-reset / welcome link poisoning via Host or X-Forwarded-Host.
 */

namespace Zero\Support\SecurityScan\Rules;

use Zero\Support\SecurityScan\FileRule;
use Zero\Support\SecurityScan\Finding;
use Zero\Support\SecurityScan\SourceFile;

/**
 * Class HostHeaderLinkRule
 *
 * In any file that calls Emailer::send(), reports every read of the request host
 * (Security::resolveTrustedHost(), HTTP_HOST, HTTP_X_FORWARDED_HOST). Links in email must be built
 * from the site's stored domain (Site::absoluteUrl()), which no request header can change.
 */
final class HostHeaderLinkRule implements FileRule
{
    public const ID = 'host-header-link';

    /**
     * {@inheritdoc}
     */
    public function check(SourceFile $file): array
    {
        $sendsMail = false;
        foreach ($file->tokens as $i => $token) {
            if ($file->isName($i, 'Emailer') && ($file->tokens[$i + 1][0] ?? null) === T_DOUBLE_COLON && $file->isName($i + 2, 'send')) {
                $sendsMail = true;
                break;
            }
        }
        if (!$sendsMail) {
            return [];
        }

        $findings = [];
        foreach ($file->tokens as $i => $token) {
            $hostRead = ($file->isName($i, 'resolveTrustedHost') && ($file->tokens[$i + 1][0] ?? null) === '(')
                || ($token[0] === T_CONSTANT_ENCAPSED_STRING && \preg_match('/^[\'"](HTTP_HOST|HTTP_X_FORWARDED_HOST)[\'"]$/', $token[1]) === 1);
            if ($hostRead) {
                $findings[] = new Finding(self::ID, Finding::HIGH, $file->path, $token[2],
                    'This file sends email and reads the request host. A link built from Host / X-Forwarded-Host can be pointed at an attacker\'s server (e.g. "victim.com:@evil.com"), leaking reset tokens. Build emailed URLs from the stored site domain: $site->absoluteUrl($path).',
                    $file->lineText($token[2]));
            }
        }

        return $findings;
    }
}
