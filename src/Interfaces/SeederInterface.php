<?php

declare(strict_types=1);

/**
 * File: src/Interfaces/SeederInterface.php
 * Architectural Purpose: Handles operations and business logic within the system.
 * Package: Zero\Interfaces
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

// src/Interfaces/SeederInterface.php

namespace Zero\Interfaces;

/**
 * Interface SeederInterface
 *
 * Defines systemic behavioral interface contract mechanisms.
 */
interface SeederInterface
{
    /**
     * Get the associated module identifier (e.g. 'security', 'formbuilder', 'site-search').
     *
     * @return string
     */
    public function getModuleId(): string;

    /**
     * Get the execution priority (lower numbers run first).
     *
     * @return int
     */
    public function getPriority(): int;

    /**
     * Run the dynamic seeder routine for a specific site ID.
     *
     * @param string $siteId The unique UUID of the site to seed
     * @param string $uploadsDir Absolute path to public uploads directory
     * @return void
     * @throws \Exception If the module is not active for this site
     */
    public function run(string $siteId, string $uploadsDir): void;
}
