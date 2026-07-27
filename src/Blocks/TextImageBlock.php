<?php

namespace Zero\Blocks;

use Zero\Interfaces\BlockHelperInterface;

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
            $parts[] = $this->data['title'];
        }
        if (!empty($this->data['content'])) {
            $parts[] = strip_tags($this->data['content']);
        }
        return implode(' ', $parts);
    }
}
