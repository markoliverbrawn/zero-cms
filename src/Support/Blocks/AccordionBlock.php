<?php

declare(strict_types=1);

/**
 * File: src/Support/Blocks/AccordionBlock.php
 * Architectural Purpose: Handles operations and business logic within the system.
 * Package: Zero\Support\Blocks
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Blocks;

use Zero\Interfaces\BlockHelperInterface;

/**
 * Class AccordionBlock
 *
 * BlockHelperInterface adapter exposing an accordion block's panel titles and bodies as plain text
 * for the search indexer. Operates purely on the passed-in JSON block data and issues no queries.
 */
class AccordionBlock implements BlockHelperInterface
{
    protected array $data;

    /**
     * AccordionBlock constructor.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Retrieve the searchable plain text content from the Accordion Q&A block.
     * Loops through all nested Accordion rows in-memory with no database hits.
     *
     * @return string
     */
    public function getSearchableContent(): string
    {
        $parts = [];
        if (!empty($this->data['title'])) {
            $parts[] = \strip_tags($this->data['title']);
        }

        if (!empty($this->data['items']) && \is_array($this->data['items'])) {
            foreach ($this->data['items'] as $item) {
                if (!empty($item['title'])) {
                    $parts[] = $item['title'];
                }
                if (!empty($item['content'])) {
                    $parts[] = \strip_tags($item['content']);
                }
            }
        }

        return \implode(' ', $parts);
    }
}
