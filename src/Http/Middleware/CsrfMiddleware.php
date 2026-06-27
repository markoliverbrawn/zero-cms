<?php

namespace Zero\Http\Middleware;

use Zero\Core\App;
use Zero\Support\Security;

class CsrfMiddleware
{
    public function handle(callable $next)
    {
        App::ensureSession();

        $stateChangingMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        if (in_array($_SERVER['REQUEST_METHOD'], $stateChangingMethods)) {
            $token = $_POST['csrf'] ?? '';

            // Resolve from Custom HTTP Headers (for AJAX/REST calls)
            if (empty($token)) {
                $headers = function_exists('getallheaders') ? getallheaders() : [];
                $token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? $headers['X-XSRF-Token'] ?? $headers['x-xsrf-token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_XSRF_TOKEN'] ?? '';
            }

            // Resolve from JSON payloads (e.g. php://input)
            if (empty($token)) {
                $json = json_decode(file_get_contents('php://input'), true);
                if (is_array($json) && isset($json['csrf'])) {
                    $token = $json['csrf'];
                }
            }

            if (!Security::csrfVerify($token)) {
                // If it's an administrative path or login attempt, redirect gracefully to the login page with a clean error message
                $requestUri = $_SERVER['REQUEST_URI'] ?? '';
                if (str_contains($requestUri, '/admin')) {
                    header('Location: /admin/login?error=csrf_verification_failed', true, 302);
                    exit();
                }

                http_response_code(400);
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Bad Request: Invalid or Missing CSRF security verification handshake';
                exit();
            }
        }

        return $next();
    }
}
