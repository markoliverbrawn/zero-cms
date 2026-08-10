<?php
/**
 * File: src/Modules/Security/Middleware/ContentSecurityPolicyMiddleware.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Security\Middleware
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */


// src/Modules/Security/Middleware/ContentSecurityPolicyMiddleware.php

namespace Zero\Modules\Security\Middleware;

use Exception;
use Zero\Core\App;
use Zero\Core\Env;

/**
 * Class ContentSecurityPolicyMiddleware
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class ContentSecurityPolicyMiddleware
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param callable $next Argument descriptor.
     * @return mixed Response output.
     */
    public function handle(callable $next)
    {
        // Generate a dynamic secure CSP cryptographic nonce
        $nonce = '';
        try {
            $nonce = base64_encode(random_bytes(16));
        } catch (Exception $e) {
            $nonce = base64_encode(uniqid('', true));
        }
        
        App::setNonce($nonce);

        // Detect if active connection is secure/HTTPS
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
                || ($_SERVER['SERVER_PORT'] ?? '') == 443 
                || (Env::get('STORAGE_DRIVER') === 's3');

        // Enforce strong Content Security Policy (CSP) headers to mitigate XSS and clickjacking
        $csp = "default-src 'self'; " .
               "script-src 'self' 'nonce-" . $nonce . "' 'unsafe-inline'; " . // Strictly load scripts with local origin ('self') & nonce fallbacks!
               "style-src 'self' 'unsafe-inline'; " . // Allow local styles and custom property inline variables
               "img-src 'self' data: https: http:; " . // Allow local and secure external images
               "font-src 'self' data: https: http:; " . // Allow fonts over both protocols to prevent CORS protocol-upgrade blocks on dev domains!
               "media-src 'self' data: https: http:; " . // Allow local and secure external media/videos to stream perfectly!
               "connect-src 'self'; " . // Restrict AJAX connections strictly to local API endpoints
               "frame-src 'self'; " . // Restrict iframe loading strictly to local block previews
               "object-src 'none'; " . // Completely block insecure object plugins
               "base-uri 'self'; " . // Prevent base href hijacking
               "form-action 'self';"; // Restrict form submissions strictly to local controllers

        if ($isHttps) {
            $csp .= " upgrade-insecure-requests;";
        }

        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            header("Content-Security-Policy: " . $csp);

            // Enforce other high-impact, standard security response headers
            header("X-Frame-Options: SAMEORIGIN");
            header("X-Content-Type-Options: nosniff");
            header("X-XSS-Protection: 1; mode=block");
            header("Referrer-Policy: strict-origin-when-cross-origin");
        }

        return $next();
    }
}
