<?php

namespace Zero\Interfaces;

interface BlockHelperInterface
{
    /**
     * BlockHelperInterface constructor.
     * Takes the block's raw, saved JSON data array.
     *
     * @param array $data
     */
    public function __construct(array $data);

    /**
     * Compile and retrieve the searchable plain text content from the block data.
     * Must be 100% database-query free to prevent N+1 queries.
     *
     * @return string
     */
    public function getSearchableContent(): string;
}
