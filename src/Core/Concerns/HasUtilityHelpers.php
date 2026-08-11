<?php

declare(strict_types=1);

/**
 * File: src/Core/Concerns/HasUtilityHelpers.php
 * Architectural Purpose: Miscellaneous small helpers with no state of their own beyond a
 * request-scoped CSP nonce: nonce access, slug generation, and inline SVG asset rendering.
 * Extracted out of App.php.
 * Package: Zero\Core\Concerns
 */

namespace Zero\Core\Concerns;

use Zero\Support\Str;

/**
 * Trait HasUtilityHelpers
 */
trait HasUtilityHelpers
{
    protected static $nonce = '';

    /**
     * Get the dynamic CSP cryptographic nonce.
     *
     * @return string
     */
    public static function getNonce(): string
    {
        return self::$nonce;
    }

    /**
     * Set the dynamic CSP cryptographic nonce.
     *
     * @param string $nonce
     * @return void
     */
    public static function setNonce(string $nonce): void
    {
        self::$nonce = $nonce;
    }

    /**
     * Slugify processing implementation helper.
     *
     * @param mixed $text Argument descriptor.
     * @return mixed Response output.
     */
    public static function slugify($text)
    {
        return Str::slug($text);
    }

    /**
     * Slashes-friendly URL path slugifier for manual parent-child page nesting.
     * Keeps method sorting alphabetically correct (slugify -> slugifyPath).
     */
    public static function slugifyPath($text)
    {
        return Str::slugPath($text);
    }

    /**
     * Render the content of an SVG file stored in assets/svgs/
     */
    public static function svg(string $name): string
    {
        $path = APPLICATION_ROOT . '/public/assets/svgs/' . $name . '.svg';
        if (\file_exists($path)) {
            return \file_get_contents($path);
        }
        return '';
    }
}
