<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Controllers/Api/SendWelcomeController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Admin\Controllers\Api
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Admin\Controllers\Api;

use Zero\Core\App;
use Zero\Core\Template;
use Zero\Database\DB;
use Zero\Interfaces\Controller;
use Zero\Models\User;
use Zero\Support\Emailer;
use Zero\Support\Security;
use Zero\Support\Str;

/**
 * Class SendWelcomeController
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class SendWelcomeController implements Controller
{
    /**
     * Handle incoming AJAX request to dispatch welcome email to a target user.
     */
    public function handle($param)
    {
        // Enforce JSON API response format
        \header('Content-Type: application/json');

        // Check if the user is authenticated (is a logged-in admin or editor)
        App::ensureSession();
        $currentUser = App::getCurrentUser();
        if (!$currentUser) {
            \http_response_code(401);
            echo \json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in first.']);
            exit;
        }

        // Validate the request is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            \http_response_code(405);
            echo \json_encode(['success' => false, 'message' => 'Method not allowed.']);
            exit;
        }

        // Parse JSON payload inputs
        $input = \json_decode(\file_get_contents('php://input'), true) ?? [];
        $userId = $input['id'] ?? null;
        $csrfToken = $input['csrf'] ?? '';

        // Verify CSRF handshake to protect against state-changing forgery vectors
        if (empty($csrfToken) || !Security::csrfVerify($csrfToken)) {
            \http_response_code(403);
            echo \json_encode(['success' => false, 'message' => 'CSRF verification failed. Request declined.']);
            exit;
        }

        if (empty($userId)) {
            \http_response_code(400);
            echo \json_encode(['success' => false, 'message' => 'User ID is required.']);
            exit;
        }

        // Find target user
        $user = User::find($userId);
        if (!$user) {
            \http_response_code(404);
            echo \json_encode(['success' => false, 'message' => 'Target user not found.']);
            exit;
        }

        if (empty($user->email)) {
            \http_response_code(400);
            echo \json_encode(['success' => false, 'message' => 'Target user does not have a registered email address.']);
            exit;
        }

        // Resolve active site context
        $site = App::getCurrentSite();
        $siteName = $site ? $site->name : 'Zero CMS Platform';
        $siteDomain = $site ? $site->domain : 'localhost';

        // Construct primary login url
        $host = $_SERVER['HTTP_HOST'] ?? $siteDomain;
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $link = $scheme . '://' . $host . '/admin/login';

        // Render beautiful welcome email template using template view file
        $subject = "Welcome to " . $siteName . "!";
        $htmlBody = Template::renderFile(APPLICATION_ROOT . '/src/Views/emails/welcome.php', [
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role ?? 'editor',
            'siteName' => $siteName,
            'siteDomain' => $siteDomain,
            'link' => $link
        ]);

        // Send welcome email via local socket Emailer
        try {
            Emailer::send($user->email, $subject, $htmlBody);
            echo \json_encode([
                'success' => true,
                'message' => 'Welcome email dispatched successfully to ' . Str::escape($user->email) . '!'
            ]);
        } catch (\Exception $e) {
            \http_response_code(500);
            echo \json_encode([
                'success' => false,
                'message' => 'Failed to dispatch email: ' . $e->getMessage()
            ]);
        }
        exit;
    }
}
