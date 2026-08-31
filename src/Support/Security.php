<?php

declare(strict_types=1);

/**
 * File: src/Support/Security.php
 * Architectural Purpose: Global diagnostic tools, cryptographic security handlers, SMTP email transmitters, and text helpers.
 * Package: Zero\Support
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support;

use Zero\Core\App;
use Zero\Core\Env;
use Zero\Database\DB;
use Zero\Support\Str;

/**
 * Class Security
 *
 * The engine's security primitives in one place: CSRF token issue and verification, request and
 * authentication rate limiting, HTML/SVG sanitisation, input escaping, UUIDv7 generation, and the
 * allow-list checks that validate SQL identifiers before they are interpolated into a statement.
 */
class Security {
    /**
     * True only when TRUSTED_PROXY_SECRET is configured and the request's X-Proxy-Secret header
     * matches it (constant-time). Deployments that sit behind a reverse proxy which originates its
     * own request to origin (e.g. a Cloudflare Worker's fetch()) can set this env var and have the
     * proxy inject the matching header, so the app can safely trust proxy-supplied headers that
     * would otherwise be trivially spoofable by anyone hitting the origin directly.
     */
    public static function isTrustedProxyRequest(): bool
    {
        $secret = Env::get('TRUSTED_PROXY_SECRET', '');
        if ($secret === '') {
            return false;
        }
        return \hash_equals($secret, $_SERVER['HTTP_X_PROXY_SECRET'] ?? '');
    }

    /**
     * Resolve the real client IP. Only trusts the CF-Connecting-IP header from a verified trusted
     * proxy (see isTrustedProxyRequest()) -- without that verification, REMOTE_ADDR is the only
     * value that can't be forged by the client, even though it reports the proxy's own address
     * (not the visitor's) whenever one sits in front of the origin.
     */
    public static function getClientIp(): string
    {
        if (self::isTrustedProxyRequest() && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Check if authentication attempts are exceeded for a combination of IP and identifier.
     * Action parameter can be 'login' or 'password_reset'.
     */
    public static function checkAuthRateLimit(string $action, string $identifier, int $maxAttempts = 5, int $decaySeconds = 900): bool
    {
        $ip = self::getClientIp();
        $timeWindow = \gmdate('Y-m-d H:i:s', \time() - $decaySeconds);

        // Map general actions to database actions
        $failedActions = [];
        if ($action === 'login') {
            $failedActions = ['login_failed', 'frontend_login_failed'];
        } elseif ($action === 'password_reset') {
            $failedActions = ['password_reset_failed', 'password_reset_request_failed'];
        } elseif ($action === 'demo_creation') {
            $failedActions = ['demo_creation_failed', 'demo_creation_success'];
        }

        if (empty($failedActions)) {
            return true;
        }

        // Prepare query with correct in clause
        $placeholders = \implode(',', \array_fill(0, \count($failedActions), '?'));
        $params = \array_merge($failedActions, [$timeWindow]);

        $logs = DB::query("
            SELECT meta FROM audit_logs
            WHERE action IN ({$placeholders})
              AND created_at >= ?
              AND deleted_at IS NULL
        ", $params)->fetchAll();

        $attempts = 0;
        foreach ($logs as $log) {
            $meta = \json_decode($log['meta'] ?? '{}', true);
            $metaIp = $meta['ip_address'] ?? '';
            $metaUser = $meta['username'] ?? '';

            if ($metaIp === $ip || (!empty($metaUser) && \strtolower($metaUser) === \strtolower($identifier))) {
                $attempts++;
            }
        }

        return $attempts < $maxAttempts;
    }

    /**
     * Csrf input processing implementation helper.
     *
     * @return mixed Response output.
     */
    public static function csrfInput() {
        return '<input type="hidden" name="csrf" value="' . Str::escape(self::csrfToken()) . '">';
    }

    /**
     * Csrf token processing implementation helper.
     *
     * @return mixed Response output.
     */
    public static function csrfToken()
    {
        App::ensureSession();
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = \bin2hex(\random_bytes(16));
            $_SESSION['_csrf_token_time'] = \time(); // Record creation time
        }
        return $_SESSION['_csrf_token'];
    }

    /**
     * Csrf verify processing implementation helper.
     *
     * @param mixed $token Argument descriptor.
     * @return mixed Response output.
     */
    public static function csrfVerify($token)
    {
        App::ensureSession();
        if (empty($token)) return false;

        // Expiry check: 10 minutes (600 seconds) of inactivity
        $tokenTime = $_SESSION['_csrf_token_time'] ?? 0;
        if ((\time() - $tokenTime) > 600) {
            // Token has expired! Destroy it to prevent replay
            unset($_SESSION['_csrf_token']);
            unset($_SESSION['_csrf_token_time']);
            return false;
        }

        $valid = \hash_equals($_SESSION['_csrf_token'] ?? '', $token);
        if ($valid) {
            // Sliding expiry: a successful check resets the inactivity window, so the same
            // token (never rotated) keeps working across an actively-used session instead of
            // dying exactly 10 minutes after the page that rendered it was first loaded.
            $_SESSION['_csrf_token_time'] = \time();
        }
        return $valid;
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
        $now = \time();
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
        $internalErrors = \libxml_use_internal_errors(true);

        $doc = new \DOMDocument();
        // Load with UTF-8 encoding declaration to handle special characters correctly
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        \libxml_clear_errors();
        \libxml_use_internal_errors($internalErrors);

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
                $lowerAttr = \strtolower($attrName);
                // Strip event handlers starting with "on" (e.g. onclick, onload, onerror)
                if (\strpos($lowerAttr, 'on') === 0) {
                    $el->removeAttribute($attrName);
                    continue;
                }

                // Strip attributes with "javascript:" or "data:" URI protocols
                $attrValue = \strtolower(\trim($el->getAttribute($attrName)));
                if ($lowerAttr === 'href' || $lowerAttr === 'src') {
                    if (\strpos($attrValue, 'javascript:') === 0 || \strpos($attrValue, 'data:') === 0) {
                        $el->removeAttribute($attrName);
                    }
                }
            }
        }

        // Save and clean the output HTML
        $cleaned = $doc->saveHTML();
        // Remove the xml encoding header
        $cleaned = \str_replace('<?xml encoding="utf-8" ?>', '', $cleaned);

        $cleaned = \trim($cleaned);

        // Automatically highlight any inline pre/code blocks!
        return Str::highlightHtml($cleaned);
    }

    /**
     * Sanitise a block title's rich text HTML down to inline formatting only.
     *
     * A block title's contenteditable field only exposes bold/italic/small formatting, but the
     * underlying contenteditable region still lets Enter or a paste inject block-level markup
     * (<p>, headings, lists, blockquotes...). Titles are always rendered inside their own
     * heading wrapper (<h1>/<h2>/<h3>), so any block-level tag surviving sanitizeHtml() would
     * nest illegally inside it. Collapse line-separating tags to a space, then strip anything
     * that isn't inline formatting.
     */
    public static function sanitizeTitleHtml(string $html): string
    {
        $html = self::sanitizeHtml($html);
        if ($html === '') {
            return '';
        }

        $html = \preg_replace('#</(p|div|h[1-6]|li|blockquote|tr)\s*>#i', ' ', $html);
        $html = \strip_tags($html, '<b><strong><i><em><u><small><br>');

        return \trim(\preg_replace('/\s+/', ' ', $html));
    }

    /**
     * Globally sanitise incoming user inputs recursively.
     */
    public static function sanitizeInput($data, bool $stripHtml = false, array $exceptKeys = ['password', 'confirm_password', 'content', 'description'])
    {
        if (\is_array($data)) {
            foreach ($data as $key => $value) {
                // Skip sanitising raw values for passwords and rich HTML blocks
                $shouldStrip = $stripHtml && !\in_array($key, $exceptKeys);
                $data[$key] = self::sanitizeInput($value, $shouldStrip, $exceptKeys);
            }
            return $data;
        }

        if (\is_string($data)) {
            // Prevent null-byte injection attacks (\0 / %00)
            $data = \str_replace(\chr(0), '', $data);

            // Trim surrounding whitespace
            $data = \trim($data);

            if ($stripHtml) {
                $data = \strip_tags($data);
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
        $time = \microtime(true);
        $milli = \floor($time * 1000);

        // Convert millisecond timestamp to hexadecimal string padding left
        $timeHex = \str_pad(\base_convert((string)$milli, 10, 16), 12, '0', STR_PAD_LEFT);

        // Generate 16 bytes of cryptographically secure random entropy
        $randBytes = \random_bytes(10);

        // Extract and map fields conforming with RFC 9562 layout
        $randHex1 = \bin2hex(\substr($randBytes, 0, 2));
        $randHex2 = \bin2hex(\substr($randBytes, 2, 8));

        // Inject version (7) and variant (2) bits
        $varAndVer = '7' . \substr($randHex1, 1);
        $variant = \base_convert((string)((\hexdec(\substr($randHex2, 0, 1)) & 0x03) | 0x08), 10, 16) . \substr($randHex2, 1);

        // Format and return as a standard 36-character time-ordered UUIDv7 string key
        return \sprintf('%s-%s-%s-%s-%s',
            \substr($timeHex, 0, 8),
            \substr($timeHex, 8, 4),
            $varAndVer,
            \substr($variant, 0, 4),
            \substr($variant, 4, 12)
        );
    }

    /**
     * Validate a string is safe to interpolate directly into raw SQL as a table or column
     * identifier. Table/column names can't be bound via PDO placeholders the way values can,
     * so any code path that must build a query around a dynamic identifier (e.g. cascade-delete
     * child table/foreign-key metadata resolved via reflection) should reject anything that
     * doesn't match this strict allowlist before interpolating it.
     */
    public static function isSafeSqlIdentifier(string $identifier): bool
    {
        return (bool)\preg_match('/^[a-zA-Z0-9_]+$/', $identifier);
    }

    /**
     * Strict allow-list check: confirm $table actually exists in the connected database's live
     * schema (via SHOW TABLES, cached for the process lifetime) before it's interpolated into raw
     * SQL. Ties the identifier to ground truth rather than trusting character-class shape alone --
     * use alongside isSafeSqlIdentifier(), not instead of it.
     */
    public static function isKnownSqlTable(string $table): bool
    {
        static $tables = null;
        if ($tables === null) {
            try {
                $tables = DB::query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
            } catch (\Exception $e) {
                $tables = [];
            }
        }
        return \in_array($table, $tables, true);
    }

    /**
     * Strict allow-list check: confirm $column is an actual column of $table (via SHOW COLUMNS,
     * cached per table for the process lifetime). $table must already be validated via
     * isKnownSqlTable() before calling this, since it's interpolated into the SHOW COLUMNS query.
     */
    public static function isKnownSqlColumn(string $table, string $column): bool
    {
        static $columnsByTable = [];
        if (!isset($columnsByTable[$table])) {
            try {
                $rows = DB::query("SHOW COLUMNS FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
                $columnsByTable[$table] = \array_column($rows, 'Field');
            } catch (\Exception $e) {
                $columnsByTable[$table] = [];
            }
        }
        return \in_array($column, $columnsByTable[$table], true);
    }

    /**
     * Parse and sanitize XML/SVG files to strip active scripting vectors, dangerous elements, and CSS/style overrides (Stored XSS mitigation).
     */
    public static function sanitizeSvg(string $filePath): bool
    {
        if (!\file_exists($filePath)) {
            return false;
        }

        $content = \file_get_contents($filePath);
        if ($content === false) {
            return false;
        }

        // Hardened: Reject SVGs containing DOCTYPE or Entity declarations to completely block XXE
        if (\preg_match('/<!DOCTYPE/i', $content) || \preg_match('/<!ENTITY/i', $content)) {
            return false;
        }

        $dom = new \DOMDocument();

        // Disable external entities loading and DTD processing completely
        $libxmlState = \libxml_use_internal_errors(true);
        $success = $dom->loadXML($content, LIBXML_NONET | LIBXML_NOCDATA);
        \libxml_use_internal_errors($libxmlState);

        if (!$success) {
            return false;
        }

        // 1. Permanently remove all <script> tags
        $scripts = $dom->getElementsByTagName('script');
        while ($scripts->length > 0) {
            $script = $scripts->item(0);
            if ($script && $script->parentNode) {
                $script->parentNode->removeChild($script);
            }
        }

        // 2. Permanently remove all <foreignObject> tags (can house nested HTML elements / script bypasses)
        $foreignObjects = $dom->getElementsByTagName('foreignObject');
        while ($foreignObjects->length > 0) {
            $fo = $foreignObjects->item(0);
            if ($fo && $fo->parentNode) {
                $fo->parentNode->removeChild($fo);
            }
        }

        // 3. Permanently remove all <style> tags (prevents CSS/style injection and external fonts/import tracking)
        $styles = $dom->getElementsByTagName('style');
        while ($styles->length > 0) {
            $style = $styles->item(0);
            if ($style && $style->parentNode) {
                $style->parentNode->removeChild($style);
            }
        }

        // 4. Recursively traverse all nodes and strip style attributes, event handlers, and dangerous URI schemes
        $xpath = new \DOMXPath($dom);
        $allNodes = $xpath->query('//*');
        if ($allNodes) {
            foreach ($allNodes as $node) {
                if ($node instanceof \DOMElement && $node->hasAttributes()) {
                    $attrsToRemove = [];
                    foreach ($node->attributes as $attr) {
                        $attrName = \strtolower($attr->nodeName);

                        // Strip inline event triggers (onload, onclick, etc.)
                        if (\strpos($attrName, 'on') === 0) {
                            $attrsToRemove[] = $attr->nodeName;
                            continue;
                        }

                        // Strip style attributes completely to remove any CSS/style content
                        if ($attrName === 'style') {
                            $attrsToRemove[] = $attr->nodeName;
                            continue;
                        }

                        // Strip all dangerous URI schemes (javascript:, data:, vbscript:, file:, etc.) in any attribute
                        // Safe schemes are http, https, or relative paths/fragment IDs (no scheme separator ':')
                        if (\preg_match('/^\s*([a-zA-Z0-9+.-]+):/i', $attr->nodeValue, $matches)) {
                            $scheme = \strtolower($matches[1]);
                            if ($scheme !== 'http' && $scheme !== 'https') {
                                $attrsToRemove[] = $attr->nodeName;
                            }
                        }
                    }
                    foreach ($attrsToRemove as $attrName) {
                        $node->removeAttribute($attrName);
                    }
                }
            }
        }

        $sanitizedContent = $dom->saveXML();
        return \file_put_contents($filePath, $sanitizedContent) !== false;
    }
}
