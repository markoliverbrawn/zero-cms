<?php

namespace Zero\Support;

use Zero\Core\App;

class Security {
    public static function csrfInput() {
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars(self::csrfToken(), ENT_QUOTES, "UTF-8") . '">';
    }
    
    
    
    public static function csrfToken()
    {
        App::ensureSession();
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(16));
            $_SESSION['_csrf_token_time'] = time(); // Record creation time
        }
        return $_SESSION['_csrf_token'];
    }

    

    public static function csrfVerify($token)
    {
        App::ensureSession();
        if (empty($token)) return false;

        // Expiry check: 10 minutes (600 seconds)
        $tokenTime = $_SESSION['_csrf_token_time'] ?? 0;
        if ((time() - $tokenTime) > 600) {
            // Token has expired! Destroy it to prevent replay
            unset($_SESSION['_csrf_token']);
            unset($_SESSION['_csrf_token_time']);
            return false;
        }

        return hash_equals($_SESSION['_csrf_token'] ?? '', $token);
    }

    /**
     * Escape HTML entities to prevent XSS.
     */
    public static function escape(?string $value): string
    {
        return Str::escape($value);
    }

    /**
     * Dynamic Session-Based Rate Limiter (IP/Session scope protection)
     */
    public static function rateLimit(string $key, int $limitSeconds): bool
    {
        App::ensureSession();
        $now = time();
        $sessionKey = '_rate_limit_' . $key;
        $lastTime = $_SESSION[$sessionKey] ?? 0;

        if (($now - $lastTime) < $limitSeconds) {
            return false; // Rate limit exceeded!
        }

        $_SESSION[$sessionKey] = $now;
        return true;
    }

    /**
     * Pure PHP, dependency-free HTML sanitizer using DOMDocument.
     * Prevents XSS by stripping blacklisted tags, inline javascript, and dangerous protocols.
     */
    public static function sanitizeHtml(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Disable standard libxml errors to prevent warning output for incomplete HTML tags
        $internalErrors = libxml_use_internal_errors(true);

        $doc = new \DOMDocument();
        // Load with UTF-8 encoding declaration to handle special characters correctly
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        // Recursive tag and attribute scrubber
        $stripTags = ['script', 'iframe', 'object', 'embed', 'style', 'meta', 'link', 'form', 'input', 'button', 'textarea'];
        
        $xpath = new \DOMXPath($doc);
        
        // Remove blacklisted elements
        foreach ($stripTags as $tag) {
            $elements = $xpath->query("//" . $tag);
            foreach ($elements as $el) {
                $el->parentNode->removeChild($el);
            }
        }

        // Remove dangerous attributes (on*, javascript: URIs)
        $allElements = $xpath->query('//*');
        foreach ($allElements as $el) {
            $attrs = [];
            foreach ($el->attributes as $attr) {
                $attrs[] = $attr->name;
            }

            foreach ($attrs as $attrName) {
                $lowerAttr = strtolower($attrName);
                // Strip event handlers starting with "on" (e.g. onclick, onload, onerror)
                if (strpos($lowerAttr, 'on') === 0) {
                    $el->removeAttribute($attrName);
                    continue;
                }

                // Strip attributes with "javascript:" or "data:" URI protocols
                $attrValue = strtolower(trim($el->getAttribute($attrName)));
                if ($lowerAttr === 'href' || $lowerAttr === 'src') {
                    if (strpos($attrValue, 'javascript:') === 0 || strpos($attrValue, 'data:') === 0) {
                        $el->removeAttribute($attrName);
                    }
                }
            }
        }

        // Save and clean the output HTML
        $cleaned = $doc->saveHTML();
        // Remove the xml encoding header
        $cleaned = str_replace('<?xml encoding="utf-8" ?>', '', $cleaned);
        
        $cleaned = trim($cleaned);

        // Automatically highlight any inline pre/code blocks!
        return Str::highlightHtml($cleaned);
    }

    /**
     * Globally sanitise incoming user inputs recursively.
     */
    public static function sanitizeInput($data, bool $stripHtml = false, array $exceptKeys = ['password', 'confirm_password', 'content', 'description'])
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                // Skip sanitising raw values for passwords and rich HTML blocks
                $shouldStrip = $stripHtml && !in_array($key, $exceptKeys);
                $data[$key] = self::sanitizeInput($value, $shouldStrip, $exceptKeys);
            }
            return $data;
        }

        if (is_string($data)) {
            // Prevent null-byte injection attacks (\0 / %00)
            $data = str_replace(chr(0), '', $data);
            
            // Trim surrounding whitespace
            $data = trim($data);
            
            if ($stripHtml) {
                $data = strip_tags($data);
            }
        }

        return $data;
    }

    /**
     * Generate a cryptographically secure, time-ordered UUIDv7 (RFC 9562).
     */
    public static function uuidv7(): string
    {
        // Get current millisecond timestamp
        $time = microtime(true);
        $milli = floor($time * 1000);

        // Convert millisecond timestamp to hexadecimal string padding left
        $timeHex = str_pad(base_convert($milli, 10, 16), 12, '0', STR_PAD_LEFT);

        // Generate 16 bytes of cryptographically secure random entropy
        $randBytes = random_bytes(10);

        // Extract and map fields conforming with RFC 9562 layout
        $randHex1 = bin2hex(substr($randBytes, 0, 2));
        $randHex2 = bin2hex(substr($randBytes, 2, 8));

        // Inject version (7) and variant (2) bits
        $varAndVer = '7' . substr($randHex1, 1);
        $variant = base_convert((hexdec(substr($randHex2, 0, 1)) & 0x03) | 0x08, 10, 16) . substr($randHex2, 1);

        // Format and return as a standard 36-character time-ordered UUIDv7 string key
        return sprintf('%s-%s-%s-%s-%s',
            substr($timeHex, 0, 8),
            substr($timeHex, 8, 4),
            $varAndVer,
            substr($variant, 0, 4),
            substr($variant, 4, 12)
        );
    }
}
