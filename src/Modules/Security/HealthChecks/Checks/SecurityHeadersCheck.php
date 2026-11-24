<?php

declare(strict_types=1);

/**
 * File: src/Modules/Security/HealthChecks/Checks/SecurityHeadersCheck.php
 * Architectural Purpose: Checks the site actually serves its security response headers.
 * Package: Zero\Modules\Security\HealthChecks\Checks
 * Systemic Role: One of the deployment health checks run by HealthCheckRunner.
 */

namespace Zero\Modules\Security\HealthChecks\Checks;

use Zero\Models\Site;
use Zero\Modules\Security\HealthChecks\CheckResult;
use Zero\Modules\Security\HealthChecks\HealthCheck;
use Zero\Modules\Security\HealthChecks\HttpProbe;

/**
 * Class SecurityHeadersCheck
 *
 * Fetches the site's home page over HTTPS (falling back to HTTP) and inspects the response
 * headers as a browser would receive them, after any proxy or CDN in front of the app.
 */
class SecurityHeadersCheck implements HealthCheck
{
    /**
     * Decide the result from the served headers.
     *
     * @param array<string, string> $headers Lower-cased response headers.
     * @param bool $isHttps Whether the headers came from an HTTPS response.
     * @param bool $isProduction Whether the installation runs in production.
     * @return CheckResult
     */
    public static function evaluate(array $headers, bool $isHttps, bool $isProduction): CheckResult
    {
        $missingRequired = [];
        $missingRecommended = [];
        if (!isset($headers['content-security-policy'])) {
            $missingRequired[] = 'Content-Security-Policy';
        }
        if (\strtolower($headers['x-content-type-options'] ?? '') !== 'nosniff') {
            $missingRequired[] = 'X-Content-Type-Options: nosniff';
        }
        if ($isHttps && !isset($headers['strict-transport-security'])) {
            $missingRequired[] = 'Strict-Transport-Security';
        }
        if (!isset($headers['x-frame-options']) && \stripos($headers['content-security-policy'] ?? '', 'frame-ancestors') === false) {
            $missingRecommended[] = 'X-Frame-Options (or CSP frame-ancestors)';
        }
        if (!isset($headers['referrer-policy'])) {
            $missingRecommended[] = 'Referrer-Policy';
        }

        if ($missingRequired !== []) {
            return CheckResult::fail('Missing security headers: ' . \implode(', ', \array_merge($missingRequired, $missingRecommended)) . '.');
        }
        if (!$isHttps && $isProduction) {
            return CheckResult::fail('The site did not answer over HTTPS.');
        }
        if ($missingRecommended !== []) {
            return CheckResult::warn('Missing recommended headers: ' . \implode(', ', $missingRecommended) . '.');
        }

        return CheckResult::pass($isHttps ? 'All security headers are served over HTTPS.' : 'All security headers are served (HTTP only).');
    }

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'security_headers';
    }

    /**
     * {@inheritdoc}
     */
    public function label(): string
    {
        return 'Served security headers';
    }

    /**
     * {@inheritdoc}
     */
    public function run(Site $site, bool $isProduction): CheckResult
    {
        $domain = (string) ($site->domain ?? '');
        if ($domain === '') {
            return CheckResult::warn('The site has no domain to fetch.');
        }

        $errors = [];
        foreach (['https', 'http'] as $scheme) {
            try {
                $response = HttpProbe::get("{$scheme}://{$domain}/");
                return self::evaluate($response['headers'], $scheme === 'https', $isProduction);
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        return CheckResult::warn('Could not fetch the site to inspect its headers: ' . \implode('; ', $errors));
    }
}
