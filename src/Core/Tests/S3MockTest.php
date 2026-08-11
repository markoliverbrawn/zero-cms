<?php
// tests/S3MockTest.php
// Unit test utilizing PHP namespace shadowing (monkey-patching) to mock AWS S3 cURL API transceivers and SigV4 verification 100% offline.

namespace Zero\Core\Storage;

// 1. Monkey-Patching PHP's native curl functions inside the target namespace!
$mockS3CurlHttpCodes = [];
$mockS3CurlBodies = [];
$lastAuthorizationHeader = '';

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
    global $mockS3CurlBodies;
    $url = $ch->url ?? '';
    return $mockS3CurlBodies[$url] ?? '<ListBucketResult><Contents><Key>mock-folder/file1.txt</Key></Contents></ListBucketResult>';
}

function curl_getinfo($ch, $option) {
    global $mockS3CurlHttpCodes;
    $url = $ch->url ?? '';
    
    if ($option === CURLINFO_HTTP_CODE) {
        return $mockS3CurlHttpCodes[$url] ?? 200; // Mock HTTP 200 OK
    }
    return 0;
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

echo "Mocked AWS S3 driver component tests completed.\n\n";
