<?php
// tests/GCSMockTest.php
// Unit test utilizing PHP namespace shadowing (monkey-patching) to mock GCP/GCS cURL API transceivers 100% offline.

namespace Zero\Core\Storage;

// 1. Monkey-Patching PHP's native curl functions inside the target namespace!
$mockCurlResponses = [];
$mockCurlHttpCodes = [];

function curl_init($url = null) {
    return (object) ['url' => $url];
}

function curl_setopt($ch, $option, $value) {
    if ($option === CURLOPT_POSTFIELDS) {
        $ch->postfields = $value;
    }
}

function curl_setopt_array($ch, $options) {
    foreach ($options as $option => $value) {
        curl_setopt($ch, $option, $value);
    }
}

function curl_exec($ch) {
    global $mockCurlResponses;
    $url = $ch->url ?? '';
    
    // Intercept Google OAuth token request
    if (strpos($url, '/token') !== false) {
        return json_encode([
            'access_token' => 'mock-oauth-access-token-12345',
            'expires_in' => 3600
        ]);
    }
    
    // Default mock response
    return $mockCurlResponses[$url] ?? 'mock-success-payload';
}

function curl_getinfo($ch, $option) {
    global $mockCurlHttpCodes;
    $url = $ch->url ?? '';
    
    if ($option === CURLINFO_HTTP_CODE) {
        return $mockCurlHttpCodes[$url] ?? 200; // Mock HTTP 200 OK
    }
    return 0;
}

function curl_close($ch) {
    // No-op
}

function openssl_sign($data, &$signature, $private_key, $algorithm = "SHA256") {
    $signature = "mock-openssl-jwt-signature-hash-12345";
    return true;
}

// 2. Main Test Runner Execution Context (switching back to global test context)
namespace GlobalContext;

require_once __DIR__ . '/bootstrap.php';

use Zero\Core\Storage\GoogleCloudStorageDriver;

echo "=== Mocked Google Cloud Storage (GCS) Driver Tests ===\n";

// Ensure mock key file exists for instantiation
$mockKeyFile = sys_get_temp_dir() . '/gcs-credentials-mock.json';
if (!file_exists($mockKeyFile)) {
    // Generate empty mock JSON key file if missing to allow instantiation
    file_put_contents($mockKeyFile, json_encode([
        'type' => 'service_account',
        'project_id' => 'mock-project-id',
        'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC3Y2F...mock\n-----END PRIVATE KEY-----\n",
        'client_email' => 'mock-client-email@example.com'
    ]));
}

// Inject GCS_KEY_FILE and GCS_BUCKET_NAME environment overrides so the driver can resolve them dynamically
putenv("GCS_BUCKET_NAME=mock-bucket");
putenv("GCS_KEY_FILE=" . $mockKeyFile);
try {
    $reflector = new \ReflectionClass(\Zero\Core\Env::class);
    $property = $reflector->getProperty('data');
    $property->setAccessible(true);
    $data = $property->getValue();
    if (!is_array($data)) {
        $data = [];
    }
    $data['GCS_BUCKET_NAME'] = 'mock-bucket';
    $data['GCS_KEY_FILE'] = $mockKeyFile;
    $property->setValue(null, $data);
} catch (\Exception $e) {}

// Instantiate GCS Driver using mock configs
$driver = new GoogleCloudStorageDriver([
    'bucket' => 'mock-bucket',
    'project_id' => 'mock-project-id',
    'key_file' => $mockKeyFile
]);

assert_test($driver !== null, "GoogleCloudStorageDriver instantiated successfully with mock JSON credentials");

// Dynamically resolve active bucket name from Env fallback
$bucketName = \Zero\Core\Env::get('GCS_BUCKET_NAME', 'mock-bucket');

// Test File Write GCS handshake Mocking
echo "  Testing GCS write handshake mocking...\n";
global $mockCurlHttpCodes;
$mockUrl = "https://storage.googleapis.com/upload/storage/v1/b/{$bucketName}/o?uploadType=media&name=mock-folder%2Ftest.txt";
$mockCurlHttpCodes[$mockUrl] = 200;

$writeResult = $driver->write('mock-folder/test.txt', 'mock payload');
assert_test($writeResult === true, "Mocked GCS write operation completed with successful HTTP 200 OK");

// Test File Exists GCS metadata Mocking
echo "  Testing GCS exists metadata query mocking...\n";
$mockMetaUrl = "https://storage.googleapis.com/storage/v1/b/{$bucketName}/o/mock-folder%2Ftest.txt";
$mockCurlHttpCodes[$mockMetaUrl] = 200; // 200 means exists!
$existsResult = $driver->exists('mock-folder/test.txt');
assert_test($existsResult === true, "Mocked GCS exists query successfully evaluated HTTP 200 OK as TRUE");

$mockCurlHttpCodes[$mockMetaUrl] = 404; // 404 means missing!
$missingResult = $driver->exists('mock-folder/test.txt');
assert_test($missingResult === false, "Mocked GCS exists query successfully evaluated HTTP 404 as FALSE");

// Test File Delete GCS Mocking
echo "  Testing GCS delete query mocking...\n";
$mockCurlHttpCodes[$mockMetaUrl] = 204; // 204 No Content is standard GCS success response on deletes
$deleteResult = $driver->delete('mock-folder/test.txt');
assert_test($deleteResult === true, "Mocked GCS delete operation successfully completed with HTTP 204");

echo "Mocked GCS driver component tests completed.\n\n";

// Clean up mock JSON key file from system temp folder
@unlink($mockKeyFile);
