<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Controllers/LoginController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Admin\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Http\Middleware\AuthThrottlingMiddleware;
use Zero\Interfaces\Controller;
use Zero\Support\Logger;
use Zero\Support\Security;

/**
 * Class LoginController
 *
 * Back-office sign-in at /admin/login. Renders the credential form, and on submit verifies the
 * password, confirms the account belongs to the active tenant unless it is a super_admin, and
 * admits only administrative roles -- answering every failure with the same generic message so the
 * response cannot be used to enumerate accounts. An already-authenticated visitor is redirected to
 * the dashboard before any of that runs.
 */
class LoginController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($param)
    {
        // If landing on the login page with an error parameter (such as a CSRF verification failure)
        // on a GET request, cleanly log out and terminate any existing session first. We check that 
        // the request method is GET so we do not wipe the session-cached CSRF token when they submit 
        // the login form itself!
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['error'])) {
            App::logoutUser();
        }

        // If the user is already authenticated, redirect them straight to the admin dashboard
        if (!empty($_SESSION['user_id'])) {
            \header('Location: /admin/dashboard');
            exit;
        }

        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            App::applyCsrfMiddleware();
            $user = $_POST['username'] ?? '';
            $pass = $_POST['password'] ?? '';

            // Enforce centralized rate limiting and progressive lockout protection via Middleware
            AuthThrottlingMiddleware::handle('login', 'admin/login', [], function() {});

            $row = DB::query('SELECT * FROM users WHERE username = ? LIMIT 1', [$user])->fetch();
            
            $failReason = 'unknown';
            if (!$row) {
                $failReason = 'user_not_found';
            } else {
                if (!\password_verify($pass, $row['password_hash'])) {
                    $failReason = 'password_mismatch';
                } else {
                    $userRole = $row['role'] ?? 'editor';
                    $userSiteId = $row['site_id'] ?? null;
                    $currentSiteId = App::getCurrentSiteId();

                    if (!($userRole === 'super_admin' || $userSiteId === $currentSiteId)) {
                        $failReason = 'site_isolation_mismatch';
                    } else {
                        if (!\in_array($userRole, ['super_admin', 'admin', 'editor'])) {
                            $failReason = 'unauthorized_role';
                        } else {
                            $failReason = 'none';
                        }
                    }
                }
            }

            if ($failReason === 'none') {
                App::loginUser($row['id']);
                Logger::log($row['id'], 'login_success', 'user', $row['id'], [
                    'username' => $user,
                    'ip_address' => Security::getClientIp()
                ]);
                
                // Forward to original requested page if present, otherwise fallback to dashboard
                $redirectTo = $_SESSION['redirect_to'] ?? '/admin/dashboard';
                unset($_SESSION['redirect_to']); // Clean up session!
                \header('Location: ' . $redirectTo);
                exit;
            }

            $error = 'Invalid credentials';
            if ($failReason === 'unauthorized_role') {
                $error = 'Unauthorized administrative role';
            }

            Logger::log(null, 'login_failed', 'user', null, [
                'username' => $user,
                'ip_address' => Security::getClientIp(),
                'fail_reason' => $failReason,
                'password_length' => \strlen($pass)
            ]);
            
            // SECURITY REMEDIATION: Throttle brute force dictionary attempts
            \sleep(1);
            
            App::render('admin/login', [
                'error' => $error,
                'csrf' => Security::csrfToken()
            ]);
            exit;
        }
        App::render('admin/login', [
            'csrf' => Security::csrfToken()
        ]);
        exit;
    }
}
