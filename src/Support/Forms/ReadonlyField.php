<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/ReadonlyField.php
 * Architectural Purpose: Read-only display field, paired with a hidden input carrying the value
 * through to POST. Ports the existing model/edit.php "readonly" branch, which delegates display
 * to the same fields/*.php listView partials also shared with the admin list/table view.
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

use Zero\Core\Template;
use Zero\Support\Str;

/**
 * Class ReadonlyField
 *
 * Config accepts 'listView' (a partial path relative to src/Modules/Admin/Views/, e.g.
 * "fields/forum_board") and 'record' (the model instance being edited, passed through to the
 * partial unchanged).
 */
class ReadonlyField extends AbstractFormField
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
        return APPLICATION_ROOT . '/src/Views/components/forms/readonly_field.php';
    }

    /**
     * @return string
     */
    public function render(): string
    {
        $displayHtml = Str::escape((string)($this->value ?? ''));
        $listView = $this->config['listView'] ?? null;
        if (!empty($listView)) {
            $viewPath = APPLICATION_ROOT . '/src/Modules/Admin/Views/' . $listView . '.php';
            if (\file_exists($viewPath)) {
                $displayHtml = Template::renderFile($viewPath, [
                    'value' => $this->value,
                    'record' => $this->config['record'] ?? null,
                ]);
            }
        }

        return Template::renderFile($this->getTemplatePath(), [
            'name' => $this->name,
            'label' => $this->label,
            'showLabel' => $this->showLabel,
            'value' => $this->value,
            'displayHtml' => $displayHtml,
            'helperText' => $this->helperText,
        ]);
    }
}
