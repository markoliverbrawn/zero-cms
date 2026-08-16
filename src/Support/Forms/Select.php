<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/Select.php
 * Architectural Purpose: Single- and multiple-select form-control component -- owns options
 * allow-list validation and current-value matching, including the legacy JSON-encoded-array
 * storage format used by multi-select model fields.
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

use Zero\Core\Template;

/**
 * Class Select
 *
 * Renders <select>/<option> markup and owns allow-list-validated casting for both single and
 * multiple selection modes.
 */
class Select extends AbstractFormField
{
    /**
     * @param array $source
     * @return string|array|null
     */
    public function castSubmittedValue(array $source)
    {
        $options = $this->config['options'] ?? [];
        $default = $this->config['default'] ?? null;
        $isMultiple = $this->config['multiple'] ?? false;

        if ($isMultiple) {
            $submitted = $source[$this->name] ?? [];
            $submitted = \is_array($submitted) ? $submitted : [$submitted];
            return \array_values(\array_filter($submitted, function ($val) use ($options) {
                return $this->isValidOption($val, $options);
            }));
        }

        $submitted = $source[$this->name] ?? $default;
        return $this->isValidOption($submitted, $options) ? $submitted : $default;
    }

    /**
     * @return string
     */
    protected function getTemplatePath(): string
    {
        return APPLICATION_ROOT . '/src/Views/components/forms/select.php';
    }

    /**
     * Determine whether a candidate value is a legal member of the options list, supporting both
     * associative ('key' => 'Label') and sequential (0 => 'Label', value === label) shapes.
     *
     * @param mixed $val
     * @param array $options
     * @return bool
     */
    protected function isValidOption($val, array $options): bool
    {
        $isSequential = (\array_keys($options) === \range(0, \count($options) - 1));
        $allowed = $isSequential ? \array_values($options) : \array_keys($options);
        return \in_array($val, $allowed, false);
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
            'isMultiple' => $this->config['multiple'] ?? false,
            'selectedVals' => $this->resolveSelectedValues(),
            'required' => $this->required,
            'disabled' => $this->disabled,
            'helperText' => $this->helperText,
            'showLabel' => $this->showLabel,
        ]);
    }

    /**
     * Normalize the current value into an array of selected option values, handling the legacy
     * JSON-encoded-array-in-a-string storage format transparently.
     *
     * @return array
     */
    protected function resolveSelectedValues(): array
    {
        $currentVal = $this->value;
        if (empty($currentVal)) {
            return [];
        }
        if (\is_array($currentVal)) {
            return $currentVal;
        }
        $decoded = \json_decode((string)$currentVal, true);
        return \is_array($decoded) ? $decoded : [$currentVal];
    }
}
