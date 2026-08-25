<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/ModulesGridField.php
 * Architectural Purpose: Site-level module-toggle checkbox grid -- one checkbox per registered
 * module, with the friendly name/description sourced from each Module's own getName()/
 * getDescription().
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

use Zero\Core\App;
use Zero\Core\Template;

/**
 * Class ModulesGridField
 *
 * Value is a JSON-encoded array of enabled module id strings.
 */
class ModulesGridField extends AbstractFormField
{
    /**
     * @param array $source
     * @return array
     */
    public function castSubmittedValue(array $source)
    {
        $submitted = $source[$this->name] ?? [];
        return \is_array($submitted) ? \array_values($submitted) : [$submitted];
    }

    /**
     * @return string
     */
    protected function getTemplatePath(): string
    {
        return APPLICATION_ROOT . '/src/Views/components/forms/modules_grid_field.php';
    }

    /**
     * @return string
     */
    public function render(): string
    {
        $activeModules = \json_decode((string)($this->value ?? '[]'), true);
        if (!\is_array($activeModules)) {
            $activeModules = [];
        }

        $modules = [];
        foreach (App::getModules() as $module) {
            $id = $module->getId();
            // Skip system modules from the site-level toggle checklist
            if ($id === 'admin' || $id === 'queue') {
                continue;
            }
            $modules[] = [
                'id' => $id,
                'name' => $module->getName(),
                'desc' => $module->getDescription(),
                'checked' => \in_array($id, $activeModules, true),
            ];
        }

        return Template::renderFile($this->getTemplatePath(), [
            'name' => $this->name,
            'label' => $this->label,
            'showLabel' => $this->showLabel,
            'modules' => $modules,
            'helperText' => $this->helperText,
        ]);
    }
}
