<?php
// src/Support/Tests/EmailerSmtpSmugglingTest.php
// The mailer speaks raw SMTP, so message content must never be able to end the DATA section early
// (CRLF.CRLF) and smuggle further commands onto the authenticated session, and addresses must
// never carry CR/LF into the envelope or headers.

require_once dirname(dirname(__DIR__)) . '/Support/TestBootstrap.php';

use Zero\Support\Emailer;

echo "=== Emailer SMTP Smuggling Tests ===\n";

echo "Testing DATA section preparation...\n";
$smuggle = "hi\r\n.\r\nMAIL FROM:ceo@example.test\r\nRCPT TO:victim@example.test\r\nDATA\r\n";
$prepared = Emailer::prepareDataSection($smuggle);
assert_test(\strpos($prepared, "\r\n.\r\n") === false, "A bare CRLF.CRLF in content is dot-stuffed");
assert_test(\strpos($prepared, "\r\n..\r\n") !== false, "The dot line is doubled");

$bareLf = Emailer::prepareDataSection("a\n.\nMAIL FROM:x\rb");
assert_test(\preg_match('/(?<!\r)\n|\r(?!\n)/', $bareLf) === 0, "Bare LF and CR are normalised to CRLF");
assert_test(\strpos($bareLf, "\r\n.\r\n") === false, "A bare-LF dot line cannot end DATA either");
assert_test(Emailer::prepareDataSection(".leading\r\nok") === "..leading\r\nok", "A dot at the very start is stuffed");

echo "Testing envelope address checks...\n";
assert_test(Emailer::isSafeAddress('person@example.test'), "A normal address is accepted");
assert_test(!Emailer::isSafeAddress("a@example.test\r\nRCPT TO:<b@example.test>"), "CRLF in an address is refused");
assert_test(!Emailer::isSafeAddress('a@example.test>'), "An angle bracket in an address is refused");
assert_test(Emailer::send("a@example.test\r\nBcc: b@example.test", 'S', '<p>x</p>') === false, "send() refuses an address with CRLF");

echo "Testing a contact-form smuggling payload yields a dot-stuffed message...\n";
// The form notification builds its text part with strip_tags(), which restores the CRLFs nl2br
// wrapped -- confirm the DATA preparation still neutralises what that produces.
$html = '<p><strong>Message:</strong> ' . \nl2br(\htmlspecialchars("hi\r\n.\r\nQUIT")) . '</p>';
assert_test(\strpos(Emailer::prepareDataSection(\strip_tags($html)), "\r\n.\r\n") === false, "A form message's text part cannot end DATA");

echo "Emailer SMTP smuggling tests completed successfully!\n";
