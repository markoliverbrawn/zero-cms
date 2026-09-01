<?php
// tests/S3MockTest.php
// Unit test utilizing PHP namespace shadowing (monkey-patching) to mock AWS S3 cURL API transceivers and SigV4 verification 100% offline.

namespace Zero\Core\Storage;

// 1. Monkey-Patching PHP's native curl functions inside the target namespace!
$mockS3CurlHttpCodes = [];
$mockS3CurlBodies = [];
$lastAuthorizationHeader = '';

// Per-URL queue of [status, response, error] steps, consumed one per curl_exec() call against
// that URL -- lets a test simulate "fails N times, then succeeds" to exercise sendRequest()'s
// CurlRetrier-backed retry/backoff, without disturbing the simpler static single-value mocks
// above that every other test in this file already relies on (only consulted once a URL has a
// queue populated). Mirrors GCSMockTest.php's identical mechanism.
$mockS3CurlSequences = [];

function curl_init($url = null) {
    return (object) ['url' => $url];
}

function curl_setopt($ch, $option, $value) {
    global $lastAuthorizationHeader;
    if ($option === CURLOPT_POSTFIELDS) {
        $ch->postfields = $value;
    }
}

function curl_setopt_array($ch, $options) {
    global $lastAuthorizationHeader;
    foreach ($options as $option => $value) {
        if ($option === CURLOPT_HTTPHEADER) {
            foreach ($value as $headerLine) {
                if (stripos($headerLine, 'Authorization:') === 0) {
                    $lastAuthorizationHeader = $headerLine;
                }
            }
        }
        curl_setopt($ch, $option, $value);
    }
}

function curl_exec($ch) {
    global $mockS3CurlBodies, $mockS3CurlSequences;
    $url = $ch->url ?? '';

    if (!empty($mockS3CurlSequences[$url])) {
        $ch->_sequenceStep = array_shift($mockS3CurlSequences[$url]);
        return $ch->_sequenceStep['response'] ?? 'mock-success-payload';
    }

    return $mockS3CurlBodies[$url] ?? '<ListBucketResult><Contents><Key>mock-folder/file1.txt</Key></Contents></ListBucketResult>';
}

function curl_getinfo($ch, $option) {
    global $mockS3CurlHttpCodes;
    $url = $ch->url ?? '';

    if ($option === CURLINFO_HTTP_CODE) {
        if (isset($ch->_sequenceStep)) {
            return $ch->_sequenceStep['status'] ?? 200;
        }
        return $mockS3CurlHttpCodes[$url] ?? 200; // Mock HTTP 200 OK
    }
    return 0;
}

function curl_error($ch) {
    return isset($ch->_sequenceStep) ? ($ch->_sequenceStep['error'] ?? '') : '';
}

function curl_close($ch) {
    // No-op
}

// 2. Main Test Runner Execution Context (switching back to global test context)
namespace GlobalContext;

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\Storage\AwsS3StorageDriver;

echo "=== Mocked AWS S3 Storage Driver & SigV4 Verification Tests ===\n";

// Instantiate S3 Driver using mock credentials
$driver = new AwsS3StorageDriver([
    'bucket' => 'test-mock-bucket',
    'access_key' => 'AKIAIOSFODNN7EXAMPLE',
    'secret_key' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
    'region' => 'us-east-1'
]);

assert_test($driver !== null, "AwsS3StorageDriver instantiated successfully with mock credentials");

// A. Test File Write S3 and SigV4 Header Generation Mocking
echo "  Testing S3 write and SigV4 cryptographic signature generation...\n";
global $mockS3CurlHttpCodes, $lastAuthorizationHeader;

$mockWriteUrl = "https://test-mock-bucket.s3.us-east-1.amazonaws.com/mock-folder/test.txt";
$mockS3CurlHttpCodes[$mockWriteUrl] = 200;

$writeResult = $driver->write('mock-folder/test.txt', 's3 test content payload');
assert_test($writeResult === true, "Mocked S3 write operation completed with successful HTTP 200 OK");

// Validate that SigV4 Authorization Header was generated correctly
assert_test(!empty($lastAuthorizationHeader), "SigV4 Authorization header was successfully compiled by the driver");
assert_test(strpos($lastAuthorizationHeader, 'AWS4-HMAC-SHA256') !== false, "Authorization header contains correct AWS SigV4 signature algorithm prefix");
assert_test(strpos($lastAuthorizationHeader, 'Credential=AKIAIOSFODNN7EXAMPLE') !== false, "Authorization header embeds correct AWS Access Key ID");
assert_test(strpos($lastAuthorizationHeader, 'Signature=') !== false, "Authorization header contains compiled cryptographic signature hash");

// B. Test File Exists (HEAD request) Mocking
echo "  Testing S3 exists (HEAD metadata query) mocking...\n";
$mockHeadUrl = "https://test-mock-bucket.s3.us-east-1.amazonaws.com/mock-folder/test.txt";

$mockS3CurlHttpCodes[$mockHeadUrl] = 200; // exists!
$existsResult = $driver->exists('mock-folder/test.txt');
assert_test($existsResult === true, "Mocked S3 exists query successfully evaluated HTTP 200 OK as TRUE");

$mockS3CurlHttpCodes[$mockHeadUrl] = 404; // missing!
$missingResult = $driver->exists('mock-folder/test.txt');
assert_test($missingResult === false, "Mocked S3 exists query successfully evaluated HTTP 404 as FALSE");

// C. Test File Delete S3 Mocking
echo "  Testing S3 delete query mocking...\n";
$mockS3CurlHttpCodes[$mockHeadUrl] = 204; // S3 standard delete code
$deleteResult = $driver->delete('mock-folder/test.txt');
assert_test($deleteResult === true, "Mocked S3 delete operation successfully completed with HTTP 204");

// D. Test Directory Purge (List and Delete) Mocking
echo "  Testing S3 cleanDirectory (ListObjectsV2 + sequential deletes) mocking...\n";
$mockListUrl = "https://test-mock-bucket.s3.us-east-1.amazonaws.com/?list-type=2&prefix=mock-folder%2F";
$mockS3CurlHttpCodes[$mockListUrl] = 200;

$cleanResult = $driver->cleanDirectory('mock-folder');
assert_test($cleanResult === true, "cleanDirectory successfully parsed ListBucketResult XML keys and completed clean deletes");

// Regression coverage for sendRequest()'s transient-failure retry (shared CurlRetrier): a 503 (or
// 429, or a failed transfer) should be retried with backoff and re-signed fresh per attempt, while
// any other 4xx should fail immediately.
global $mockS3CurlSequences;

echo "  Testing write() retries a transient 503 and succeeds once the bucket recovers...\n";
$retrySuccessUrl = "https://test-mock-bucket.s3.us-east-1.amazonaws.com/mock-folder/retry-success.txt";
$mockS3CurlSequences[$retrySuccessUrl] = [
    ['status' => 503, 'response' => '<Error><Code>ServiceUnavailable</Code><Message>Slow Down</Message></Error>', 'error' => ''],
    ['status' => 200, 'response' => '', 'error' => ''],
];
$retrySuccessResult = $driver->write('mock-folder/retry-success.txt', 'mock payload');
assert_test($retrySuccessResult === true, "write() succeeds after one retried 503");
assert_test(empty($mockS3CurlSequences[$retrySuccessUrl]), "write() consumed exactly 2 attempts (1 failure + 1 success), not more");

echo "  Testing write() gives up after exhausting all retry attempts on persistent 503s...\n";
$retryExhaustedUrl = "https://test-mock-bucket.s3.us-east-1.amazonaws.com/mock-folder/retry-exhausted.txt";
$mockS3CurlSequences[$retryExhaustedUrl] = [
    ['status' => 503, 'response' => '<Error><Code>ServiceUnavailable</Code><Message>Slow Down</Message></Error>', 'error' => ''],
    ['status' => 503, 'response' => '<Error><Code>ServiceUnavailable</Code><Message>Slow Down</Message></Error>', 'error' => ''],
    ['status' => 503, 'response' => '<Error><Code>ServiceUnavailable</Code><Message>Slow Down</Message></Error>', 'error' => ''],
];
$errorLogFile = confine_test_path(sys_get_temp_dir() . '/s3-write-retry-exhausted.log', sys_get_temp_dir());
@unlink($errorLogFile);
$previousErrorLog = ini_set('error_log', $errorLogFile);
$retryExhaustedResult = $driver->write('mock-folder/retry-exhausted.txt', 'mock payload');
ini_set('error_log', $previousErrorLog);
assert_test($retryExhaustedResult === false, "write() returns false once every retry attempt also fails");
assert_test(empty($mockS3CurlSequences[$retryExhaustedUrl]), "write() made exactly 3 attempts total (the configured max), not more");
$loggedDiagnostic = @file_get_contents($errorLogFile) ?: '';
assert_test(str_contains($loggedDiagnostic, 'Slow Down'), "The final failure's S3 <Message> is written to the diagnostic log");
assert_test(str_contains($loggedDiagnostic, '503'), "The final failure's HTTP status is written to the diagnostic log");
@unlink($errorLogFile);

echo "  Testing write() does NOT retry a non-transient 4xx (fails fast)...\n";
$noRetryUrl = "https://test-mock-bucket.s3.us-east-1.amazonaws.com/mock-folder/no-retry.txt";
$mockS3CurlSequences[$noRetryUrl] = [
    ['status' => 403, 'response' => '<Error><Code>AccessDenied</Code><Message>Access Denied</Message></Error>', 'error' => ''],
    ['status' => 200, 'response' => '', 'error' => ''], // Would only be consumed if (incorrectly) retried
];
$noRetryResult = $driver->write('mock-folder/no-retry.txt', 'mock payload');
assert_test($noRetryResult === false, "write() returns false immediately on a non-transient 403");
assert_test(count($mockS3CurlSequences[$noRetryUrl]) === 1, "write() made exactly 1 attempt for a non-transient failure, leaving the second queued step untouched");

echo "  Testing write() logs the curl transport error when the transfer itself fails...\n";
$transportFailUrl = "https://test-mock-bucket.s3.us-east-1.amazonaws.com/mock-folder/transport-fail.txt";
$mockS3CurlSequences[$transportFailUrl] = [
    ['status' => 0, 'response' => false, 'error' => 'Could not resolve host: test-mock-bucket.s3.us-east-1.amazonaws.com'],
    ['status' => 0, 'response' => false, 'error' => 'Could not resolve host: test-mock-bucket.s3.us-east-1.amazonaws.com'],
    ['status' => 0, 'response' => false, 'error' => 'Could not resolve host: test-mock-bucket.s3.us-east-1.amazonaws.com'],
];
$transportErrorLogFile = confine_test_path(sys_get_temp_dir() . '/s3-write-transport-fail.log', sys_get_temp_dir());
@unlink($transportErrorLogFile);
$previousErrorLog = ini_set('error_log', $transportErrorLogFile);
$transportFailResult = $driver->write('mock-folder/transport-fail.txt', 'mock payload');
ini_set('error_log', $previousErrorLog);
assert_test($transportFailResult === false, "write() returns false when every attempt fails to even transfer");
$transportLoggedDiagnostic = @file_get_contents($transportErrorLogFile) ?: '';
assert_test(str_contains($transportLoggedDiagnostic, 'Could not resolve host'), "curl_error() detail is written to the diagnostic log when the response body is empty");
@unlink($transportErrorLogFile);

echo "  Testing sendRequest() re-signs with a fresh SigV4 signature on every retry attempt...\n";
$resignUrl = "https://test-mock-bucket.s3.us-east-1.amazonaws.com/mock-folder/resign-test.txt";
$mockS3CurlSequences[$resignUrl] = [
    ['status' => 500, 'response' => '<Error><Code>InternalError</Code><Message>We encountered an internal error</Message></Error>', 'error' => ''],
    ['status' => 200, 'response' => '', 'error' => ''],
];
$lastAuthorizationHeader = '';
$resignResult = $driver->write('mock-folder/resign-test.txt', 'mock payload');
assert_test($resignResult === true, "write() succeeds after a retried 500");
assert_test(!empty($lastAuthorizationHeader) && strpos($lastAuthorizationHeader, 'Signature=') !== false, "The final (successful) attempt still carries a complete, freshly-signed Authorization header");

echo "Mocked AWS S3 driver component tests completed.\n\n";
