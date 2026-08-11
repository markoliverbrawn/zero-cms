<?php

declare(strict_types=1);

/**
 * File: src/Modules/FormBuilder/Module.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\FormBuilder
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\FormBuilder;

use Zero\Core\App;
use Zero\Interfaces\Module as ModuleInterface;
use Zero\Modules\FormBuilder\Controllers\FormApiController;
use Zero\Modules\FormBuilder\Models\Submission;

/**
 * Class Module
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class Module implements ModuleInterface
{
    /**
     * Retrieves the accent color attribute value.
     *
     * @return string Response output.
     */
    public function getAccentColor(): string
    {
        return '#10b981';
    }

    /**
     * Retrieves the dashboard widget view attribute value.
     *
     * @return string Response output.
     */
    public function getDashboardWidgetView(): ?string
    {
        return null;
    }

    /**
     * Retrieves the id attribute value.
     *
     * @return string Response output.
     */
    public function getId(): string
    {
        return 'formbuilder';
    }

    /**
     * Retrieves the migration class attribute value.
     *
     * @return string Response output.
     */
    public function getMigrationClass(): ?string
    {
        return Database\Migrations\CreateFormBuilderTables::class;
    }

    /**
     * Retrieves the routes attribute value.
     *
     * @return mixed Response output.
     */
    public function getRoutes(): array
    {
        return [
            '#^/api/v1/contact/submit$#' => FormApiController::class
        ];
    }

    /**
     * Init processing implementation helper.
     *
     * @return mixed Response output.
     */
    public function init()
    {
        App::registerBlock('form_builder', [
            'label' => 'Dynamic Form Builder',
            'description' => 'A dynamic AJAX form builder block supporting custom text, select, checkboxes, and radio options.',
            'icon' => 'inbox',
            'admin_view' => \dirname(__FILE__) . '/Views/blocks/admin/form_builder.php',
            'frontend_view' => \dirname(__FILE__) . '/Views/blocks/frontend/form_builder.php',
            'bypass_preview_sanitizer' => true
        ]);

        App::registerModel('submissions', Submission::class);

        App::registerAdminSidebarLink('content', [
            'title' => 'Form Submissions',
            'url' => '/admin/list/submissions',
            'icon' => 'inbox',
            'module_dependency' => 'formbuilder',
            'precedence' => 60
        ]);
    }
}
