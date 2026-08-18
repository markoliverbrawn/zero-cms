<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Controllers/GoogleAuthController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Admin\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Core\Env;
use Zero\Database\DB;
use Zero\Interfaces\Controller;
use Zero\Support\Logger;
use Zero\Support\Security;

/**
 * Class GoogleAuthController
 *
 * OAuth2 callback at /admin/google-callback completing Google single sign-on. Exchanges the
 * authorisation code for a profile over raw cURL, then matches it to a local account and refuses
 * the sign-in if that account belongs to a different tenant.
 */
class GoogleAuthController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($param)
    {
        // 1. Ensure PHP session is active
        if (\session_status() === PHP_SESSION_NONE) {
            \session_start();
        }

        $code = $_GET['code'] ?? '';
        $state = $_GET['state'] ?? '';
        $error = $_GET['error'] ?? '';

        // If Google returned an error directly during authorization
        if (!empty($error)) {
            \header('Location: /admin/login?error=google_returned_error&details=' . \urlencode($error));
            exit;
        }

        if (empty($code)) {
            \header('Location: /admin/login?error=missing_auth_code');
            exit;
        }

        // 2. Validate OAuth CSRF State matching
        if (!Security::csrfVerify($state)) {
            \header('Location: /admin/login?error=csrf_verification_failed');
            exit;
        }

        $clientId = Env::get('GOOGLE_CLIENT_ID');
        $clientSecret = Env::get('GOOGLE_CLIENT_SECRET');
        $redirectUri = Env::get('GOOGLE_REDIRECT_URI');

        if (empty($clientId) || empty($clientSecret) || empty($redirectUri)) {
            \header('Location: /admin/login?error=google_oauth_unconfigured');
            exit;
        }

        try {
            // 3. Exchange temporary auth code for access token via native PHP curl POST
            $ch = \curl_init('https://oauth2.googleapis.com/token');
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_POST, true);
            \curl_setopt($ch, CURLOPT_POSTFIELDS, \http_build_query([
                'code'          => $code,
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri'  => $redirectUri,
                'grant_type'    => 'authorization_code'
            ]));
            \curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded'
            ]);

            $response = \curl_exec($ch);
            $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            \curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                \header('Location: /admin/login?error=google_token_exchange_failed');
                exit;
            }

            $tokenData = \json_decode($response, true);
            $accessToken = $tokenData['access_token'] ?? '';

            if (empty($accessToken)) {
                \header('Location: /admin/login?error=google_invalid_access_token');
                exit;
            }

            // 4. Fetch the authenticated user profile using the access token via GET request
            $ch = \curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken
            ]);

            $profileResponse = \curl_exec($ch);
            $profileHttpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            \curl_close($ch);

            if ($profileHttpCode !== 200 || !$profileResponse) {
                \header('Location: /admin/login?error=google_profile_request_failed');
                exit;
            }

            $googleUser = \json_decode($profileResponse, true);
            $email = $googleUser['email'] ?? '';

            if (empty($email)) {
                \header('Location: /admin/login?error=google_email_missing');
                exit;
            }

            // 5. Look up corresponding local CMS tenant/global user account
            $row = DB::query('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1', [$email])->fetch();

            if (!$row) {
                \header('Location: /admin/login?error=google_account_not_found');
                exit;
            }

            $userId = $row['id'];
            $userRole = $row['role'] ?? 'editor';
            $userSiteId = $row['site_id'] ?? null;
            $currentSiteId = App::getCurrentSiteId();

            // Strict Multi-Tenant boundary verification
            if (!($userRole === 'super_admin' || $userSiteId === $currentSiteId)) {
                \header('Location: /admin/login?error=google_site_isolation_mismatch');
                exit;
            }

            if (!\in_array($userRole, ['super_admin', 'admin', 'editor'])) {
                \header('Location: /admin/login?error=google_unauthorized_role');
                exit;
            }

            // 6. Log user into system session and write security audit log
            App::loginUser($userId);
            Logger::log($userId, 'google_login_success', 'user', $userId, [
                'username' => $row['username'],
                'email' => $email,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);

            // Forward to target page or dashboard
            $redirectTo = $_SESSION['redirect_to'] ?? '/admin/dashboard';
            unset($_SESSION['redirect_to']);
            \header('Location: ' . $redirectTo);
            exit;

        } catch (\Exception $e) {
            \header('Location: /admin/login?error=google_exception_triggered&details=' . \urlencode($e->getMessage()));
            exit;
        }
    }
}
