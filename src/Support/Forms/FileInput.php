<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/FileInput.php
 * Architectural Purpose: File-upload form-control component. Uploaded files arrive via PHP's
 * separate $_FILES superglobal (not $_POST), with its own nested array shape per field -- actual
 * upload handling (moving the tmp file, validating mime/size) stays a controller concern; this
 * class only renders the <input type="file"> control itself.
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

use Zero\Core\Template;

/**
 * Class FileInput
 *
 * Renders a plain <input type="file">. castSubmittedValue() is a formality here -- real upload
 * data lives in $_FILES[$name], which callers must read directly, not through this method.
 */
class FileInput extends AbstractFormField
{
    /**
     * @param array $source
     * @return mixed
     */
    public function castSubmittedValue(array $source)
    {
        return $source[$this->name] ?? null;
    }

    /**
     * @return string
     */
    protected function getTemplatePath(): string
    {
        return APPLICATION_ROOT . '/src/Views/components/forms/file_input.php';
    }

    /**
     * @return string
     */
    public function render(): string
    {
        return Template::renderFile($this->getTemplatePath(), [
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'disabled' => $this->disabled,
            'helperText' => $this->helperText,
            'attributesHtml' => $this->renderAttributes(),
            'showLabel' => $this->showLabel,
        ]);
    }
}
