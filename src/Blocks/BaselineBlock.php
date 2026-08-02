<?php

namespace Zero\Blocks;

use Zero\Interfaces\BlockHelperInterface;

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
            $parts[] = strip_tags($this->data['title']);
        }
        if (!empty($this->data['subtitle'])) {
            $parts[] = $this->data['subtitle'];
        }
        if (!empty($this->data['content'])) {
            $parts[] = strip_tags($this->data['content']);
        }
        return implode(' ', $parts);
    }
}
