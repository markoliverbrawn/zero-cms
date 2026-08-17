<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/DateTimeInput.php
 * Architectural Purpose: Local date/time form-control component -- normalizes the browser's
 * "YYYY-MM-DDTHH:MM" datetime-local submission format into MySQL's "YYYY-MM-DD HH:MM:SS" DATETIME
 * column format, as a plain string transform (no timezone conversion, since datetime-local values
 * carry no timezone information to convert from).
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

use Zero\Core\Template;

/**
 * Class DateTimeInput
 *
 * Renders an <input type="datetime-local">.
 */
class DateTimeInput extends AbstractFormField
{
    /**
     * @param array $source
     * @return string
     */
    public function castSubmittedValue(array $source)
    {
        $raw = \trim((string)($source[$this->name] ?? ''));
        if ($raw === '') {
            return (string)($this->config['default'] ?? '');
        }
        $normalized = \str_replace('T', ' ', $raw);
        if (\strlen($normalized) === 16) { // "YYYY-MM-DD HH:MM", seconds missing
            $normalized .= ':00';
        }
        return $normalized;
    }

    /**
     * @return string
     */
    protected function getTemplatePath(): string
    {
        return APPLICATION_ROOT . '/src/Views/components/forms/datetime_input.php';
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
