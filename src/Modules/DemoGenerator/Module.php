<?php

declare(strict_types=1);

/**
 * File: src/Modules/DemoGenerator/Module.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\DemoGenerator
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

// src/Modules/DemoGenerator/Module.php

namespace Zero\Modules\DemoGenerator;

use Zero\Core\App;
use Zero\Interfaces\Module as ModuleInterface;
use Zero\Modules\DemoGenerator\Controllers\AdminCreateDemoSiteController;
use Zero\Modules\DemoGenerator\Controllers\DemoController;

/**
 * Class Module
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class Module implements ModuleInterface
{
    /**
     * Get the brand accent color associated with this module.
     */
    public function getAccentColor(): string
    {
        return '#9333ea';
    }

    /**
     * Get the view template name of the dashboard widget.
     */
    public function getDashboardWidgetView(): ?string
    {
        return null;
    }

    /**
     * Get the unique string identifier of the module.
     */
    public function getId(): string
    {
        return 'demogenerator';
    }

    /**
     * Get the database migration class associated with the module, if any.
     */
    public function getMigrationClass(): ?string
    {
        return null;
    }

    /**
     * Get the routes registered by the module.
     */
    public function getRoutes(): array
    {
        return [
            '#^/api/v1/demo/create$#' => DemoController::class,
            '#^/api/v1/admin/demo/create$#' => AdminCreateDemoSiteController::class
        ];
    }

    /**
     * Initialize the module and register its page builder blocks on bootstrap.
     */
    public function init(): void
    {
        App::registerBlock('demo_creator', [
            'label' => 'Demo Site Creator Form',
            'description' => 'An interactive, high-contrast dashboard form enabling visitors to spin up multi-tenant sandboxes.',
            'icon' => 'zap',
            'admin_view' => \dirname(__FILE__) . '/Views/blocks/admin/demo_creator.php',
            'frontend_view' => \dirname(__FILE__) . '/Views/blocks/frontend/demo_creator.php'
        ]);

        App::registerModelListAction('sites', [
            'label' => 'Create Demo Site',
            'url' => '/api/v1/admin/demo/create',
            'method' => 'post',
            'confirm' => 'Create a new kitchensink demo site? It will be seeded with sample content and expire automatically in 24 hours.',
            'module_dependency' => $this->getId(),
            'precedence' => 10
        ]);
    }
}
