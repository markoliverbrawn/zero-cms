<?php

namespace Zero\Support;

class BlockHelper
{
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

        return implode(' ', $classes);
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
        return $spaceBefore !== 'none' ? 'space-before-' . htmlspecialchars($spaceBefore, ENT_QUOTES, 'UTF-8') : '';
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
        return $spaceAfter !== 'none' ? 'space-after-' . htmlspecialchars($spaceAfter, ENT_QUOTES, 'UTF-8') : '';
    }
}
