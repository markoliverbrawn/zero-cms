<?php

declare(strict_types=1);

/**
 * File: src/Modules/Security/HealthChecks/HttpProbe.php
 * Architectural Purpose: Minimal raw-cURL GET used by health checks that look outward.
 * Package: Zero\Modules\Security\HealthChecks
 * Systemic Role: Fetches release feeds and the site's own served headers without any SDK.
 */

namespace Zero\Modules\Security\HealthChecks;

/**
 * Class HttpProbe
 *
 * Performs a single GET with short timeouts and no redirect following, returning the status
 * code, lower-cased response headers and body.
 */
class HttpProbe
{
    /**
     * Fetch a URL.
     *
     * @param string $url Absolute http(s) URL.
     * @param int $timeoutSeconds Total timeout.
     * @return array{status: int, headers: array<string, string>, body: string}
     * @throws \RuntimeException When the request cannot be completed.
     */
    public static function get(string $url, int $timeoutSeconds = 10): array
    {
        $handle = \curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException("Could not initialise a request to {$url}.");
        }

        $headers = [];
        \curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => \min(5, $timeoutSeconds),
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_USERAGENT => 'ZeroCMS-HealthCheck',
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                $parts = \explode(':', $line, 2);
                if (\count($parts) === 2) {
                    $headers[\strtolower(\trim($parts[0]))] = \trim($parts[1]);
                }
                return \strlen($line);
            },
        ]);

        $body = \curl_exec($handle);
        if ($body === false) {
            $error = \curl_error($handle);
            \curl_close($handle);
            throw new \RuntimeException("Request to {$url} failed: {$error}");
        }
        $status = (int) \curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        \curl_close($handle);

        return ['status' => $status, 'headers' => $headers, 'body' => (string) $body];
    }

    /**
     * Fetch a URL and decode its JSON body.
     *
     * @param string $url Absolute https URL.
     * @return array<mixed>
     * @throws \RuntimeException When the request fails or the body is not a JSON object/array.
     */
    public static function getJson(string $url): array
    {
        $response = self::get($url);
        if ($response['status'] !== 200) {
            throw new \RuntimeException("{$url} returned HTTP {$response['status']}.");
        }
        $decoded = \json_decode($response['body'], true);
        if (!\is_array($decoded)) {
            throw new \RuntimeException("{$url} did not return JSON.");
        }

        return $decoded;
    }
}
