<?php
/**
 * Zero CMS - SupportsBlocks Trait
 *
 * Provides composable hooks to declare and filter Page Builder block-level
 * capabilities dynamically across Active Record models or controllers.
 *
 * PHP version 8.3
 *
 * @package    Zero\Models\Traits
 * @author     Zero CMS Team
 * @copyright  2026 Zero CMS
 */

namespace Zero\Models\Traits;

/**
 * Trait SupportsBlocks
 *
 * Trait for dynamic block builder capability filtering and verification.
 */
trait SupportsBlocks
{
    /**
     * Determines whether this model or controller supports the block-builder editor at all.
     *
     * @return bool Returns true by default.
     */
    public static function isBlockBuilderEnabled(): bool
    {
        return true;
    }

    /**
     * Declares the list of page-builder block types supported by this model.
     *
     * @return array|bool Returns true to permit all registered blocks, or a
     *                    whitelist array of specific block type strings.
     */
    public static function getSupportedBlocks()
    {
        return true;
    }
}
