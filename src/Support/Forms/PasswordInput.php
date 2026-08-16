<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/PasswordInput.php
 * Architectural Purpose: Password form-control component -- never reflects a submitted or stored
 * value back into the rendered markup, unlike every other text-shaped control.
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

use Zero\Core\Template;

/**
 * Class PasswordInput
 *
 * Renders an <input type="password"> with no value attribute.
 */
class PasswordInput extends AbstractFormField
{
    /**
     * @param array $source
     * @return string
     */
    public function castSubmittedValue(array $source)
    {
        return (string)($source[$this->name] ?? '');
    }

    /**
     * @return string
     */
    protected function getTemplatePath(): string
    {
        return APPLICATION_ROOT . '/src/Views/components/forms/password_input.php';
    }

    /**
     * @return string
     */
    public function render(): string
    {
        return Template::renderFile($this->getTemplatePath(), [
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'disabled' => $this->disabled,
            'helperText' => $this->helperText,
            'attributesHtml' => $this->renderAttributes(),
            'showLabel' => $this->showLabel,
        ]);
    }
}
