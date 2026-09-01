<?php

declare(strict_types=1);

/**
 * File: src/Core/Storage/CurlRetrier.php
 * Architectural Purpose: Core bootstrapping, system environment configuration, and utility class of the framework.
 * Package: Zero\Core\Storage
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Core\Storage;

/**
 * Class CurlRetrier
 *
 * Shared transient-failure retry-with-backoff policy for storage drivers that hand-roll raw curl
 * HTTP calls against a cloud API (GoogleCloudStorageDriver, AwsS3StorageDriver) rather than a
 * vendor SDK. Both need the same policy -- retry a 429/5xx or a failed transfer, fail fast on any
 * other 4xx since a retry can't fix a bad request/auth/permission error -- so it lives here once
 * instead of being copy-pasted into each driver.
 *
 * Deliberately declared in the same Zero\Core\Storage namespace as those drivers, and calls
 * curl_exec()/curl_getinfo()/curl_error()/curl_close() unqualified rather than root-namespaced
 * (unlike this codebase's usual \foo() style for built-ins) -- GCSMockTest.php/S3MockTest.php
 * monkey-patch those specific functions by declaring same-named functions inside this same
 * namespace, relying on PHP resolving an unqualified call in the current namespace before falling
 * back to the global one. A root-namespaced \curl_exec() call, or one made from a different
 * namespace, would skip the mock entirely and hit the real function.
 */
class CurlRetrier
{
    /**
     * Execute $buildRequest() up to $maxAttempts times, retrying only transient failures (HTTP
     * 429/5xx, or curl_exec() returning false) with exponential backoff between attempts.
     *
     * @param callable $buildRequest () => resource|\CurlHandle  Builds and returns a FRESH,
     *        fully-configured curl handle for one attempt. Called again on every retry rather
     *        than reused, since (a) a curl handle that already ran can't be safely re-executed,
     *        and (b) a signed request (e.g. AWS SigV4, whose signature covers a request
     *        timestamp) must be rebuilt and re-signed per attempt to stay valid.
     * @param int $maxAttempts Total attempts including the first, before giving up.
     * @param int $baseDelayMs Delay before the first retry; doubles on each subsequent retry.
     * @return array{status: int, body: string|false, error: string} The final attempt's result,
     *         whichever attempt that was -- 'body' mirrors curl_exec()'s raw return (false on a
     *         failed transfer), 'error' is curl_error() for that same attempt.
     */
    public static function execute(callable $buildRequest, int $maxAttempts = 3, int $baseDelayMs = 200): array
    {
        $delayMs = $baseDelayMs;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $ch = $buildRequest();
            $body = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $isTransient = ($body === false) || $status === 429 || $status >= 500;
            if (!$isTransient || $attempt === $maxAttempts) {
                return ['status' => $status, 'body' => $body, 'error' => $error];
            }

            \usleep($delayMs * 1000);
            $delayMs *= 2;
        }

        // Unreachable -- the loop above always returns by its last iteration.
        return ['status' => 0, 'body' => false, 'error' => ''];
    }
}
