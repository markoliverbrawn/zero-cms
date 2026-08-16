<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/Textarea.php
 * Architectural Purpose: Plain multi-line text form-control component.
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

use Zero\Core\Template;

/**
 * Class Textarea
 *
 * Renders a plain <textarea> and owns its own submitted-value casting.
 */
class Textarea extends AbstractFormField
{
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
        return APPLICATION_ROOT . '/src/Views/components/forms/textarea.php';
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
            'showLabel' => $this->showLabel,
        ]);
    }
}
