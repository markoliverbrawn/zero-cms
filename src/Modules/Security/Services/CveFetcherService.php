<?php

namespace Zero\Modules\Security\Services;

use Exception;

class CveFetcherService
{
    private const API_ENDPOINT = 'https://api.osv.dev/v1/query';

    /**
     * Fetches recent framework vulnerability advisories via OSV JSON API.
     * 
     * @param string $package The Packagist library/package coordinate.
     * @param int $limit Max number of vulnerabilities to return.
     * @return array Array of retrieved vulnerability details.
     * @throws Exception On network or JSON decoding failures.
     */
    public static function fetchRecentAdvisories(string $package, int $limit = 3): array
    {
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
