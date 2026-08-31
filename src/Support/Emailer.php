<?php

declare(strict_types=1);

/**
 * File: src/Support/Emailer.php
 * Architectural Purpose: Global diagnostic tools, cryptographic security handlers, SMTP email transmitters, and text helpers.
 * Package: Zero\Support
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support;

use Zero\Core\Env;
use Zero\Database\DB;
use Zero\Support\Logger;

/**
 * Class Emailer
 *
 * Zero-dependency SMTP client that speaks the protocol directly over a TCP socket instead of
 * relying on a mail library.
 *
 * Also carries the test-mode switch the suite relies on: with it enabled, messages are captured in
 * memory and retrievable through getTestModeSentEmails() rather than transmitted, so a scheduled
 * job dispatched during a test cannot email a real recipient.
 */
class Emailer
{
    protected static $testMode = false;
    protected static $testModeSentEmails = [];

    /**
     * Enable test mode: send() short-circuits before opening any real SMTP connection, recording
     * the attempt instead. Prevents automated test runs from triggering real email sends as a
     * side effect of exercising code paths (e.g. a scheduled job) that happen to call
     * Emailer::send() indirectly.
     *
     * @return void
     */
    public static function enableTestMode(): void
    {
        self::$testMode = true;
        self::$testModeSentEmails = [];
    }

    /**
     * Disable test mode, restoring real SMTP send behavior. Used by tests that specifically need
     * to exercise the real send() code path (typically because they've already made it safe some
     * other way, e.g. by shadowing the low-level socket functions).
     *
     * @return void
     */
    public static function disableTestMode(): void
    {
        self::$testMode = false;
    }

    /**
     * @return bool
     */
    public static function isTestMode(): bool
    {
        return self::$testMode;
    }

    /**
     * Get every email "sent" while in test mode, for tests that want to assert on what would
     * have been sent without any real email actually going out.
     *
     * @return array
     */
    public static function getTestModeSentEmails(): array
    {
        return self::$testModeSentEmails;
    }

    /**
     * Obfuscates Personally Identifiable Information (PII) like email addresses.
     * e.g., "jordan.smith@example.com" -> "j**********h@example.com"
     */
    private static function maskEmail(string $email): string
    {
        $parts = \explode('@', $email);
        if (\count($parts) !== 2) {
            return $email;
        }
        $local = $parts[0];
        $domain = $parts[1];
        $len = \strlen($local);
        if ($len <= 2) {
            return '***@' . $domain;
        }
        return \substr($local, 0, 1) . \str_repeat('*', $len - 2) . \substr($local, -1) . '@' . $domain;
    }

    /**
     * Send an email using a pure PHP zero-dependency SMTP Socket Client.
     * Connects directly to the Mailpit SMTP server configured in .env.
     */
    public static function send(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        if (self::$testMode) {
            self::$testModeSentEmails[] = [
                'to' => $to,
                'subject' => $subject,
                'html_body' => $htmlBody,
                'text_body' => $textBody
            ];
            return true;
        }

        // Read active SMTP configurations from environment
        $host = Env::get('SMTP_HOST', 'mailpit');
        $port = \intval(Env::get('SMTP_PORT', '1025'));
        $fromEmail = Env::get('SMTP_FROM_EMAIL', 'noreply@zero.shop');
        $fromName = Env::get('SMTP_FROM_NAME', 'Zero CMS');
        $secure = Env::get('SMTP_SECURE', '');   // 'tls' enables STARTTLS
        $user = Env::get('SMTP_USER', '');        // non-empty enables AUTH LOGIN
        $pass = Env::get('SMTP_PASS', '');

        if (empty($textBody)) {
            $textBody = \strip_tags($htmlBody);
        }

        // Open direct socket connection to the SMTP server
        $socket = @fsockopen($host, $port, $errno, $errstr, 5);
        if (!$socket) {
            // Safe fallback: log error or return false if SMTP is offline
            error_log("Emailer connection failed to {$host}:{$port} - Error: {$errstr} ({$errno})");
            return false;
        }

        // Helper to read socket responses and verify SMTP return codes
        $getResponse = function ($socket) {
            $response = "";
            while (($line = fgets($socket, 512)) !== false) {
                $response .= $line;
                if (substr($line, 3, 1) === " ") {
                    break;
                }
            }
            return trim($response);
        };

        // Helper to reject a response whose status code isn't one of the codes the SMTP spec
        // allows for that step, logging the server's own text (e.g. Bird/SparkPost's
        // "535 5.7.8 Authentication credentials invalid") instead of silently pressing on -- an
        // unchecked handshake previously reported send() as successful even when every subsequent
        // command was rejected as a consequence of an earlier failure.
        $requireCode = function (string $response, array $expectedCodes, string $step) use ($host, $port, $socket): bool {
            if (\in_array(\substr($response, 0, 3), $expectedCodes, true)) {
                return true;
            }
            error_log("Emailer SMTP {$step} rejected by {$host}:{$port} - {$response}");
            \fclose($socket);
            return false;
        };

        // Standard SMTP commands handshake protocol
        try {
            $response = $getResponse($socket); // Read server greeting
            if (!$requireCode($response, ['220'], 'greeting')) {
                return false;
            }

            fputs($socket, "EHLO localhost\r\n");
            $response = $getResponse($socket);
            if (!$requireCode($response, ['250'], 'EHLO')) {
                return false;
            }

            if ($secure === 'tls') {
                fputs($socket, "STARTTLS\r\n");
                $response = $getResponse($socket);
                if (!$requireCode($response, ['220'], 'STARTTLS')) {
                    return false;
                }
                if (!\stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    error_log("Emailer STARTTLS negotiation failed for {$host}:{$port}");
                    \fclose($socket);
                    return false;
                }
                // SMTP requires re-issuing EHLO after the TLS upgrade -- the prior plaintext
                // EHLO's capability list is discarded per RFC 3207.
                fputs($socket, "EHLO localhost\r\n");
                $response = $getResponse($socket);
                if (!$requireCode($response, ['250'], 'post-STARTTLS EHLO')) {
                    return false;
                }
            }

            if ($user !== '') {
                fputs($socket, "AUTH LOGIN\r\n");
                $response = $getResponse($socket);
                if (!$requireCode($response, ['334'], 'AUTH LOGIN')) {
                    return false;
                }
                fputs($socket, \base64_encode($user) . "\r\n");
                $response = $getResponse($socket);
                if (!$requireCode($response, ['334'], 'AUTH username')) {
                    return false;
                }
                fputs($socket, \base64_encode($pass) . "\r\n");
                $response = $getResponse($socket);
                if (!$requireCode($response, ['235'], 'AUTH password')) {
                    return false;
                }
            }

            fputs($socket, "MAIL FROM: <{$fromEmail}>\r\n");
            $response = $getResponse($socket);
            if (!$requireCode($response, ['250'], 'MAIL FROM')) {
                return false;
            }

            fputs($socket, "RCPT TO: <{$to}>\r\n");
            $response = $getResponse($socket);
            if (!$requireCode($response, ['250', '251'], 'RCPT TO')) {
                return false;
            }

            fputs($socket, "DATA\r\n");
            $response = $getResponse($socket);
            if (!$requireCode($response, ['354'], 'DATA')) {
                return false;
            }

            // Construct secure, standard-compliant MIME headers & payload
            $boundary = "bnd_" . \md5(\uniqid((string)\time()));
            $headers = [
                "MIME-Version: 1.0",
                "From: =?UTF-8?B?" . \base64_encode($fromName) . "?= <{$fromEmail}>",
                "To: <{$to}>",
                "Subject: =?UTF-8?B?" . \base64_encode($subject) . "?=",
                "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
                "Date: " . \date('r'),
                "X-Mailer: Zero-Dependency PHP Mailer"
            ];

            $message = \implode("\r\n", $headers) . "\r\n\r\n";
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $textBody . "\r\n\r\n";
            
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $htmlBody . "\r\n\r\n";
            $message .= "--{$boundary}--\r\n";

            fputs($socket, $message . "\r\n.\r\n");
            $response = $getResponse($socket);
            if (!$requireCode($response, ['250'], 'message body')) {
                return false;
            }

            fputs($socket, "QUIT\r\n");
            $getResponse($socket);

            \fclose($socket);

            // Central audit logging of email send attempts with strict PII redaction
            try {
                $recipientUserId = null;
                $objectType = 'emailer';
                try {
                    // Try to resolve the recipient user's ID if registered in our database!
                    $stmt = DB::query("SELECT id FROM users WHERE email = ? LIMIT 1", [$to]);
                    $recipientUser = $stmt->fetch();
                    if ($recipientUser) {
                        $recipientUserId = $recipientUser['id'] ?? null;
                        $objectType = 'user'; // Associate semantically with user model
                    }
                } catch (\Exception $ex) {
                    // Safe fallback if tables or DB connections are not yet initialized
                }

                $userId = $_SESSION['user_id'] ?? null;
                $maskedRecipient = self::maskEmail($to);
                Logger::log(
                    $userId,
                    'email_sent',
                    $objectType,
                    $recipientUserId,
                    [
                        'recipient' => $maskedRecipient,
                        'subject' => $subject
                    ]
                );
            } catch (\Exception $e) {
                // Ignore audit logging errors during un-bootstrapped phases
            }

            return true;
        } catch (\Exception $e) {
            \fclose($socket);
            return false;
        }
    }
}
