<?php
// tests/GCSMockTest.php
// Unit test utilizing PHP namespace shadowing (monkey-patching) to mock GCP/GCS cURL API transceivers 100% offline.

namespace Zero\Core\Storage;

// 1. Monkey-Patching PHP's native curl functions inside the target namespace!
$mockCurlResponses = [];
$mockCurlHttpCodes = [];

// Every request curl_exec() receives, in order, as ['url' => ..., 'body' => ...] -- lets a test
// assert which bucket/endpoint a driver call actually targeted, and what it sent.
$mockCurlRequests = [];

// Per-URL queue of [status, response, error] steps, consumed one per curl_exec() call against
// that URL -- lets a test simulate "fails N times, then succeeds" to exercise curlWithRetry()'s
// backoff/retry logic, without disturbing the simpler static single-value mocks above that every
// other test in this file already relies on (only consulted once a URL has a queue populated).
$mockCurlSequences = [];

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
    global $mockCurlResponses, $mockCurlSequences, $mockCurlRequests;
    $url = $ch->url ?? '';
    $mockCurlRequests[] = ['url' => $url, 'body' => $ch->postfields ?? null];

    if (!empty($mockCurlSequences[$url])) {
        $ch->_sequenceStep = array_shift($mockCurlSequences[$url]);
        return $ch->_sequenceStep['response'] ?? 'mock-success-payload';
    }

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
        if (isset($ch->_sequenceStep)) {
            return $ch->_sequenceStep['status'] ?? 200;
        }
        return $mockCurlHttpCodes[$url] ?? 200; // Mock HTTP 200 OK
    }
    return 0;
}

function curl_error($ch) {
    return isset($ch->_sequenceStep) ? ($ch->_sequenceStep['error'] ?? '') : '';
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

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\Storage\GoogleCloudStorageDriver;

echo "=== Mocked Google Cloud Storage (GCS) Driver Tests ===\n";

// Ensure mock key file exists for instantiation
$mockKeyFile = confine_test_path(sys_get_temp_dir() . '/gcs-credentials-mock.json', sys_get_temp_dir());
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

// Regression test: media.path is populated from this driver's own getUrl() output (see
// FileManagerService::uploadFile()), so exists()/read()/delete() must be able to unwrap that same
// full public URL back down to the underlying object key. Before this was fixed, cleanPath() had
// no logic for its own getUrl() prefix, so every read-back of an uploaded file's path 404'd
// silently -- breaking on-demand image variant generation and dimension probing under
// STORAGE_DRIVER=gcs.
echo "  Testing getUrl() output round-trips back through exists()/read()...\n";
global $mockCurlResponses;
$publicUrl = $driver->getUrl('mock-folder/round-trip.txt');
assert_test(
    $publicUrl === "https://storage.googleapis.com/{$bucketName}/mock-folder/round-trip.txt",
    "getUrl() produced the expected fully-qualified public URL"
);

// The mock's curl_getinfo() defaults an unrecognized URL to HTTP 200 (see top of file), so an
// exists()-only assertion would pass even if cleanPath() regressed to a no-op that leaves the
// full URL intact -- it would just query a different (wrong) URL that also defaults to 200.
// Explicitly mapping that exact wrong/pre-fix URL to a 404 closes that hole and makes this a real
// regression guard rather than a false-positive-prone one.
$mockRoundTripMetaUrl = "https://storage.googleapis.com/storage/v1/b/{$bucketName}/o/mock-folder%2Fround-trip.txt";
$mockCurlHttpCodes[$mockRoundTripMetaUrl] = 200;
$mockBrokenMetaUrl = "https://storage.googleapis.com/storage/v1/b/{$bucketName}/o/" . urlencode($publicUrl);
$mockCurlHttpCodes[$mockBrokenMetaUrl] = 404;
assert_test(
    $driver->exists($publicUrl) === true,
    "exists() correctly unwraps a getUrl()-shaped public URL back to its object key"
);

$mockRoundTripReadUrl = "https://storage.googleapis.com/storage/v1/b/{$bucketName}/o/mock-folder%2Fround-trip.txt?alt=media";
$mockCurlResponses[$mockRoundTripReadUrl] = 'round trip payload';
$mockCurlHttpCodes[$mockRoundTripReadUrl] = 200;
assert_test(
    $driver->read($publicUrl) === 'round trip payload',
    "read() correctly unwraps a getUrl()-shaped public URL back to its object key"
);

// Regression coverage for write()'s transient-failure retry (curlWithRetry()): a 503 (or 429, or
// a failed transfer) should be retried with backoff, while any other 4xx should fail immediately.
global $mockCurlSequences;

echo "  Testing write() retries a transient 503 and succeeds once the bucket recovers...\n";
$retrySuccessUrl = "https://storage.googleapis.com/upload/storage/v1/b/{$bucketName}/o?uploadType=media&name=mock-folder%2Fretry-success.txt";
$mockCurlSequences[$retrySuccessUrl] = [
    ['status' => 503, 'response' => '{"error":{"message":"Backend Error"}}', 'error' => ''],
    ['status' => 200, 'response' => '{}', 'error' => ''],
];
$retrySuccessResult = $driver->write('mock-folder/retry-success.txt', 'mock payload');
assert_test($retrySuccessResult === true, "write() succeeds after one retried 503");
assert_test(empty($mockCurlSequences[$retrySuccessUrl]), "write() consumed exactly 2 attempts (1 failure + 1 success), not more");

echo "  Testing write() gives up after exhausting all retry attempts on persistent 503s...\n";
$retryExhaustedUrl = "https://storage.googleapis.com/upload/storage/v1/b/{$bucketName}/o?uploadType=media&name=mock-folder%2Fretry-exhausted.txt";
$mockCurlSequences[$retryExhaustedUrl] = [
    ['status' => 503, 'response' => '{"error":{"message":"Backend Error"}}', 'error' => ''],
    ['status' => 503, 'response' => '{"error":{"message":"Backend Error"}}', 'error' => ''],
    ['status' => 503, 'response' => '{"error":{"message":"Backend Error"}}', 'error' => ''],
];
$errorLogFile = confine_test_path(sys_get_temp_dir() . '/gcs-write-retry-exhausted.log', sys_get_temp_dir());
@unlink($errorLogFile);
$previousErrorLog = ini_set('error_log', $errorLogFile);
$retryExhaustedResult = $driver->write('mock-folder/retry-exhausted.txt', 'mock payload');
ini_set('error_log', $previousErrorLog);
assert_test($retryExhaustedResult === false, "write() returns false once every retry attempt also fails");
assert_test(empty($mockCurlSequences[$retryExhaustedUrl]), "write() made exactly 3 attempts total (the configured max), not more");
$loggedDiagnostic = @file_get_contents($errorLogFile) ?: '';
assert_test(str_contains($loggedDiagnostic, 'Backend Error'), "The final failure's GCS error.message is written to the diagnostic log");
assert_test(str_contains($loggedDiagnostic, '503'), "The final failure's HTTP status is written to the diagnostic log");
@unlink($errorLogFile);

echo "  Testing write() does NOT retry a non-transient 4xx (fails fast)...\n";
$noRetryUrl = "https://storage.googleapis.com/upload/storage/v1/b/{$bucketName}/o?uploadType=media&name=mock-folder%2Fno-retry.txt";
$mockCurlSequences[$noRetryUrl] = [
    ['status' => 403, 'response' => '{"error":{"message":"Forbidden"}}', 'error' => ''],
    ['status' => 200, 'response' => '{}', 'error' => ''], // Would only be consumed if (incorrectly) retried
];
$noRetryResult = $driver->write('mock-folder/no-retry.txt', 'mock payload');
assert_test($noRetryResult === false, "write() returns false immediately on a non-transient 403");
assert_test(count($mockCurlSequences[$noRetryUrl]) === 1, "write() made exactly 1 attempt for a non-transient failure, leaving the second queued step untouched");

echo "  Testing write() logs the curl transport error when the transfer itself fails...\n";
$transportFailUrl = "https://storage.googleapis.com/upload/storage/v1/b/{$bucketName}/o?uploadType=media&name=mock-folder%2Ftransport-fail.txt";
$mockCurlSequences[$transportFailUrl] = [
    ['status' => 0, 'response' => false, 'error' => 'Could not resolve host: storage.googleapis.com'],
    ['status' => 0, 'response' => false, 'error' => 'Could not resolve host: storage.googleapis.com'],
    ['status' => 0, 'response' => false, 'error' => 'Could not resolve host: storage.googleapis.com'],
];
$transportErrorLogFile = confine_test_path(sys_get_temp_dir() . '/gcs-write-transport-fail.log', sys_get_temp_dir());
@unlink($transportErrorLogFile);
$previousErrorLog = ini_set('error_log', $transportErrorLogFile);
$transportFailResult = $driver->write('mock-folder/transport-fail.txt', 'mock payload');
ini_set('error_log', $previousErrorLog);
assert_test($transportFailResult === false, "write() returns false when every attempt fails to even transfer");
$transportLoggedDiagnostic = @file_get_contents($transportErrorLogFile) ?: '';
assert_test(str_contains($transportLoggedDiagnostic, 'Could not resolve host'), "curl_error() detail is written to the diagnostic log when the response body is empty");
@unlink($transportErrorLogFile);

// Sets (or, with null, clears) an env value for the driver, in both getenv() and Env's loaded
// .env data, since Env::get() consults the latter whenever getenv() has nothing.
function set_gcs_env(string $key, ?string $value): void {
    putenv($value === null ? $key : "{$key}={$value}");
    $reflector = new \ReflectionClass(\Zero\Core\Env::class);
    $property = $reflector->getProperty('data');
    $property->setAccessible(true);
    $data = $property->getValue() ?: [];
    if ($value === null) {
        unset($data[$key]);
    } else {
        $data[$key] = $value;
    }
    $property->setValue(null, $data);
}

// URL of the most recent mocked request matching a substring.
function last_request_matching(string $needle): ?array {
    global $mockCurlRequests;
    foreach (array_reverse($mockCurlRequests) as $request) {
        if (str_contains($request['url'], $needle)) {
            return $request;
        }
    }
    return null;
}

echo "  Testing the legacy single-bucket layout still marks private objects with an ACL...\n";
$mockCurlRequests = [];
$driver->write('storage/private/backups/site-1/legacy.json.gz', 'backup');
$legacyWrite = last_request_matching('name=storage%2Fprivate%2Fbackups');
assert_test($legacyWrite !== null && str_contains($legacyWrite['url'], "/b/{$bucketName}/o?"), "Without GCS_PRIVATE_BUCKET_NAME, private writes still go to the main bucket");
assert_test($legacyWrite !== null && str_contains($legacyWrite['url'], 'predefinedAcl=private'), "Without GCS_PRIVATE_BUCKET_NAME, private writes still send predefinedAcl=private");

echo "  Testing private objects route to GCS_PRIVATE_BUCKET_NAME without an ACL...\n";
set_gcs_env('GCS_PRIVATE_BUCKET_NAME', 'mock-private-bucket');
$privateDriver = new GoogleCloudStorageDriver();
$mockCurlRequests = [];

$privateDriver->write('/storage/private/backups/site-1/b.json.gz', 'backup');
$privateWrite = last_request_matching('name=storage%2Fprivate%2Fbackups%2Fsite-1%2Fb.json.gz');
assert_test($privateWrite !== null && str_contains($privateWrite['url'], '/b/mock-private-bucket/o?'), "write() of a private path targets the private bucket");
assert_test($privateWrite !== null && !str_contains($privateWrite['url'], 'predefinedAcl'), "write() of a private path sends no predefinedAcl (a uniform-access bucket rejects one)");

$tmpUpload = confine_test_path(sys_get_temp_dir() . '/gcs-private-upload.json.gz', sys_get_temp_dir());
file_put_contents($tmpUpload, 'staged restore upload');
$privateDriver->putFile('/storage/private/restore-uploads/r.json.gz', $tmpUpload);
@unlink($tmpUpload);
$privatePut = last_request_matching('name=storage%2Fprivate%2Frestore-uploads');
assert_test($privatePut !== null && str_contains($privatePut['url'], '/b/mock-private-bucket/o?') && !str_contains($privatePut['url'], 'predefinedAcl'), "putFile() of a private path targets the private bucket with no predefinedAcl");

$privateDriver->write('/storage/uploads/site-1/photo.jpg', 'jpeg');
$publicWrite = last_request_matching('name=storage%2Fuploads%2Fsite-1%2Fphoto.jpg');
assert_test($publicWrite !== null && str_contains($publicWrite['url'], "/b/{$bucketName}/o?"), "write() of a public path still targets the main bucket");

$privateDriver->read('/storage/private/backups/site-1/b.json.gz');
$privateDriver->exists('/storage/private/backups/site-1/b.json.gz');
$privateDriver->delete('/storage/private/backups/site-1/b.json.gz');
$privateObjectCalls = array_filter($mockCurlRequests, fn ($r) => str_contains($r['url'], '/o/storage%2Fprivate%2Fbackups%2Fsite-1%2Fb.json.gz'));
assert_test(count($privateObjectCalls) === 3, "read(), exists() and delete() of a private path each made one request");
assert_test(count(array_filter($privateObjectCalls, fn ($r) => str_contains($r['url'], '/b/mock-private-bucket/o/'))) === 3, "read(), exists() and delete() of a private path all target the private bucket");

$privateUrl = $privateDriver->getUrl('/storage/private/backups/site-1/b.json.gz');
assert_test($privateUrl === 'https://storage.googleapis.com/mock-private-bucket/storage/private/backups/site-1/b.json.gz', "getUrl() of a private path points at the private bucket");
$privateDriver->exists($privateUrl);
assert_test(str_contains(end($mockCurlRequests)['url'], '/b/mock-private-bucket/o/storage%2Fprivate%2Fbackups'), "A stored private-bucket URL is unwrapped back to its object key");

$privateDriver->rename('/storage/uploads/site-1/a.txt', '/storage/private/moved/a.txt');
$copy = last_request_matching('/copyTo/');
assert_test($copy !== null && str_contains($copy['url'], "/b/{$bucketName}/o/storage%2Fuploads%2Fsite-1%2Fa.txt/copyTo/b/mock-private-bucket/o/storage%2Fprivate%2Fmoved%2Fa.txt"), "rename() from a public to a private path copies across the buckets");
assert_test($copy !== null && !str_contains($copy['url'], 'PredefinedAcl'), "rename() into the private bucket sends no destination ACL");

echo "  Testing getSignedUrl() with a key file signs locally for the right bucket...\n";
$mockCurlRequests = [];
$keySigned = $privateDriver->getSignedUrl('/storage/private/backups/site-1/b.json.gz', 300);
assert_test(str_starts_with($keySigned, 'https://storage.googleapis.com/mock-private-bucket/storage/private/backups/site-1/b.json.gz?'), "Signed URL for a private path points at the private bucket");
assert_test(str_contains($keySigned, 'X-Goog-Credential=mock-client-email%40example.com'), "Key-file signing uses the key file's client_email");
assert_test(str_contains($keySigned, 'X-Goog-Signature=' . bin2hex('mock-openssl-jwt-signature-hash-12345')), "Key-file signing uses the local OpenSSL signature");
assert_test(last_request_matching('signBlob') === null, "Key-file signing makes no IAM Credentials call");

echo "  Testing getSignedUrl() without a key file signs through the IAM Credentials API...\n";
set_gcs_env('GCS_KEY_FILE', null);
$workloadDriver = new GoogleCloudStorageDriver();
$emailUrl = 'http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/email';
$signBlobUrl = 'https://iamcredentials.googleapis.com/v1/projects/-/serviceAccounts/runtime-sa%40mock-project.iam.gserviceaccount.com:signBlob';
$mockCurlResponses[$emailUrl] = 'runtime-sa@mock-project.iam.gserviceaccount.com';
$mockCurlResponses[$signBlobUrl] = json_encode(['keyId' => 'k1', 'signedBlob' => base64_encode('iam-signed-bytes')]);
$mockCurlRequests = [];
$iamSigned = $workloadDriver->getSignedUrl('/storage/private/backups/site-1/b.json.gz', 300);
assert_test(str_contains($iamSigned, 'X-Goog-Credential=runtime-sa%40mock-project.iam.gserviceaccount.com'), "Workload-identity signing uses the metadata server's service account email");
assert_test(str_contains($iamSigned, 'X-Goog-Signature=' . bin2hex('iam-signed-bytes')), "Workload-identity signing uses the signature signBlob returned");
$signCall = last_request_matching('signBlob');
$signedPayload = $signCall !== null ? base64_decode(json_decode($signCall['body'], true)['payload'] ?? '') : '';
assert_test(str_starts_with($signedPayload, "GOOG4-RSA-SHA256\n"), "signBlob was asked to sign the V4 string-to-sign");

$mockCurlHttpCodes[$signBlobUrl] = 403;
$mockCurlResponses[$signBlobUrl] = '{"error":{"message":"Permission iam.serviceAccounts.signBlob denied"}}';
$signError = '';
try {
    $workloadDriver->getSignedUrl('/storage/private/backups/site-1/b.json.gz', 300);
} catch (\Exception $e) {
    $signError = $e->getMessage();
}
assert_test(str_contains($signError, 'signBlob denied') && str_contains($signError, 'roles/iam.serviceAccountTokenCreator'), "A signBlob permission failure throws with the API error and the role to grant");

set_gcs_env('GCS_KEY_FILE', $mockKeyFile);
set_gcs_env('GCS_PRIVATE_BUCKET_NAME', null);

echo "Mocked GCS driver component tests completed.\n\n";

// Clean up mock JSON key file from system temp folder
@unlink($mockKeyFile);
