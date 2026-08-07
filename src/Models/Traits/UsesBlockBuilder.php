<?php
/**
 * Zero CMS - UsesBlockBuilder Trait
 *
 * Exposes core page-builder storage field and allowed block type getters.
 * Extends capabilities dynamically via the composed SupportsBlocks trait.
 *
 * PHP version 8.3
 *
 * @package    Zero\Models\Traits
 * @author     Zero CMS Team
 * @copyright  2026 Zero CMS
 */

namespace Zero\Models\Traits;

/**
 * Trait UsesBlockBuilder
 *
 * Standard block builder model capability trait.
 */
trait UsesBlockBuilder
{
    use SupportsBlocks;

    /**
     * Specifies the database field name used to store the serialized block-builder JSON.
     * Defaults to 'content'. Models can override this method if their block payload field differs.
     *
     * @return string The field name string.
     */
    public static function getBlockBuilderField(): string
    {
        return 'content';
    }

    /**
     * Specifies a filtered list of block types that can be included in this model's builder.
     * Defaults to null, which permits all registered blocks from the core and enabled modules.
     * Models can override this to return an array of allowed block names, e.g. ['text', 'accordion'].
     *
     * @return array|null An array of allowed block type strings, or null if all are allowed.
     */
    public static function getAllowedBlocks(): ?array
    {
        $supported = static::getSupportedBlocks();
        if (is_array($supported)) {
            return $supported;
        }
        return null; // Null means all blocks are allowed
    }
}
