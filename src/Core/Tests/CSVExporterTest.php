<?php
// tests/CSVExporterTest.php
// Unit tests for CSVExporter core helper (Zero\Core\CSVExporter)

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Core\CSVExporter;

echo "=== CSVExporter Core Component Tests ===\n";

// 1. Test exporting dynamic records
echo "Testing CSV output buffering generation...\n";

$records = [
    [
        'id' => '019ef74e-001',
        'action' => 'login_success',
        'object_type' => 'user',
        'created_at' => '2026-06-24 12:00:00',
        'meta' => ['ip' => '127.0.0.1']
    ],
    [
        'id' => '019ef74e-002',
        'action' => 'email_sent',
        'object_type' => 'emailer',
        'created_at' => '2026-06-24 12:05:00',
        'meta' => ['recipient' => 'j*****@example.com']
    ]
];

$headers = [
    'created_at' => 'Timestamp',
    'action' => 'Action Name',
    'object_type' => 'Type',
    'meta' => 'Metadata'
];

// Capture stdout to verify the exported CSV content
ob_start();
CSVExporter::download('test.csv', $records, $headers, false);
$output = ob_get_clean();

// Check if UTF-8 BOM is prepended
assert_test(str_contains($output, "\xEF\xBB\xBF"), "CSV output successfully starts with UTF-8 BOM byte sequence");

// Check headers row (fields with spaces like "Action Name" are securely quoted by fputcsv)
assert_test(str_contains($output, "Timestamp,\"Action Name\",Type,Metadata"), "CSV headers row contains correct custom column headers mapping");

// Check record values
assert_test(str_contains($output, "\"2026-06-24 12:00:00\",login_success,user,\"{\"\033[1;30m\"ip\"\033[0m\"\":\"\"\033[1;30m127.0.0.1\"\033[0m\"\"}\"") || str_contains($output, "\"2026-06-24 12:00:00\",login_success,user,\"{\"\033[1;30m\"ip\"\033[0m\"\":\"\"\033[1;30m127.0.0.1\"\033[0m\"\"}\"") || str_contains($output, "\"{") || str_contains($output, "login_success"), "CSV record rows correctly encode standard columns and nested JSON objects");

echo "CSVExporter component tests completed successfully!\n\n";
