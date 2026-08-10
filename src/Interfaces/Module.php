<?php
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
     * Get the unique string identifier of the module (e.g., 'blog', 'shop', 'howtos', 'admin').
     */
    public function getId(): string;

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
