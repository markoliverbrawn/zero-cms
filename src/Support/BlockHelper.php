<?php

namespace Zero\Support;

class BlockHelper
{
    /**
     * Get the space before margin class name.
     *
     * @param array $block
     * @return string
     */
    public static function getSpaceBeforeClass(array $block): string
    {
        $spaceBefore = $block['space_before'] ?? 'none';
        return 'space-before-' . htmlspecialchars($spaceBefore, ENT_QUOTES, 'UTF-8');
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
        return 'space-after-' . htmlspecialchars($spaceAfter, ENT_QUOTES, 'UTF-8');
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

        // Add spaces classes dynamically
        $classes[] = self::getSpaceBeforeClass($block);
        $classes[] = self::getSpaceAfterClass($block);

        return implode(' ', $classes);
    }
}
