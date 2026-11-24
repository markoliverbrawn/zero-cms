<?php

declare(strict_types=1);

/**
 * File: src/Support/BlockHelper.php
 * Architectural Purpose: Global diagnostic tools, cryptographic security handlers, SMTP email transmitters, and text helpers.
 * Package: Zero\Support
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support;

use Zero\Support\Str;

/**
 * Class BlockHelper
 *
 * Shared layout-class resolver for page-builder blocks, translating a block's stored row and
 * spacing settings into the CSS class names its frontend template renders with.
 */
class BlockHelper
{
    /**
     * Throw if a block-builder JSON payload contains a block whose type could not safely name a
     * template file. Called on every model save, since every theme resolves a block's template as
     * '.../blocks/' . $type . '.php' and a type like '../../x' would include an arbitrary file.
     *
     * @param mixed $json Stored block-builder JSON (non-strings and non-arrays are ignored).
     * @return void
     * @throws \InvalidArgumentException When a block type is not a safe name.
     */
    public static function assertSafeBlockTypes($json): void
    {
        $blocks = \is_string($json) ? \json_decode($json, true) : null;
        if (!\is_array($blocks)) {
            return;
        }

        foreach ($blocks as $block) {
            if (\is_array($block) && \array_key_exists('type', $block) && !self::isSafeBlockType($block['type'])) {
                throw new \InvalidArgumentException('Invalid block type.');
            }
        }
    }

    /**
     * Whether a block type is a plain template name: letters, digits, '_' and '-' only, so it
     * can never contain a path separator or '..'.
     *
     * @param mixed $type Block type as stored.
     * @return bool
     */
    public static function isSafeBlockType($type): bool
    {
        return \is_string($type) && \preg_match('/^[A-Za-z0-9_-]+$/', $type) === 1;
    }

    /**
     * Get combined row classes for a block.
     *
     * @param array $block
     * @param string $type
     * @param bool $isBreakout
     * @return string
     */
    public static function getRowClasses(array $block, string $type, bool $isBreakout = false): string
    {
        $classes = [
            'block-row',
            'block-row-' . $type
        ];

        if ($isBreakout) {
            $classes[] = 'block-row-breakout';
        }

        // Add spaces classes dynamically if they are explicitly configured and not default 'none'
        $spaceBefore = self::getSpaceBeforeClass($block);
        if ($spaceBefore !== '') {
            $classes[] = $spaceBefore;
        }

        $spaceAfter = self::getSpaceAfterClass($block);
        if ($spaceAfter !== '') {
            $classes[] = $spaceAfter;
        }

        return \implode(' ', $classes);
    }

    /**
     * Get the space before margin class name.
     *
     * @param array $block
     * @return string
     */
    public static function getSpaceBeforeClass(array $block): string
    {
        $spaceBefore = $block['space_before'] ?? 'none';
        return $spaceBefore !== 'none' ? 'space-before-' . Str::escape($spaceBefore) : '';
    }

    /**
     * Get the space after margin class name.
     *
     * @param array $block
     * @return string
     */
    public static function getSpaceAfterClass(array $block): string
    {
        $spaceAfter = $block['space_after'] ?? 'none';
        return $spaceAfter !== 'none' ? 'space-after-' . Str::escape($spaceAfter) : '';
    }
}
