<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/RadioGroup.php
 * Architectural Purpose: A set of mutually-exclusive radio options submitted as one scalar-valued
 * field. Only the first <input> in the group carries the `required` attribute -- a deliberate
 * HTML5 quirk (one required radio in a group is sufficient to enforce the constraint natively);
 * marking every input required would be redundant, marking none would silently drop validation.
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

use Zero\Core\Template;

/**
 * Class RadioGroup
 *
 * Renders a group of <input type="radio"> controls sharing one options list. Casting
 * intentionally does not filter the submitted value against the options allow-list -- a
 * deliberate, separately-approved follow-up, not bundled into the initial rendering system.
 */
class RadioGroup extends AbstractFormField
{
    /**
     * @param array $source
     * @return string|null
     */
    public function castSubmittedValue(array $source)
    {
        $raw = $source[$this->name] ?? ($this->config['default'] ?? null);
        return $raw === null ? null : (string)$raw;
    }

    /**
     * @return string
     */
    protected function getTemplatePath(): string
    {
        return APPLICATION_ROOT . '/src/Views/components/forms/radio_group.php';
    }

    /**
     * @return string
     */
    public function render(): string
    {
        return Template::renderFile($this->getTemplatePath(), [
            'name' => $this->name,
            'label' => $this->label,
            'options' => $this->config['options'] ?? [],
            'selectedVal' => $this->value,
            'required' => $this->required,
            'disabled' => $this->disabled,
            'helperText' => $this->helperText,
            'showLabel' => $this->showLabel,
        ]);
    }
}
