<?php

namespace Zero\Interfaces;

interface Module
{
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
