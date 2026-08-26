<?php

declare(strict_types=1);

/**
 * File: src/Http/Middleware/CsrfMiddleware.php
 * Architectural Purpose: HTTP request routing, request filtering middleware, or dynamic content-security controllers.
 * Package: Zero\Http\Middleware
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Http\Middleware;

use Zero\Core\App;
use Zero\Support\Security;

/**
 * Class CsrfMiddleware
 *
 * Verifies a CSRF token on every state-changing request (POST, PUT, PATCH, DELETE), accepting it
 * from the form body, an X-CSRF-Token/X-XSRF-Token header, or a JSON payload, and rejecting the
 * request when verification fails. AJAX/API callers (identified by how the token arrived, or by
 * an XMLHttpRequest/JSON Accept signal) get a JSON error body instead of the HTML-redirect/
 * plain-text response a plain <form> submission gets -- every admin *.js fetch() call already
 * checks `data.success`/`data.error` on its response, so an HTML or plain-text body just breaks
 * `res.json()` with an opaque "Unexpected token '<'" parse error instead of a real message.
 */
class CsrfMiddleware
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param callable $next Argument descriptor.
     * @return mixed Response output.
     */
    public function handle(callable $next)
    {
        App::ensureSession();

        $stateChangingMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        if (\in_array($_SERVER['REQUEST_METHOD'], $stateChangingMethods)) {
            $token = $_POST['csrf'] ?? '';
            $isAjax = false;

            // Resolve from Custom HTTP Headers (for AJAX/REST calls) -- a token arriving via
            // header rather than the form body means this is a fetch()/XHR call, not a <form> submit.
            if (empty($token)) {
                $headers = \function_exists('getallheaders') ? getallheaders() : [];
                $token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? $headers['X-XSRF-Token'] ?? $headers['x-xsrf-token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_XSRF_TOKEN'] ?? '';
                if (!empty($token)) {
                    $isAjax = true;
                }
            }

            // Resolve from JSON payloads (e.g. php://input)
            if (empty($token)) {
                $json = \json_decode(\file_get_contents('php://input'), true);
                if (\is_array($json) && isset($json['csrf'])) {
                    $token = $json['csrf'];
                    $isAjax = true;
                }
            }

            // Fallback signals for callers that still put the token in the form body (e.g. a
            // FormData-based fetch upload) but are still clearly AJAX/API requests.
            if (!$isAjax) {
                $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
                $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
                if (\strtolower($requestedWith) === 'xmlhttprequest' || \str_contains($accept, 'application/json')) {
                    $isAjax = true;
                }
            }

            if (!Security::csrfVerify($token)) {
                if ($isAjax) {
                    \http_response_code(403);
                    \header('Content-Type: application/json; charset=utf-8');
                    echo \json_encode([
                        'success' => false,
                        'error' => 'Your session security token has expired or is invalid. Please refresh the page and try again.',
                        'error_code' => 'csrf_invalid',
                    ]);
                    exit();
                }

                // If it's an administrative path or login attempt, redirect gracefully to the login page with a clean error message
                $requestUri = $_SERVER['REQUEST_URI'] ?? '';
                if (\str_contains($requestUri, '/admin')) {
                    \header('Location: /admin/login?error=csrf_verification_failed', true, 302);
                    exit();
                }

                \http_response_code(400);
                \header('Content-Type: text/plain; charset=utf-8');
                echo 'Bad Request: Invalid or Missing CSRF security verification handshake';
                exit();
            }
        }

        return $next();
    }
}
