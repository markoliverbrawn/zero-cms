<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/TextInput.php
 * Architectural Purpose: Plain single-line text form-control component.
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

use Zero\Core\Template;

/**
 * Class TextInput
 *
 * Renders a plain <input type="text"> and owns its own submitted-value casting.
 */
class TextInput extends AbstractFormField
{
    /** HTML `type=""` attribute this control renders as; EmailInput/PasswordInput override it. */
    protected string $htmlInputType = 'text';

    /**
     * @param array $source
     * @return string
     */
    public function castSubmittedValue(array $source)
    {
        return \trim((string)($source[$this->name] ?? ($this->config['default'] ?? '')));
    }

    /**
     * @return string
     */
    protected function getTemplatePath(): string
    {
        return APPLICATION_ROOT . '/src/Views/components/forms/text_input.php';
    }

    /**
     * @return string
     */
    public function render(): string
    {
        return Template::renderFile($this->getTemplatePath(), [
            'name' => $this->name,
            'label' => $this->label,
            'value' => $this->value,
            'required' => $this->required,
            'disabled' => $this->disabled,
            'helperText' => $this->helperText,
            'attributesHtml' => $this->renderAttributes(),
            'inputType' => $this->htmlInputType,
            'showLabel' => $this->showLabel,
        ]);
    }
}
