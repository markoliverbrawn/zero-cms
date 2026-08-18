<?php

declare(strict_types=1);

/**
 * File: src/Support/Blocks/TestimonialsBlock.php
 * Architectural Purpose: Handles operations and business logic within the system.
 * Package: Zero\Support\Blocks
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Blocks;

use Zero\Interfaces\BlockHelperInterface;

/**
 * Class TestimonialsBlock
 *
 * BlockHelperInterface adapter exposing each testimonial's quote and attribution as plain text for
 * the search indexer. Operates purely on the passed-in JSON block data and issues no queries.
 */
class TestimonialsBlock implements BlockHelperInterface
{
    protected array $data;

    /**
     * TestimonialsBlock constructor.
     *
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Retrieve the searchable plain text content from the Testimonials slider block.
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
                if (!empty($item['name'])) {
                    $parts[] = $item['name'];
                }
                if (!empty($item['role'])) {
                    $parts[] = $item['role'];
                }
                if (!empty($item['quote'])) {
                    $parts[] = \strip_tags($item['quote']);
                }
            }
        }

        return \implode(' ', $parts);
    }
}
