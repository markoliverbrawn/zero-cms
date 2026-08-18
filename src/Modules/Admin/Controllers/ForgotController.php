<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Controllers/ForgotController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Admin\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Core\Template;
use Zero\Database\DB;
use Zero\Http\Middleware\AuthThrottlingMiddleware;
use Zero\Interfaces\Controller;
use Zero\Support\Emailer;
use Zero\Support\Logger;
use Zero\Support\Security;

/**
 * Class ForgotController
 *
 * Back-office password-reset request at /admin/forgot. Issues a single-use reset token and emails
 * it, answering identically whether or not the address is on file.
 */
class ForgotController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($param)
    {
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            App::applyCsrfMiddleware();
            $username = \trim($_POST['username'] ?? '');

            // Enforce centralized rate limiting and progressive lockout protection via Middleware
            AuthThrottlingMiddleware::handle('password_reset', 'admin/forgot', [], function() {});

            $user = DB::query('SELECT * FROM users WHERE username = ? LIMIT 1', [$username])->fetch();
            
            if ($user && !empty($user['email'])) {
                $site = App::getCurrentSite();
                $expiryMinutes = $site ? (int)$site->getModuleSetting('admin', 'password_reset_expiry_minutes', 60) : 60;

                $token = \bin2hex(\random_bytes(16));
                $expires = \gmdate('Y-m-d H:i:s', \time() + ($expiryMinutes * 60));
                $resetId = Security::uuidv7();
                
                // Track reset token in database
                DB::query('INSERT INTO password_resets (id, site_id, user_id, token, expires_at) VALUES (?, ?, ?, ?, ?)', [
                    $resetId,
                    App::getCurrentSiteId(),
                    $user['id'],
                    $token,
                    $expires
                ]);
                
                Logger::log($user['id'], 'password_reset_request', 'user', $user['id'], ['username' => $username, 'ip_address' => $_SERVER['REMOTE_ADDR']]);
                
                // Construct recovery URL
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $link = $scheme . '://' . $host . '/admin/reset?token=' . $token;
                
                // Construct beautiful recovery email template
                $subject = "Reset Your Password - Zero CMS";
                $htmlBody = Template::renderFile(APPLICATION_ROOT . '/src/Views/emails/forgot-password.php', [
                    'username' => $user['username'],
                    'siteName' => $site->name,
                    'link' => $link,
                    'expiryMinutes' => $expiryMinutes
                ]);
                
                // Send Recovery Email via dynamic Mailpit SMTP helper!
                Emailer::send($user['email'], $subject, $htmlBody);
            } else {
                // Log failed attempt to trigger rate limiting and prevent brute-force requests
                Logger::log(null, 'password_reset_request_failed', 'user', null, [
                    'username' => $username,
                    'ip_address' => $_SERVER['REMOTE_ADDR']
                ]);
                // Introduce simulated timing delay to match successful path
                \usleep(250000); // 250ms
            }
            
            // SECURITY REMEDIATION (Timing mitigation):
            // Always show success message to mitigate user enumeration attacks!
            $success = 'If an account matches that username, a password recovery link has been dispatched to your registered email address.';
            App::render('admin/forgot', [
                'success' => $success,
                'csrf' => Security::csrfToken()
            ]);
            exit;
        }
        App::render('admin/forgot', [
            'csrf' => Security::csrfToken()
        ]);
        exit;
    }
}
