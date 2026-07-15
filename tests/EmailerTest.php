<?php
// tests/EmailerTest.php
// Unit and integration tests for the Emailer support class and SMTP Socket Client

require_once __DIR__ . '/bootstrap.php';

use Zero\Support\Emailer;
use Zero\Database\DB;

echo "=== Emailer Support Tests ===\n";

// Ensure the sites and users tables are bootstrapped to avoid any cascade or audit log association issues
$siteId = \Zero\Support\Security::uuidv7();
DB::query("
    INSERT INTO sites (id, name, domain, theme, enabled_modules, created_at, updated_at)
    VALUES (?, 'Test Site', 'test.zero', 'default', '[]', NOW(), NOW())
", [$siteId]);

// 1. Verify standard email dispatching (should return true on successful SMTP connection and transmission)
echo "Testing SMTP socket connection and sending mail...\n";
$to = "recipient.test@zero.cms";
$subject = "Test Subject " . uniqid();
$htmlBody = "<h1>Hello World</h1><p>This is a test email sent from the Zero CMS test runner.</p>";

$success = Emailer::send($to, $subject, $htmlBody);
assert_test($success === true, "Emailer successfully connects to SMTP and transmits email envelope");

// 2. Verify that email_sent is recorded in the audit logs
echo "Testing audit log recording...\n";
$log = DB::query("SELECT * FROM audit_logs WHERE action = 'email_sent' ORDER BY created_at DESC LIMIT 1")->fetch();
assert_test(!empty($log), "An audit log entry with action 'email_sent' is successfully recorded in the database");

// 3. Verify PII masking rules inside recorded audit logs
echo "Testing PII masking rules inside audit log meta...\n";
$meta = json_decode($log['meta'], true);
assert_test(isset($meta['recipient']), "Audit log metadata contains 'recipient' property");
assert_test(isset($meta['subject']), "Audit log metadata contains 'subject' property");
assert_test($meta['subject'] === $subject, "Subject in audit log meta matches the sent subject exactly");

// Ensure PII is masked
$expectedMaskedRecipient = "r************t@zero.cms";
assert_test($meta['recipient'] === $expectedMaskedRecipient, "Recipient email is obfuscated to protect PII ('{$meta['recipient']}' matches expected '{$expectedMaskedRecipient}')");

// Clean up
DB::query("DELETE FROM audit_logs WHERE id = ?", [$log['id']]);
DB::query("DELETE FROM sites WHERE id = ?", [$siteId]);

echo "Emailer support tests completed successfully!\n";
