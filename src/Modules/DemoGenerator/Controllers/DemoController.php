<?php

declare(strict_types=1);

/**
 * File: src/Modules/DemoGenerator/Controllers/DemoController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\DemoGenerator\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

// src/Modules/DemoGenerator/Controllers/DemoController.php

namespace Zero\Modules\DemoGenerator\Controllers;

use Exception;
use Zero\Core\Template;
use Zero\Database\DB;
use Zero\Interfaces\Controller;
use Zero\Modules\DemoGenerator\Services\DemoSiteFactory;
use Zero\Support\Emailer;
use Zero\Support\Logger;
use Zero\Support\Security;

/**
 * Class DemoController
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class DemoController implements Controller
{
    /**
     * Dispatch sandbox credentials securely using standard, zero-dependency SMTP network streams.
     */
    protected function dispatchCredentialsEmail(string $email, string $domain, string $password): void
    {
        $subject = "Your Zero CMS Sandbox Demo Credentials";
        
        $templatePath = APPLICATION_ROOT . '/src/Views/emails/demo_credentials.php';
        $htmlBody = Template::renderFile($templatePath, [
            'email' => $email,
            'domain' => $domain,
            'password' => $password
        ]);
        
        $textBody = "Your Zero CMS Sandbox Demo is Ready!\n\nSandbox Domain: http://{$domain}\nAdmin URL: http://{$domain}/admin/dashboard\n\nLogin Credentials:\nUsername: {$email}\nPassword: {$password}\n\nNote: This sandbox is temporary and will be permanently deleted automatically in 24 hours.";

        Emailer::send($email, $subject, $htmlBody, $textBody);
    }

    /**
     * Primary entry point to process the sandbox request.
     */
    public function handle($matches): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Security Hardening: Enforce strict IP rate limiting (max 3 demos per 1 hour per IP)
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            if (!Security::checkAuthRateLimit('demo_creation', $ip, 3, 3600)) {
                Logger::log(null, 'demo_creation_failed', 'demo', null, ['ip_address' => $ip, 'error' => 'Rate limit exceeded']);
                \http_response_code(429);
                \header('Content-Type: application/json');
                echo \json_encode(['success' => false, 'error' => 'Rate limit exceeded. Please wait before creating another sandbox.']);
                exit;
            }

            $preset = $_POST['preset'] ?? 'kitchensink';
            $email = \filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

            if (!$email) {
                Logger::log(null, 'demo_creation_failed', 'demo', null, ['ip_address' => $ip, 'error' => 'Invalid email']);
                \http_response_code(400);
                \header('Content-Type: application/json');
                echo \json_encode(['success' => false, 'error' => 'Please provide a valid email address.']);
                exit;
            }

            if ($preset !== 'kitchensink') {
                Logger::log(null, 'demo_creation_failed', 'demo', null, ['ip_address' => $ip, 'error' => 'Invalid preset']);
                \http_response_code(400);
                \header('Content-Type: application/json');
                echo \json_encode(['success' => false, 'error' => 'Invalid preset template selected. Only the Kitchen Sink Showroom is available.']);
                exit;
            }

            // Enforce a strict boundary: only one active sandbox demo site per email address at a time
            $existing = DB::query("
                SELECT s.domain 
                FROM users u
                JOIN sites s ON u.site_id = s.id
                WHERE u.email = ? AND s.expires_at > NOW() AND u.deleted_at IS NULL AND s.deleted_at IS NULL
                LIMIT 1
            ", [$email])->fetch();

            if ($existing) {
                Logger::log(null, 'demo_creation_failed', 'demo', null, ['ip_address' => $ip, 'error' => 'Active sandbox already exists', 'email' => $email]);
                \http_response_code(400);
                \header('Content-Type: application/json');
                echo \json_encode([
                    'success' => false,
                    'error' => "An active sandbox is already registered to this email address (http://{$existing['domain']}). Please use it or wait for it to expire."
                ]);
                exit;
            }

            try {
                $demo = (new DemoSiteFactory())->createDemoSite($email, $preset);
                $this->dispatchCredentialsEmail($email, $demo['domain'], $demo['password']);

                Logger::log(null, 'demo_creation_success', 'demo', null, ['ip_address' => $ip, 'domain' => $demo['domain']]);

                \header('Content-Type: application/json');
                echo \json_encode([
                    'success' => true,
                    'domain' => $demo['domain'],
                    // Security Hardening: Do not disclose plain text passwords in public HTTP/API responses
                    'message' => 'Demo site generated successfully! Credentials have been sent to your email.'
                ]);
                exit;
            } catch (Exception $e) {
                Logger::log(null, 'demo_creation_failed', 'demo', null, ['ip_address' => $ip, 'error' => $e->getMessage()]);
                \http_response_code(500);
                \header('Content-Type: application/json');
                echo \json_encode(['success' => false, 'error' => 'Server Error: ' . $e->getMessage()]);
                exit;
            }
        }

        // Default GET request 404
        \http_response_code(404);
        echo "Not Found";
        exit;
    }
}
