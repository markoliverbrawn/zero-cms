<?php
// tests/EmailerTest.php
// Unit and integration tests for the Emailer support class and SMTP Socket Client

namespace Zero\Support {
    class MockSmtpState {
        public static $commandCount = 0;
        public static $isOffline = false;
        public static $writtenData = '';
    }

    function fsockopen($host, $port, &$errno, &$errstr, $timeout) {
        if (MockSmtpState::$isOffline) {
            $errno = 111;
            $errstr = "Connection refused";
            return false;
        }
        return fopen('php://temp', 'r+');
    }

    function fgets($handle, $length = null) {
        $responses = [
            0 => "220 mailpit SMTP service ready\r\n", // Server greeting
            1 => "250 OK\r\n", // HELO greeting
            2 => "250 OK\r\n", // MAIL FROM OK
            3 => "250 OK\r\n", // RCPT TO OK
            4 => "354 Start mail input; end with <CRLF>.<CRLF>\r\n", // DATA start
            5 => "250 OK\r\n", // DATA OK
            6 => "221 Bye\r\n", // QUIT Bye
        ];
        $state = MockSmtpState::$commandCount;
        return $responses[$state] ?? "250 OK\r\n";
    }

    function fputs($handle, $string) {
        MockSmtpState::$commandCount++;
        MockSmtpState::$writtenData .= $string;
        return strlen($string);
    }

    function fclose($handle) {
        @\fclose($handle);
        return true;
    }
}

namespace {
    require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

    use Zero\Support\Emailer;
    use Zero\Support\MockSmtpState;
    use Zero\Database\DB;

    echo "=== Emailer Support Tests ===\n";

    // This test specifically exercises Emailer::send()'s real code path (SMTP handshake, audit
    // logging, PII masking), so it opts out of the test-suite-wide mock enabled by
    // src/Support/TestBootstrap.php. This is still fully safe: the fsockopen/fgets/fputs/fclose functions
    // are shadowed above in the Zero\Support namespace, so no real socket is ever opened.
    Emailer::disableTestMode();

    // Ensure the sites and users tables are bootstrapped to avoid any cascade or audit log association issues
    $siteId = \Zero\Support\Security::uuidv7();
    DB::query("
        INSERT INTO sites (id, name, domain, theme, enabled_modules, created_at, updated_at)
        VALUES (?, 'Test Site', 'test.zero', 'default', '[]', NOW(), NOW())
    ", [$siteId]);

    // Reset SMTP State
    MockSmtpState::$commandCount = 0;
    MockSmtpState::$isOffline = false;

    // 1. Verify standard email dispatching (should return true on successful SMTP connection and transmission)
    echo "Testing SMTP socket connection and sending mail (mocked)...\n";
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

    // 4. Verify connection failure handling
    echo "Testing connection failure handling...\n";
    MockSmtpState::$isOffline = true;
    $successFail = Emailer::send($to, $subject, $htmlBody);
    assert_test($successFail === false, "Emailer handles connection failure gracefully and returns false");
    MockSmtpState::$isOffline = false;

    // 5. Verify setForceRecipient() redirects the real SMTP envelope, for test-mode/demo-site use
    echo "Testing forced-recipient redirect (test mode / demo sites)...\n";
    MockSmtpState::$commandCount = 0;
    MockSmtpState::$writtenData = '';
    $overrideAddress = 'redirect-target@zero.cms';
    Emailer::setForceRecipient($overrideAddress);
    assert_test(Emailer::getForceRecipient() === $overrideAddress, "getForceRecipient() reflects the address just set");

    $successOverride = Emailer::send($to, $subject, $htmlBody);
    assert_test($successOverride === true, "Emailer::send() still succeeds while a forced recipient is set");
    assert_test(str_contains(MockSmtpState::$writtenData, "RCPT TO: <{$overrideAddress}>"), "RCPT TO is sent to the forced recipient, not the original address");
    assert_test(str_contains(MockSmtpState::$writtenData, "To: <{$overrideAddress}>"), "The To: header carries the forced recipient");
    assert_test(str_contains(MockSmtpState::$writtenData, "X-Original-To: <{$to}>"), "X-Original-To preserves the original intended recipient for traceability");

    // Tiebreak on id (a time-ordered UUIDv7, see Logger::log()) rather than created_at alone --
    // this test's earlier sends can land within the same second-granularity NOW() timestamp.
    $overrideLog = DB::query("SELECT * FROM audit_logs WHERE action = 'email_sent' ORDER BY created_at DESC, id DESC LIMIT 1")->fetch();
    $overrideMeta = json_decode($overrideLog['meta'], true);
    assert_test($overrideMeta['recipient'] === $expectedMaskedRecipient, "Audit log still attributes the send to the original recipient, not the override address");
    assert_test($overrideMeta['redirected'] === true, "Audit log meta flags the send as redirected");

    // setForceRecipient(null) must fully restore normal, unredirected delivery
    Emailer::setForceRecipient(null);
    assert_test(Emailer::getForceRecipient() === null, "getForceRecipient() returns null after clearing the override");
    MockSmtpState::$commandCount = 0;
    MockSmtpState::$writtenData = '';
    Emailer::send($to, $subject, $htmlBody);
    assert_test(str_contains(MockSmtpState::$writtenData, "RCPT TO: <{$to}>"), "Clearing the forced recipient restores normal delivery to the real address");
    assert_test(!str_contains(MockSmtpState::$writtenData, "X-Original-To"), "No X-Original-To header is added when nothing is being redirected");

    // Clean up
    DB::query("DELETE FROM audit_logs WHERE id IN (?, ?)", [$log['id'], $overrideLog['id']]);
    DB::query("DELETE FROM sites WHERE id = ?", [$siteId]);

    echo "Emailer support tests completed successfully!\n";
}
