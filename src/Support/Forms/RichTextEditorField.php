<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/RichTextEditorField.php
 * Architectural Purpose: WYSIWYG rich-text editor field -- wraps the existing
 * src/Modules/Admin/Views/editor.php partial (contenteditable toolbar + hidden mirror input),
 * which reads $record/$field from its rendering scope (Template::renderFile()'s extract()
 * reproduces that scope, exactly as the legacy raw `include` did).
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

use Zero\Core\Template;
use Zero\Support\Str;

/**
 * Class RichTextEditorField
 *
 * Value is a raw HTML string as submitted; casting returns it unchanged (sanitization, if any,
 * happens wherever it already does today -- out of scope for this field).
 */
class RichTextEditorField extends AbstractFormField
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
        return APPLICATION_ROOT . '/src/Modules/Admin/Views/editor.php';
    }

    /**
     * @return string
     */
    public function render(): string
    {
        $labelHtml = $this->showLabel ? '<label>' . Str::escape($this->label) . '</label>' : '';
        return $labelHtml . Template::renderFile($this->getTemplatePath(), [
            'record' => $this->config['record'] ?? null,
            'field' => $this->name,
        ]);
    }
}
