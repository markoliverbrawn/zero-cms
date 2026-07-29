<?php
// src/Interfaces/SeederInterface.php

namespace Zero\Interfaces;

interface SeederInterface
{
    /**
     * Get the associated module identifier (e.g. 'shop', 'forum', 'blog').
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
