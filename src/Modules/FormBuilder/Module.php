<?php

namespace Zero\Modules\FormBuilder;

use Zero\Interfaces\Module as ModuleInterface;
use Zero\Modules\FormBuilder\Controllers\FormApiController;
use Zero\Core\App;
use Zero\Modules\FormBuilder\Models\Submission;

class Module implements ModuleInterface
{

    public function getDashboardWidgetView(): ?string
    {
        return null;
    }

    
    public function getId(): string
    {
        return 'formbuilder';
    }

    

    public function getMigrationClass(): ?string
    {
        return Database\Migrations\CreateFormBuilderTables::class;
    }

    

    public function getRoutes(): array
    {
        return [
            '#^/api/v1/contact/submit$#' => FormApiController::class
        ];
    }

    

    public function init()
    {
        App::registerBlock('form_builder', [
            'label' => 'Dynamic Form Builder',
            'description' => 'A dynamic AJAX form builder block supporting custom text, select, checkboxes, and radio options.',
            'icon' => 'inbox',
            'admin_view' => dirname(__FILE__) . '/Views/blocks/admin/form_builder.php',
            'frontend_view' => dirname(__FILE__) . '/Views/blocks/frontend/form_builder.php',
            'bypass_preview_sanitizer' => true
        ]);

        App::registerModel('submissions', Submission::class);
    }
}
