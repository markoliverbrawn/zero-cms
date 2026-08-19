<?php

declare(strict_types=1);

/**
 * File: src/Modules/Events/Module.php
 * Architectural Purpose: Main bootstrapper class for the Events module.
 * Package: Zero\Modules\Events
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Events;

use Zero\Core\App;
use Zero\Interfaces\Module as ModuleInterface;
use Zero\Modules\Events\Models\Event;

/**
 * Class Module
 *
 * Implements the Module contract to bootstrap routes, models, sidebars, and theme-fallbacks dynamically.
 */
class Module implements ModuleInterface
{
    /**
     * Retrieve the brand accent color for rendering administrative tags and widgets.
     *
     * @return string
     */
    public function getAccentColor(): string
    {
        return '#f97316'; // Highly representative orange
    }

    /**
     * Retrieve the view path relative to Views/ for a dashboard widget.
     *
     * @return string|null
     */
    public function getDashboardWidgetView(): ?string
    {
        return null;
    }

    /**
     * Retrieve the unique module identifier key.
     *
     * @return string
     */
    public function getId(): string
    {
        return 'events';
    }

    /**
     * Retrieve the FQCN of the module's migration class (not used for auto-discovery).
     *
     * @return string|null
     */
    public function getMigrationClass(): ?string
    {
        return null;
    }

    /**
     * Retrieve the routing map for public events listing and detail pages.
     *
     * @return array
     */
    public function getRoutes(): array
    {
        return [
            '#^/events$#' => Controllers\EventsController::class,
            '#^/events/([^/]+)$#' => Controllers\EventDetailController::class,
        ];
    }

    /**
     * Bootstraps registrations, sidebar channels, and localized fallback views on system kernel initialization.
     *
     * @return void
     */
    public function init(): void
    {
        // Register the fallback view theme folder
        App::registerThemeFallback('events');

        // Register the Event Active Record model
        App::registerModel('events', Event::class);

        // Register Admin sidebar links
        App::registerAdminSidebarSection('events', [
            'title' => 'Events',
            'icon' => 'calendar',
            'module_dependency' => $this->getId(),
            'precedence' => 450
        ]);

        App::registerAdminSidebarLink('events', [
            'title' => 'Manage Events',
            'url' => '/admin/list/events',
            'icon' => 'clipboard',
            'module_dependency' => $this->getId(),
            'precedence' => 10
        ]);
        
        // Register module settings
        App::registerModuleSettings('events', [
            'events_per_page' => [
                'type' => 'number',
                'label' => 'Events Per Page',
                'default' => 5,
                'required' => true,
                'min' => 1,
                'helper_text' => 'How many upcoming events to list on the public directory.'
            ]
        ]);
    }
}
