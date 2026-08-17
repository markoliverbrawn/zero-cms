<?php

declare(strict_types=1);

/**
 * File: src/Interfaces/Model.php
 * Architectural Purpose: Handles operations and business logic within the system.
 * Package: Zero\Interfaces
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Interfaces;

/**
 * Interface Model
 *
 * Defines systemic behavioral interface contract mechanisms.
 */
interface Model
{
    /**
     * Retrieves the config attribute value.
     *
     * @return mixed Response output.
     */
    public static function getConfig(): array;
}
