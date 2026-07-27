<?php

namespace Zero\Http\Middleware;

use Zero\Support\Security;

class RateLimitMiddleware
{
    /**
     * Intercept state-changing or high-frequency requests to apply session-scoped throttling.
     *
     * @param string $key The unique rate limiting partition key.
     * @param int $limitSeconds The lock duration in seconds.
     * @param callable $next The next middleware or controller callback on success.
     */
    public static function handle(string $key, int $limitSeconds, callable $next)
    {
        if (!Security::rateLimit($key, $limitSeconds)) {
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            header('Retry-After: ' . $limitSeconds);
            echo json_encode([
                'success' => false,
                'error' => 'Rate limit exceeded: Please wait ' . $limitSeconds . ' seconds before trying again.'
            ]);
            exit();
        }

        return $next();
    }
}
