<?php

namespace Zero\Models\Traits;

trait UsesBlockBuilder
{
    /**
     * Specifies the database field name used to store the serialized block-builder JSON.
     * Defaults to 'content'. Models can override this method if their block payload field differs.
     */
    public static function getBlockBuilderField(): string
    {
        return 'content';
    }

    /**
     * Specifies a filtered list of block types that can be included in this model's builder.
     * Defaults to null, which permits all registered blocks from the core and enabled modules.
     * Models can override this to return an array of allowed block names, e.g. ['text', 'accordion'].
     */
    public static function getAllowedBlocks(): ?array
    {
        return null; // Null means all blocks are allowed
    }
}
