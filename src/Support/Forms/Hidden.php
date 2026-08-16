<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/Hidden.php
 * Architectural Purpose: Hidden passthrough form-control component.
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

use Zero\Core\Template;

/**
 * Class Hidden
 *
 * Renders a plain <input type="hidden"> and passes its submitted value through unchanged.
 */
class Hidden extends AbstractFormField
{
    /**
     * @param array $source
     * @return string
     */
    public function castSubmittedValue(array $source)
    {
        return (string)($source[$this->name] ?? ($this->config['default'] ?? ''));
    }

    /**
     * @return string
     */
    protected function getTemplatePath(): string
    {
        return APPLICATION_ROOT . '/src/Views/components/forms/hidden.php';
    }

    /**
     * @return string
     */
    public function render(): string
    {
        return Template::renderFile($this->getTemplatePath(), [
            'name' => $this->name,
            'value' => $this->value,
        ]);
    }
}
