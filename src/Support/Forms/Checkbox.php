<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/Checkbox.php
 * Architectural Purpose: Single boolean-toggle form-control component -- unlike every other field
 * type, its <label> wraps the <input> rather than preceding it, so it owns that whole structure
 * itself instead of relying on the caller's generic label chrome.
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

use Zero\Core\Template;

/**
 * Class Checkbox
 *
 * Renders a single <input type="checkbox"> wrapped in its own <label>, and casts an absent POST
 * key to false (unchecked HTML checkboxes are simply absent from $_POST entirely).
 */
class Checkbox extends AbstractFormField
{
    /**
     * @param array $source
     * @return bool
     */
    public function castSubmittedValue(array $source)
    {
        return isset($source[$this->name]);
    }

    /**
     * @return string
     */
    protected function getTemplatePath(): string
    {
        return APPLICATION_ROOT . '/src/Views/components/forms/checkbox.php';
    }

    /**
     * @return string
     */
    public function render(): string
    {
        return Template::renderFile($this->getTemplatePath(), [
            'name' => $this->name,
            'label' => $this->label,
            'checked' => !empty($this->value),
            'disabled' => $this->disabled,
            'helperText' => $this->helperText,
            'attributesHtml' => $this->renderAttributes(),
        ]);
    }
}
