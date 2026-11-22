<?php
// src/Modules/Admin/Tests/FileManagerMultiUploadTest.php
// Covers multi-file uploads: FileManagerService::normaliseFilesArray()'s reshaping of PHP's grouped
// $_FILES shape, uploadFiles()'s per-file error reporting, and the no-JS full-page POST to
// /admin/list/files with two files where one is invalid.

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Core\Storage\Storage;
use Zero\Modules\Admin\Services\FileManagerService;
use Zero\Support\TestRequest;

echo "=== FileManagerService Multi-File Upload Tests ===\n";

// 1. normaliseFilesArray(): grouped shape flattened, empty slots skipped, errors kept.
echo "Testing normaliseFilesArray() with a grouped multi-file entry...\n";
$grouped = [
    'name' => ['a.txt', '', 'b.png'],
    'type' => ['text/plain', '', 'image/png'],
    'tmp_name' => ['/tmp/phpA', '', ''],
    'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE, UPLOAD_ERR_INI_SIZE],
    'size' => [5, 0, 0],
];
$list = FileManagerService::normaliseFilesArray($grouped);
assert_test(\count($list) === 2, "Grouped entry yields one item per non-empty slot (UPLOAD_ERR_NO_FILE skipped)");
assert_test($list[0] === ['name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => '/tmp/phpA', 'error' => UPLOAD_ERR_OK, 'size' => 5], "First item carries its own name/type/tmp_name/error/size");
assert_test($list[1]['name'] === 'b.png' && $list[1]['error'] === UPLOAD_ERR_INI_SIZE, "An errored slot is kept so it can be reported, not silently dropped");

echo "Testing normaliseFilesArray() with a single-file entry...\n";
$single = ['name' => 'c.txt', 'type' => 'text/plain', 'tmp_name' => '/tmp/phpC', 'error' => UPLOAD_ERR_OK, 'size' => 3];
assert_test(FileManagerService::normaliseFilesArray($single) === [$single], "A plain single-file entry passes through unchanged");
assert_test(FileManagerService::normaliseFilesArray(['name' => '', 'error' => UPLOAD_ERR_NO_FILE]) === [], "An empty single-file slot yields nothing");
assert_test(FileManagerService::normaliseFilesArray([]) === [], "A malformed entry yields nothing");

// 2. uploadFiles(): failures are prefixed with the offending filename (no DB writes on this path).
echo "Testing uploadFiles() reports each failure by filename...\n";
$errored = FileManagerService::uploadFiles('no-site', [
    'name' => ['first.txt', 'second.txt'],
    'type' => ['text/plain', 'text/plain'],
    'tmp_name' => ['', ''],
    'error' => [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_PARTIAL],
    'size' => [0, 0],
], '');
assert_test($errored['uploaded'] === 0, "No files counted as uploaded when every slot errored");
assert_test(\count($errored['errors']) === 2, "One error per failed file");
assert_test(\str_starts_with($errored['errors'][0], 'first.txt: ') && \str_starts_with($errored['errors'][1], 'second.txt: '), "Each error is prefixed with its filename");

// 3. Full-page POST with two files, one invalid: the valid one is stored, the invalid one is not.
echo "Testing POST /admin/list/files with one valid and one invalid file...\n";
$workDir = \sys_get_temp_dir() . '/zero-multiupload-' . \bin2hex(\random_bytes(4));
\mkdir($workDir, 0775, true);
$validTmp = $workDir . '/valid';
$invalidTmp = $workDir . '/invalid';
\file_put_contents($validTmp, "plain text upload\n");
\file_put_contents($invalidTmp, "MZ not really an executable\n");

$suffix = \bin2hex(\random_bytes(4));
$validName = 'notes-' . $suffix . '.txt';
$invalidName = 'evil-' . $suffix . '.exe';
$domain = 'multiupload-' . \bin2hex(\random_bytes(4)) . '.zero';
$response = TestRequest::post('/admin/list/files', ['folder' => ''])
    ->onSite(['domain' => $domain])
    ->asUser()
    ->withCsrf()
    ->withFiles([
        'file' => [
            'name' => [$validName, $invalidName],
            'type' => ['text/plain', 'application/octet-stream'],
            'tmp_name' => [$validTmp, $invalidTmp],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size' => [\filesize($validTmp), \filesize($invalidTmp)],
        ],
    ])
    ->send();

assert_test($response['exit_code'] === 0, "Multi-file POST completes without a fatal error" . ($response['exit_code'] !== 0 ? ': ' . $response['stderr'] : ''));

// The routed request runs in a child process whose re-run TestBootstrap resolves a different test
// database name than this parent, so assert on the stored files on disk rather than media rows.
$storedValid = \glob(Storage::getUploadsRoot() . '/*/' . $validName) ?: [];
$storedInvalid = \glob(Storage::getUploadsRoot() . '/*/' . $invalidName) ?: [];
assert_test(\count($storedValid) === 1, "The valid file was stored");
assert_test(\count($storedInvalid) === 0, "The invalid file was rejected");

// Clean up the stored upload and scratch files.
foreach ($storedValid as $stored) {
    @\unlink($stored);
    @\rmdir(\dirname($stored));
}
@\unlink($validTmp);
@\unlink($invalidTmp);
@\rmdir($workDir);
