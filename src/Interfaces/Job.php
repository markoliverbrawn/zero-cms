<?php

namespace Zero\Interfaces;

interface Job
{
    /**
     * Executes the task using a primitive parameters array.
     *
     * @param array $payload
     * @return void
     */
    public function execute(array $payload): void;
}
