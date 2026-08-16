<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Controllers/ModuleSettingsController.php
 * Architectural Purpose: Generic, schema-driven admin controller for viewing and saving a single
 * module's site-configurable settings, as registered via App::registerModuleSettings(). One
 * controller serves every module's settings page -- a module only needs to register a schema
 * (see ManagesModuleSettings), never write its own settings controller/view.
 * Package: Zero\Modules\Admin\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Interfaces\Controller;
use Zero\Support\Logger;

/**
 * Class ModuleSettingsController
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class ModuleSettingsController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($param)
    {
        App::applyAuthMiddleware();
        App::applyRoleMiddleware('super_admin');

        $moduleId = $param[1] ?? '';
        $schema = App::getModuleSettingsSchema($moduleId);

        if (empty($schema)) {
            \http_response_code(404);
            echo "No settings are registered for module '{$moduleId}'.";
            exit;
        }

        $site = App::getCurrentSite();
        $success = '';
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            App::applyCsrfMiddleware();

            $values = $this->collectSubmittedValues($schema);
            $site->saveModuleSettings($moduleId, $values);

            Logger::log($_SESSION['user_id'] ?? null, 'update', 'site_settings', $site->id ?? null, [
                'module' => $moduleId,
                'settings' => $values
            ]);

            $success = 'Settings saved successfully!';
        }

        App::render('admin/module_settings', [
            'moduleId' => $moduleId,
            'moduleLabel' => \ucwords(\str_replace(['-', '_'], ' ', $moduleId)),
            'schema' => $schema,
            'values' => $site ? $site->getModuleSettings($moduleId) : [],
            'success' => $success,
            'error' => $error
        ]);
        exit;
    }

    /**
     * Collect and lightly validate/cast POST values against a settings schema, so every field
     * type is stored as a consistent, predictable PHP type regardless of what was submitted.
     *
     * @param array $schema
     * @return array
     */
    protected function collectSubmittedValues(array $schema): array
    {
        $values = [];

        foreach ($schema as $key => $fieldConfig) {
            $type = $fieldConfig['type'] ?? 'text';
            $default = $fieldConfig['default'] ?? null;

            if ($type === 'checkbox') {
                // Unchecked HTML checkboxes are simply absent from $_POST entirely.
                $values[$key] = isset($_POST[$key]);
            } elseif ($type === 'number') {
                $values[$key] = isset($_POST[$key]) && $_POST[$key] !== '' ? (float)$_POST[$key] : $default;
            } elseif ($type === 'select' && !empty($fieldConfig['options'])) {
                $posted = $_POST[$key] ?? $default;
                $values[$key] = \array_key_exists($posted, $fieldConfig['options']) ? $posted : $default;
            } else {
                $values[$key] = $_POST[$key] ?? $default;
            }
        }

        return $values;
    }
}
