<?php

declare(strict_types=1);

/**
 * File: src/Interfaces/Job.php
 * Architectural Purpose: Handles operations and business logic within the system.
 * Package: Zero\Interfaces
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Interfaces;

/**
 * Interface Job
 *
 * Defines systemic behavioral interface contract mechanisms.
 */
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
