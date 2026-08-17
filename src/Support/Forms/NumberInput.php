<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/NumberInput.php
 * Architectural Purpose: Numeric form-control component -- renders <input type="number"> and owns
 * min/max clamping so a negative or zero value can never silently defeat downstream logic (rate
 * limits, retention windows, pagination sizes) the way an unvalidated raw string could.
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

use Zero\Core\Template;

/**
 * Class NumberInput
 *
 * Registered against both the 'number' (float) and 'int' (integer) type strings -- the config's
 * own 'type' key decides which PHP numeric type castSubmittedValue() produces.
 */
class NumberInput extends AbstractFormField
{
    /**
     * @param array $source
     * @return int|float|null
     */
    public function castSubmittedValue(array $source)
    {
        $raw = $source[$this->name] ?? null;
        $default = $this->config['default'] ?? null;

        if ($raw === null || $raw === '') {
            $value = $default;
        } else {
            $value = $this->isInteger() ? (int)$raw : (float)$raw;
        }

        if ($value === null) {
            return $value;
        }
        if (isset($this->config['min']) && $value < $this->config['min']) {
            $value = $this->config['min'];
        }
        if (isset($this->config['max']) && $value > $this->config['max']) {
            $value = $this->config['max'];
        }
        return $this->isInteger() ? (int)$value : (float)$value;
    }

    /**
     * @return string
     */
    protected function getTemplatePath(): string
    {
        return APPLICATION_ROOT . '/src/Views/components/forms/number_input.php';
    }

    /**
     * Whether this instance was constructed for the 'int' type (vs the default 'number'/float).
     *
     * @return bool
     */
    protected function isInteger(): bool
    {
        return ($this->config['type'] ?? 'number') === 'int';
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
            'min' => $this->config['min'] ?? null,
            'max' => $this->config['max'] ?? null,
            'step' => $this->isInteger() ? '1' : 'any',
            'showLabel' => $this->showLabel,
        ]);
    }
}
