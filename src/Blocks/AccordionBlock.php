<?php

namespace Zero\Blocks;

use Zero\Interfaces\BlockHelperInterface;

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
            $parts[] = $this->data['title'];
        }

        if (!empty($this->data['items']) && is_array($this->data['items'])) {
            foreach ($this->data['items'] as $item) {
                if (!empty($item['title'])) {
                    $parts[] = $item['title'];
                }
                if (!empty($item['content'])) {
                    $parts[] = strip_tags($item['content']);
                }
            }
        }

        return implode(' ', $parts);
    }
}
