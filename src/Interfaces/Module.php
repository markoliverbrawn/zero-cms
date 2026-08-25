<?php

declare(strict_types=1);

/**
 * File: src/Interfaces/Module.php
 * Architectural Purpose: Handles operations and business logic within the system.
 * Package: Zero\Interfaces
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Interfaces;

/**
 * Interface Module
 *
 * Defines systemic behavioral interface contract mechanisms.
 */
interface Module
{
    /**
     * Get the brand accent color associated with this module (e.g., '#3b82f6').
     */
    public function getAccentColor(): string;

    /**
     * Get the view template name of the dashboard widget relative to module views, if any.
     */
    public function getDashboardWidgetView(): ?string;

    /**
     * Get the unique string identifier of the module (e.g., 'security', 'formbuilder', 'site-search', 'admin').
     */
    public function getId(): string;

    /**
     * Get the friendly display name of the module (e.g., 'Form Builder'), as shown in the
     * site-level module toggle grid and other administrative surfaces.
     */
    public function getName(): string;

    /**
     * Get the short friendly description of the module (e.g., 'Dynamic Custom Contact Forms'),
     * as shown alongside getName() in the site-level module toggle grid.
     */
    public function getDescription(): string;

    /**
     * Get the database migration class associated with the module, if any.
     */
    public function getMigrationClass(): ?string;

    /**
     * Get the routes registered by the module.
     * Returns an array mapping regex route patterns to controller class names.
     */
    public function getRoutes(): array;
}
