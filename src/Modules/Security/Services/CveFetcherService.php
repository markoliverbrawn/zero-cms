<?php
/**
 * Zero CMS - CVE Fetcher Service
 *
 * This service class communicates with the OSV (Open Source Vulnerabilities) API
 * to fetch recent security vulnerabilities and comparative advisories.
 *
 * PHP version 8.3
 *
 * @package    Zero\Modules\Security\Services
 * @author     Zero CMS Team
 * @copyright  2026 Zero CMS
 */

namespace Zero\Modules\Security\Services;

use Exception;

/**
 * Class CveFetcherService
 *
 * Handles HTTP requests to retrieve security advisories for major framework packages.
 */
class CveFetcherService
{
    private const API_ENDPOINT = 'https://api.osv.dev/v1/query';

    /**
     * Fetches recent framework vulnerability advisories via OSV JSON API.
     * Bypasses the network request by returning mocked data if the test suite is running.
     * 
     * @param string $package The Packagist library/package coordinate.
     * @param int $limit Max number of vulnerabilities to return.
     * @return array Array of retrieved vulnerability details.
     * @throws Exception On network or JSON decoding failures.
     */
    public static function fetchRecentAdvisories(string $package, int $limit = 3): array
    {
        // Network Isolation Mocking: Prevent HTTP requests and socket timeouts during test suite runs
        if (defined('TEST_SUITE_RUNNING') && TEST_SUITE_RUNNING) {
            return [
                [
                    'id' => 'GHSA-mock-1234',
                    'summary' => 'Mock Vulnerability for ' . $package,
                    'details' => 'This is a mocked security advisory returned during test execution to prevent network latency.'
                ]
            ];
        }

        $payload = json_encode([
            'package' => [
                'name' => $package,
                'ecosystem' => 'Packagist'
            ]
        ]);

        $ch = curl_init(self::API_ENDPOINT);
        if ($ch === false) {
            throw new Exception("Failed to initialize cURL handle for CVE fetch.");
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);

        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("Network connection failed during CVE fetch for '{$package}': Code {$errno} - {$error}");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Malformed JSON response during CVE fetch: " . json_last_error_msg());
        }

        return array_slice($data['vulns'] ?? [], 0, $limit);
    }
}
