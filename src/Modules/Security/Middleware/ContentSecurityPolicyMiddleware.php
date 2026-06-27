<?php

namespace Zero\Modules\Security\Middleware;

class ContentSecurityPolicyMiddleware
{
    public function handle(callable $next)
    {
        // Enforce strong Content Security Policy (CSP) headers to mitigate XSS and clickjacking
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https: http:; " .
               "style-src 'self' 'unsafe-inline' https: http:; " .
               "img-src 'self' data: https: http:; " .
               "font-src 'self' data: https: http:; " .
               "connect-src 'self' https: http:; " .
               "frame-src 'self' https: http:; " .
               "object-src 'none'; " .
               "base-uri 'self'; " .
               "form-action 'self' https: http:;";

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
