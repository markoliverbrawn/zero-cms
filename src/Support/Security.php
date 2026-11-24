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
     * Seconds of inactivity (no page render issuing the token, no successful verification) after
     * which a CSRF token expires.
     */
    private const CSRF_TOKEN_TTL = 600;

    /**
     * Attributes sanitizeHtml() keeps on any allowed element (plus data-* and aria-*).
     */
    private const SANITIZE_GLOBAL_ATTRIBUTES = ['class', 'dir', 'id', 'lang', 'role', 'style', 'title'];

    /**
     * Elements sanitizeHtml() keeps, mapped to the element-specific attributes they may carry.
     */
    private const SANITIZE_ALLOWED_TAGS = [
        'a' => ['href', 'hreflang', 'name', 'rel', 'target'],
        'abbr' => [], 'article' => [], 'aside' => [],
        'audio' => ['autoplay', 'controls', 'loop', 'muted', 'preload', 'src'], 'b' => [], 'blockquote' => ['cite'], 'br' => [],
        'caption' => [], 'cite' => [], 'code' => [], 'col' => ['span', 'width'], 'colgroup' => ['span'],
        'dd' => [], 'del' => ['cite', 'datetime'], 'details' => ['open'], 'div' => ['align'], 'dl' => [],
        'dt' => [], 'em' => [], 'figcaption' => [], 'figure' => [], 'footer' => [], 'h1' => ['align'],
        'h2' => ['align'], 'h3' => ['align'], 'h4' => ['align'], 'h5' => ['align'], 'h6' => ['align'],
        'header' => [], 'hr' => [], 'i' => [],
        'img' => ['alt', 'decoding', 'height', 'loading', 'sizes', 'src', 'srcset', 'width'],
        'ins' => ['cite', 'datetime'], 'kbd' => [], 'li' => ['value'], 'mark' => [], 'nav' => [],
        'ol' => ['reversed', 'start', 'type'], 'p' => ['align'], 'picture' => [], 'pre' => [],
        'q' => ['cite'], 's' => [], 'samp' => [], 'section' => [], 'small' => [],
        'source' => ['media', 'sizes', 'src', 'srcset', 'type'], 'span' => [], 'strike' => [],
        'strong' => [], 'sub' => [], 'summary' => [], 'sup' => [],
        'table' => ['border', 'cellpadding', 'cellspacing', 'summary', 'width'], 'tbody' => [],
        'td' => ['align', 'colspan', 'headers', 'rowspan', 'valign', 'width'], 'tfoot' => [],
        'th' => ['abbr', 'align', 'colspan', 'headers', 'rowspan', 'scope', 'valign', 'width'],
        'thead' => [], 'time' => ['datetime'], 'tr' => ['align', 'valign'], 'u' => [], 'ul' => [],
        'var' => [],
        'video' => ['autoplay', 'controls', 'height', 'loop', 'muted', 'playsinline', 'poster', 'preload', 'src', 'width'],
    ];

    /**
     * URL schemes allowed in URL-bearing attributes. Scheme-less (relative) URLs are always allowed.
     */
    private const SANITIZE_ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Elements removed together with their contents rather than unwrapped.
     */
    private const SANITIZE_DROPPED_TAGS = [
        'applet', 'base', 'button', 'canvas', 'embed', 'form', 'frame', 'frameset', 'iframe',
        'input', 'link', 'math', 'meta', 'noembed', 'noframes', 'noscript', 'object', 'option',
        'plaintext', 'script', 'select', 'style', 'svg', 'template', 'textarea', 'title', 'xmp',
    ];

    /**
     * Attributes whose value is a single URL.
     */
    private const SANITIZE_URL_ATTRIBUTES = ['cite', 'href', 'poster', 'src'];

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
     * Resolve the request's Host, for use anywhere a host/domain gets embedded in output that
     * matters (tenant resolution, links in outbound emails, etc). X-Forwarded-Host is otherwise
     * trivially spoofable by anyone hitting the origin directly -- e.g. injecting an
     * attacker-controlled domain into a legitimate transactional email's links -- so it's only
     * honored once TRUSTED_PROXY_SECRET is configured and isTrustedProxyRequest() verifies it
     * (falling back to today's unconditional trust when the secret is unset, so deployments that
     * don't use this plumbing see no change).
     */
    public static function resolveTrustedHost(?string $fallback = null): string
    {
        $forwardedHostTrusted = Env::get('TRUSTED_PROXY_SECRET', '') === '' || self::isTrustedProxyRequest();
        return ($forwardedHostTrusted ? ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? null) : null)
            ?? $_SERVER['HTTP_HOST']
            ?? $fallback
            ?? 'localhost';
    }

    /**
     * Resolve the scheme ('https' or 'http') the visitor actually used for the current request.
     * Behind a reverse proxy (Cloudflare, Cloud Run) TLS terminates upstream, so PHP sees a plain
     * HTTP request and $_SERVER['HTTPS'] is unset -- the original scheme only arrives via
     * X-Forwarded-Proto (first value when more than one proxy appended to the header). That header
     * is otherwise trivially spoofable by anyone hitting the origin directly, so it's only honored
     * once TRUSTED_PROXY_SECRET is configured and isTrustedProxyRequest() verifies it (falling back
     * to today's unconditional trust when the secret is unset, so deployments that don't use this
     * plumbing see no change) -- the same gating resolveTrustedHost() applies to X-Forwarded-Host.
     */
    public static function resolveScheme(): string
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return 'https';
        }

        $forwardedProtoTrusted = Env::get('TRUSTED_PROXY_SECRET', '') === '' || self::isTrustedProxyRequest();
        if ($forwardedProtoTrusted) {
            $forwardedProto = \strtolower(\trim(\explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
            if ($forwardedProto === 'https') {
                return 'https';
            }
        }

        return 'http';
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
        $tokenTime = $_SESSION['_csrf_token_time'] ?? 0;
        if (empty($_SESSION['_csrf_token']) || (\time() - $tokenTime) > self::CSRF_TOKEN_TTL) {
            // Missing, or already past the inactivity window: issue a fresh token rather than
            // re-serving one csrfVerify() would reject.
            $_SESSION['_csrf_token'] = \bin2hex(\random_bytes(16));
        }
        // Rendering the token into a page is activity too. Without this, a page loaded after 10
        // minutes of GET-only browsing embedded a token that was already about to expire, so its
        // very first POST (e.g. a media upload) failed with a session-token error.
        $_SESSION['_csrf_token_time'] = \time();
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

        // Expiry check: 10 minutes (CSRF_TOKEN_TTL) of inactivity
        $tokenTime = $_SESSION['_csrf_token_time'] ?? 0;
        if ((\time() - $tokenTime) > self::CSRF_TOKEN_TTL) {
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
     * Pure PHP, dependency-free HTML sanitiser using DOMDocument. Pass $highlight = false when the
     * result feeds an editor rather than a page.
     *
     * Allow-list based: only SANITIZE_ALLOWED_TAGS survive. Unknown tags are unwrapped (their text
     * is kept) except SANITIZE_DROPPED_TAGS, which are removed with their contents because they can
     * execute script, change how the rest of the page resolves URLs, or parse differently in a
     * browser than in libxml (mutation XSS). Attributes are allow-listed too, and URL-bearing
     * attributes may only use SANITIZE_ALLOWED_SCHEMES or be relative.
     */
    public static function sanitizeHtml(string $html, bool $highlight = true): string
    {
        if (empty($html)) {
            return '';
        }

        // Disable standard libxml errors to prevent warning output for incomplete HTML tags
        $internalErrors = \libxml_use_internal_errors(true);

        $doc = new \DOMDocument();
        // Parse a complete document (meta charset for UTF-8) with the fragment inside a known
        // wrapper, then serialise only the wrapper's children. The older XML encoding-declaration prefix
        // plus LIBXML_HTML_NOIMPLIED broke under libxml2 >= 2.14's rewritten HTML parser: the
        // prefix survived as a comment and top-level text between tags was dropped.
        $doc->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="zero-sanitize-root">'
            . $html
            . '</div></body></html>'
        );

        \libxml_clear_errors();
        \libxml_use_internal_errors($internalErrors);

        $root = $doc->getElementById('zero-sanitize-root');
        if ($root !== null) {
            self::scrubHtmlChildren($root);
        }

        // Serialise just the wrapper's children
        $cleaned = '';
        if ($root !== null) {
            foreach ($root->childNodes as $child) {
                $cleaned .= $doc->saveHTML($child);
            }
        }

        $cleaned = \trim($cleaned);

        // Automatically highlight any inline pre/code blocks (skipped for editor round-trips, so
        // the highlighter's wrapper markup is never saved back into content).
        return $highlight ? Str::highlightHtml($cleaned) : $cleaned;
    }

    /**
     * Whether a URL is safe to place in an href/src: relative, or using http, https, mailto or tel.
     * Control characters and whitespace are ignored when finding the scheme, as browsers do.
     *
     * @param string $url Candidate URL.
     * @return bool
     */
    public static function isSafeUrl(string $url): bool
    {
        return self::isSafeHtmlUrl($url);
    }

    /**
     * Whether a URL attribute value is safe: relative, or using an allowed scheme. ASCII control
     * characters and whitespace are ignored when finding the scheme, as browsers ignore them too
     * (so "java\tscript:" is still treated as javascript:).
     *
     * @param string $url Attribute value as decoded by the parser.
     * @return bool
     */
    private static function isSafeHtmlUrl(string $url): bool
    {
        $normalised = \strtolower((string) \preg_replace('/[\x00-\x20\x7F]+/', '', $url));
        if (!\preg_match('/^([a-z][a-z0-9+.\-]*):/', $normalised, $match)) {
            return true;
        }

        return \in_array($match[1], self::SANITIZE_ALLOWED_SCHEMES, true);
    }

    /**
     * Strip every attribute from an allowed element that is not on its allow-list, or that carries
     * an unsafe URL or style value. Links opening a new window also get rel="noopener noreferrer".
     *
     * @param \DOMElement $element Element whose attributes are scrubbed in place.
     * @return void
     */
    private static function scrubHtmlAttributes(\DOMElement $element): void
    {
        $tag = \strtolower($element->nodeName);
        $allowed = \array_merge(self::SANITIZE_GLOBAL_ATTRIBUTES, self::SANITIZE_ALLOWED_TAGS[$tag] ?? []);

        $names = [];
        foreach ($element->attributes as $attribute) {
            $names[] = $attribute->nodeName;
        }

        foreach ($names as $name) {
            $lower = \strtolower($name);
            $value = $element->getAttribute($name);

            $keep = \in_array($lower, $allowed, true)
                || \preg_match('/^(aria|data)-[a-z0-9_.\-]+$/', $lower) === 1;

            if ($keep && \in_array($lower, self::SANITIZE_URL_ATTRIBUTES, true)) {
                $keep = self::isSafeHtmlUrl($value);
            }

            if ($keep && $lower === 'srcset') {
                foreach (\explode(',', $value) as $candidate) {
                    $candidateUrl = \preg_split('/\s+/', \trim($candidate))[0] ?? '';
                    if (!self::isSafeHtmlUrl($candidateUrl)) {
                        $keep = false;
                        break;
                    }
                }
            }

            if ($keep && $lower === 'style') {
                $keep = \preg_match('/expression\s*\(|url\s*\(|@import|behavior\s*:|javascript:|[<>\\\\]/i', $value) !== 1;
            }

            if (!$keep) {
                $element->removeAttribute($name);
            }
        }

        if ($tag === 'a' && \strtolower($element->getAttribute('target')) === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    /**
     * Recursively scrub a node's children: drop comments and dangerous elements with their
     * contents, unwrap unknown elements (keeping their scrubbed children), and scrub the
     * attributes of allowed elements.
     *
     * @param \DOMNode $node Parent whose children are scrubbed in place.
     * @return void
     */
    private static function scrubHtmlChildren(\DOMNode $node): void
    {
        $child = $node->firstChild;
        while ($child !== null) {
            $next = $child->nextSibling;

            if ($child instanceof \DOMElement) {
                $tag = \strtolower($child->nodeName);

                if (\in_array($tag, self::SANITIZE_DROPPED_TAGS, true)) {
                    $node->removeChild($child);
                } elseif (!isset(self::SANITIZE_ALLOWED_TAGS[$tag])) {
                    self::scrubHtmlChildren($child);
                    while ($child->firstChild !== null) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                } else {
                    self::scrubHtmlAttributes($child);
                    self::scrubHtmlChildren($child);
                }
            } elseif (!($child instanceof \DOMText) || $child instanceof \DOMCdataSection) {
                // Comments, processing instructions, CDATA: never needed in content.
                $node->removeChild($child);
            }

            $child = $next;
        }
    }

    /**
     * Verify a submitted password against a stored hash, refusing inputs that password_verify()
     * would otherwise mishandle: non-strings (e.g. password[]= in a form post), empty passwords,
     * passwords containing NUL (bcrypt truncates at the first NUL byte), and empty/absent hashes.
     *
     * @param mixed $password Submitted password, straight from the request.
     * @param mixed $hash Stored password hash.
     * @return bool
     */
    public static function verifyPassword($password, $hash): bool
    {
        if (!\is_string($password) || $password === '' || \str_contains($password, "\0")) {
            return false;
        }

        if (!\is_string($hash) || $hash === '') {
            return false;
        }

        return \password_verify($password, $hash);
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
