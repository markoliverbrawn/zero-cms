---
name: raw-tcp-emailer
description: Explains Zero CMS's zero-dependency SMTP mailer, which speaks raw SMTP protocol over a manual TCP socket instead of using a mail library. Use when modifying, debugging, or extending src/Support/Emailer.php, mail delivery failures, or SMTP handshake issues.
---

# Raw TCP Socket Emailer (`src/Support/Emailer.php`)

To maintain complete framework decoupling and remove any third-party dependencies, Zero CMS utilizes a manual **SMTP TCP socket transceiver** directly on raw network streams to dispatch transaction updates and account credentials.

## Handshake sequence

1. Opens a socket connection to the target mail server using `fsockopen()`:
   ```php
   $socket = fsockopen($host, $port, $errno, $errstr, 5);
   ```
2. Manually loops, listens, and pushes SMTP protocol commands strictly, verifying server return codes on each dialogue step:
   * Expects code `220` on initial handshakes.
   * Dispatches `EHLO {domain}` → expects `250`.
   * Dispatches `MAIL FROM: <...>` → expects `250`.
   * Dispatches `RCPT TO: <...>` → expects `250`.
   * Dispatches `DATA` → expects `354`.
   * Sends custom boundaries, mime envelopes, headers, and the encoded UTF-8 body.
   * Sends the termination dot on an empty line `\r\n.\r\n` → expects `250`.
   * Dispatches `QUIT` to close connection.

## Testing

`Emailer::enableTestMode()`/`disableTestMode()` gate real socket usage — the test suite's shared bootstrap (`src/Support/TestBootstrap.php`) calls `enableTestMode()` globally so no real email is ever sent as a side effect of running tests. Tests that specifically need to exercise the real `send()` code path (SMTP handshake, audit logging, PII masking) opt back out explicitly and shadow the `fsockopen`/`fgets`/`fputs`/`fclose` functions in the `Zero\Support` namespace so no real socket is ever opened even with test mode disabled (see `src/Support/Tests/EmailerTest.php`).
