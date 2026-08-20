<?php

declare(strict_types=1);

/**
 * File: src/Support/Str.php
 * Architectural Purpose: Global diagnostic tools, cryptographic security handlers, SMTP email transmitters, and text helpers.
 * Package: Zero\Support
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

// src/Support/Str.php

namespace Zero\Support;

/**
 * Class Str
 *
 * String helpers used across the engine: HTML escaping (the single approved escape path for view
 * output), URL slug generation, truncation, and syntax highlighting for rendered code blocks.
 */
class Str
{
    /**
     * Escape HTML entities to protect against Cross-Site Scripting (XSS).
     *
     * @param string|null $value
     * @return string
     */
    public static function escape(?string $value): string
    {
        return \htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * High-contrast zero-dependency code tokenizer
     *
     * @param string $code
     * @param string $language
     * @return string
     */
    public static function highlightCode(string $code, string $language): string
    {
        $html = \htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

        if ($language === 'php') {
            // Keywords
            $keywords = ['class', 'function', 'use', 'namespace', 'public', 'protected', 'private', 'static', 'return', 'new', 'if', 'else', 'foreach', 'as', 'try', 'catch', 'match', 'default', 'interface', 'extends', 'implements', 'define'];
            $html = \preg_replace_callback('/\b(' . \implode('|', $keywords) . ')\b/', function($m) {
                return '<span class="tok-keyword">' . $m[1] . '</span>';
            }, $html);

            // Variables
            $html = \preg_replace_callback('/(\$[a-zA-Z_][a-zA-Z0-9_]*)/', function($m) {
                return '<span class="tok-var">' . $m[1] . '</span>';
            }, $html);

            // Strings (double and single quoted)
            $html = \preg_replace_callback('/(&quot;.*?&quot;|&#039;.*?&#039;)/s', function($m) {
                return '<span class="tok-str">' . $m[1] . '</span>';
            }, $html);

            // Functions
            $html = \preg_replace_callback('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*(?=\(|&lt;)/', function($m) {
                return '<span class="tok-func">' . $m[1] . '</span>';
            }, $html);

            // Comments (Multi-line)
            $html = \preg_replace_callback('/(\/\*.*?\*\/)/s', function($m) {
                return '<span class="tok-comment">' . $m[1] . '</span>';
            }, $html);

            // Comments (Single-line)
            $html = \preg_replace_callback('/(\/\/.*|(?<!&)#.*)/', function($m) {
                return '<span class="tok-comment">' . $m[1] . '</span>';
            }, $html);

        } elseif ($language === 'json') {
            // Keys: "key":
            $html = \preg_replace_callback('/(&quot;[a-zA-Z0-9_]+&quot;)\s*:/', function($m) {
                return '<span class="tok-key">' . $m[1] . '</span>:';
            }, $html);

            // Strings
            $html = \preg_replace_callback('/:\s*(&quot;.*?&quot;)/', function($m) {
                return ': <span class="tok-str">' . $m[1] . '</span>';
            }, $html);

            // Numbers
            $html = \preg_replace_callback('/\b(\d+)\b/', function($m) {
                return '<span class="tok-num">' . $m[1] . '</span>';
            }, $html);

        } elseif ($language === 'html') {
            // HTML Tags
            $html = \preg_replace_callback('/(&lt;\/?[a-zA-Z0-9]+(?:\s+[^&]*)?&gt;)/', function($m) {
                return '<span class="tok-tag">' . $m[1] . '</span>';
            }, $html);

            // Double quoted attributes inside tags (e.g. class="...")
            $html = \preg_replace_callback('/([a-zA-Z_:-]+)=(&quot;.*?&quot;)/', function($m) {
                return '<span class="tok-var">' . $m[1] . '</span>=<span class="tok-str">' . $m[2] . '</span>';
            }, $html);

        } elseif ($language === 'javascript') {
            // Keywords
            $keywords = ['const', 'let', 'var', 'function', 'return', 'if', 'else', 'for', 'while', 'new', 'class', 'import', 'from', 'export', 'default'];
            $html = \preg_replace_callback('/\b(' . \implode('|', $keywords) . ')\b/', function($m) {
                return '<span class="tok-keyword">' . $m[1] . '</span>';
            }, $html);

            // Strings
            $html = \preg_replace_callback('/(&quot;.*?&quot;|&#039;.*?&#039;)/s', function($m) {
                return '<span class="tok-str">' . $m[1] . '</span>';
            }, $html);

            // Functions
            $html = \preg_replace_callback('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*(?=\()/', function($m) {
                return '<span class="tok-func">' . $m[1] . '</span>';
            }, $html);

            // Comments (Multi-line)
            $html = \preg_replace_callback('/(\/\*.*?\*\/)/s', function($m) {
                return '<span class="tok-comment">' . $m[1] . '</span>';
            }, $html);

            // Comments (Single-line)
            $html = \preg_replace_callback('/(\/\/.*|(?<!&)#.*)/', function($m) {
                return '<span class="tok-comment">' . $m[1] . '</span>';
            }, $html);

        } elseif ($language === 'bash') {
            // Comments
            $html = \preg_replace_callback('/((?<!&)#.*)/', function($m) {
                return '<span class="tok-comment">' . $m[1] . '</span>';
            }, $html);

            // Commands (first word of line)
            $html = \preg_replace_callback('/^\s*([a-zA-Z_-]+)\b/m', function($m) {
                return '<span class="tok-keyword">' . $m[1] . '</span>';
            }, $html);
        }

        return $html;
    }

    /**
     * Parse HTML and automatically apply high-contrast tokenization highlight classes
     * to any inline <pre><code class="language-...">...</code></pre> structures.
     *
     * @param string $html
     * @return string
     */
    public static function highlightHtml(string $html): string
    {
        if (empty($html)) {
            return '';
        }

        return \preg_replace_callback(
            '/<pre>\s*<code class=["\']language-([a-zA-Z0-9_-]+)["\']>(.*?)<\/code>\s*<\/pre>/s',
            function ($matches) {
                $language = $matches[1];
                $escapedCode = $matches[2];
                
                // Decode the HTML entities so we get raw code characters back
                $rawCode = \html_entity_decode($escapedCode, ENT_QUOTES, 'UTF-8');
                
                // Highlight the raw code using our custom static parser
                $highlighted = self::highlightCode($rawCode, $language);
                
                // Return wrapped in standard block-code elements so it matches style sheets
                return '<div class="block-code-container">'
                    . '<pre class="block-code-pre"><code class="language-' . \htmlspecialchars($language, ENT_QUOTES, 'UTF-8') . '">'
                    . $highlighted
                    . '</code></pre></div>';
            },
            $html
        );
    }

    /**
     * Accented-letter to ASCII map used by slug(). Deliberately explicit rather than delegating to
     * iconv()'s '//TRANSLIT': that transliteration is supplied by the host system's iconv
     * implementation, so the same input slugs differently (or fails outright, returning false and
     * collapsing the whole slug to 'n-a') depending on the libc and locale the deployment happens
     * to run on -- glibc renders 'é' as "'e", musl has no //TRANSLIT support at all. Covers Latin-1,
     * the Latin Extended-A letters used by the shipped hr dictionary, and macrons used by mi.
     *
     * @var array<string, string>
     */
    private static array $asciiFolding = [
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Ā' => 'A', 'Ă' => 'A', 'Ą' => 'A',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a', 'ă' => 'a', 'ą' => 'a',
        'Æ' => 'AE', 'æ' => 'ae',
        'Ç' => 'C', 'Ć' => 'C', 'Č' => 'C', 'Ĉ' => 'C', 'Ċ' => 'C',
        'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'ĉ' => 'c', 'ċ' => 'c',
        'Ď' => 'D', 'Đ' => 'D', 'Ð' => 'D', 'ď' => 'd', 'đ' => 'd', 'ð' => 'd',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ē' => 'E', 'Ĕ' => 'E', 'Ė' => 'E', 'Ę' => 'E', 'Ě' => 'E',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ĕ' => 'e', 'ė' => 'e', 'ę' => 'e', 'ě' => 'e',
        'Ĝ' => 'G', 'Ğ' => 'G', 'Ġ' => 'G', 'Ģ' => 'G', 'ĝ' => 'g', 'ğ' => 'g', 'ġ' => 'g', 'ģ' => 'g',
        'Ĥ' => 'H', 'Ħ' => 'H', 'ĥ' => 'h', 'ħ' => 'h',
        'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ĩ' => 'I', 'Ī' => 'I', 'Ĭ' => 'I', 'Į' => 'I', 'İ' => 'I',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ĩ' => 'i', 'ī' => 'i', 'ĭ' => 'i', 'į' => 'i', 'ı' => 'i',
        'Ĵ' => 'J', 'ĵ' => 'j', 'Ķ' => 'K', 'ķ' => 'k',
        'Ĺ' => 'L', 'Ļ' => 'L', 'Ľ' => 'L', 'Ł' => 'L', 'ĺ' => 'l', 'ļ' => 'l', 'ľ' => 'l', 'ł' => 'l',
        'Ñ' => 'N', 'Ń' => 'N', 'Ņ' => 'N', 'Ň' => 'N', 'ñ' => 'n', 'ń' => 'n', 'ņ' => 'n', 'ň' => 'n',
        'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O', 'Ō' => 'O', 'Ŏ' => 'O', 'Ő' => 'O',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ō' => 'o', 'ŏ' => 'o', 'ő' => 'o',
        'Œ' => 'OE', 'œ' => 'oe',
        'Ŕ' => 'R', 'Ŗ' => 'R', 'Ř' => 'R', 'ŕ' => 'r', 'ŗ' => 'r', 'ř' => 'r',
        'Ś' => 'S', 'Ŝ' => 'S', 'Ş' => 'S', 'Š' => 'S', 'ś' => 's', 'ŝ' => 's', 'ş' => 's', 'š' => 's',
        'ß' => 'ss',
        'Ţ' => 'T', 'Ť' => 'T', 'Ŧ' => 'T', 'Þ' => 'TH',
        'ţ' => 't', 'ť' => 't', 'ŧ' => 't', 'þ' => 'th',
        'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ũ' => 'U', 'Ū' => 'U', 'Ŭ' => 'U', 'Ů' => 'U', 'Ű' => 'U', 'Ų' => 'U',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ũ' => 'u', 'ū' => 'u', 'ŭ' => 'u', 'ů' => 'u', 'ű' => 'u', 'ų' => 'u',
        'Ŵ' => 'W', 'ŵ' => 'w',
        'Ý' => 'Y', 'Ŷ' => 'Y', 'Ÿ' => 'Y', 'ý' => 'y', 'ŷ' => 'y', 'ÿ' => 'y',
        'Ź' => 'Z', 'Ż' => 'Z', 'Ž' => 'Z', 'ź' => 'z', 'ż' => 'z', 'ž' => 'z',
    ];

    /**
     * Standard web-safe URL portion slugifier. Folds accented letters down to ASCII from the
     * explicit map above (host-independent), then drops anything left that is not a word character.
     *
     * @param string $text
     * @return string
     */
    public static function slug(string $text): string
    {
        $text = \strtr($text, self::$asciiFolding);
        $text = \preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = \preg_replace('~[^-\w]+~', '', (string)$text);
        $text = \trim((string)$text, '-');
        $text = \preg_replace('~-+~', '-', $text);
        $text = \strtolower((string)$text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }

    /**
     * Slashes-friendly URL path slugifier for manual parent-child page nesting.
     *
     * @param string $text
     * @return string
     */
    public static function slugPath(string $text): string
    {
        $segments = \explode('/', $text);
        $slugifiedSegments = [];
        foreach ($segments as $segment) {
            $slugified = self::slug($segment);
            if ($slugified !== '' && $slugified !== 'n-a') {
                $slugifiedSegments[] = $slugified;
            }
        }
        return empty($slugifiedSegments) ? 'n-a' : \implode('/', $slugifiedSegments);
    }

    /**
     * Truncate a string to a specific length in a multi-byte safe manner.
     *
     * @param string $text
     * @param int $limit
     * @param string $end
     * @return string
     */
    public static function truncate(string $text, int $limit, string $end = '...'): string
    {
        if (\mb_strlen($text) <= $limit) {
            return $text;
        }
        return \mb_substr($text, 0, $limit) . $end;
    }
}
