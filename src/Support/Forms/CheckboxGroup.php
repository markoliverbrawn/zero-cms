<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/CheckboxGroup.php
 * Architectural Purpose: A set of related checkboxes submitted together as one array-valued
 * field (e.g. FormBuilder's "checkbox" field type) -- distinct from Checkbox, which is a single
 * boolean toggle.
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

use Zero\Core\Template;

/**
 * Class CheckboxGroup
 *
 * Renders a group of <input type="checkbox" name="field[]"> controls sharing one options list.
 * Casting intentionally does not filter submitted values against the options allow-list -- that
 * is a deliberate, separately-approved follow-up, not bundled into the initial rendering system.
 */
class CheckboxGroup extends AbstractFormField
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
        return APPLICATION_ROOT . '/src/Views/components/forms/checkbox_group.php';
    }

    /**
     * @return string
     */
    public function render(): string
    {
        $selectedVals = \is_array($this->value) ? $this->value : [];

        return Template::renderFile($this->getTemplatePath(), [
            'name' => $this->name,
            'label' => $this->label,
            'options' => $this->config['options'] ?? [],
            'selectedVals' => $selectedVals,
            'disabled' => $this->disabled,
            'helperText' => $this->helperText,
            'showLabel' => $this->showLabel,
        ]);
    }
}
