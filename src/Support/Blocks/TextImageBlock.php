<?php

declare(strict_types=1);

/**
 * File: src/Support/Blocks/TextImageBlock.php
 * Architectural Purpose: Handles operations and business logic within the system.
 * Package: Zero\Support\Blocks
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Blocks;

use Zero\Interfaces\BlockHelperInterface;

/**
 * Class TextImageBlock
 *
 * BlockHelperInterface adapter exposing a text-and-image block's copy for the search indexer.
 * Operates purely on the passed-in JSON block data and issues no queries.
 */
class TextImageBlock implements BlockHelperInterface
{
    protected array $data;

    /**
     * TextImageBlock constructor.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Retrieve the searchable plain text content from the Text with Image block.
     *
     * @return string
     */
    public function getSearchableContent(): string
    {
        $parts = [];
        if (!empty($this->data['title'])) {
            $parts[] = \strip_tags($this->data['title']);
        }
        if (!empty($this->data['content'])) {
            $parts[] = \strip_tags($this->data['content']);
        }
        return \implode(' ', $parts);
    }
}
