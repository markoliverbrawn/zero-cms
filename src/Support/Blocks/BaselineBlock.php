<?php

declare(strict_types=1);

/**
 * File: src/Support/Blocks/BaselineBlock.php
 * Architectural Purpose: Handles operations and business logic within the system.
 * Package: Zero\Support\Blocks
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Blocks;

use Zero\Interfaces\BlockHelperInterface;

/**
 * Class BaselineBlock
 *
 * BlockHelperInterface adapter exposing a baseline block's text content for the search indexer.
 * Operates purely on the passed-in JSON block data and issues no queries.
 */
class BaselineBlock implements BlockHelperInterface
{
    protected array $data;

    /**
     * BaselineBlock constructor.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Retrieve the searchable plain text content from the Baseline Hero block.
     *
     * @return string
     */
    public function getSearchableContent(): string
    {
        $parts = [];
        if (!empty($this->data['title'])) {
            $parts[] = \strip_tags($this->data['title']);
        }
        if (!empty($this->data['subtitle'])) {
            $parts[] = $this->data['subtitle'];
        }
        if (!empty($this->data['content'])) {
            $parts[] = \strip_tags($this->data['content']);
        }
        return \implode(' ', $parts);
    }
}
