<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/BlockBuilderField.php
 * Architectural Purpose: Page-builder block editor field -- wraps the existing
 * src/Modules/Admin/Views/block_builder.php partial, which itself reads $record/$modelName/
 * $blockBuilderField from its rendering scope (Template::renderFile()'s extract() reproduces
 * that scope from this class's $data array, exactly as the legacy raw `include` did).
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

use Zero\Core\Template;
use Zero\Support\Str;

/**
 * Class BlockBuilderField
 *
 * Config accepts 'record' and 'modelName', passed through unchanged to the block_builder.php
 * partial. Casting is intentionally a no-op passthrough of the raw JSON payload -- persistence
 * validation/shape for block data is owned by the block-builder engine itself, not this field.
 */
class BlockBuilderField extends AbstractFormField
{
    /**
     * @param array $source
     * @return string
     */
    public function castSubmittedValue(array $source)
    {
        return (string)($source[$this->name] ?? ($this->value ?? ''));
    }

    /**
     * @return string
     */
    protected function getTemplatePath(): string
    {
        return APPLICATION_ROOT . '/src/Modules/Admin/Views/block_builder.php';
    }

    /**
     * @return string
     */
    public function render(): string
    {
        $labelHtml = $this->showLabel ? '<label>' . Str::escape($this->label) . '</label>' : '';
        return $labelHtml . Template::renderFile($this->getTemplatePath(), [
            'record' => $this->config['record'] ?? null,
            'modelName' => $this->config['modelName'] ?? null,
            'blockBuilderField' => $this->name,
            'csrf' => $this->config['csrf'] ?? '',
        ]);
    }
}
