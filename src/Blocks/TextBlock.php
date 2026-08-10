<?php
/**
 * File: src/Blocks/TextBlock.php
 * Architectural Purpose: Handles operations and business logic within the system.
 * Package: Zero\Blocks
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Blocks;

use Zero\Interfaces\BlockHelperInterface;

/**
 * Class TextBlock
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class TextBlock implements BlockHelperInterface
{
    protected array $data;

    /**
     * TextBlock constructor.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Retrieve the searchable plain text content from the Text block.
     *
     * @return string
     */
    public function getSearchableContent(): string
    {
        $parts = [];
        if (!empty($this->data['title'])) {
            $parts[] = strip_tags($this->data['title']);
        }
        if (!empty($this->data['content'])) {
            $parts[] = strip_tags($this->data['content']);
        }
        return implode(' ', $parts);
    }
}
