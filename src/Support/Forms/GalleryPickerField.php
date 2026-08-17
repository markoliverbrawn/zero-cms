<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/GalleryPickerField.php
 * Architectural Purpose: Multi-image gallery picker form-control component -- ports the existing
 * "media_ids" comma-separated-UUIDs admin widget (thumbnail grid + hidden input + "Choose Gallery
 * Images" button, wired to model_edit.js) into a reusable component.
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

use Zero\Core\Template;
use Zero\Database\DB;

/**
 * Class GalleryPickerField
 *
 * Value is a comma-separated string of media UUIDs, persisted unchanged (no casting beyond a
 * trim) to match the existing storage format.
 */
class GalleryPickerField extends AbstractFormField
{
    /**
     * @param array $source
     * @return string
     */
    public function castSubmittedValue(array $source)
    {
        return \trim((string)($source[$this->name] ?? ''));
    }

    /**
     * @return string
     */
    protected function getTemplatePath(): string
    {
        return APPLICATION_ROOT . '/src/Views/components/forms/gallery_picker_field.php';
    }

    /**
     * @return string
     */
    public function render(): string
    {
        $images = [];
        $csv = (string)($this->value ?? '');
        if ($csv !== '') {
            $ids = \array_filter(\array_map('trim', \explode(',', $csv)));
            if (!empty($ids)) {
                $placeholders = \implode(',', \array_fill(0, \count($ids), '?'));
                $images = DB::query("SELECT id, path FROM media WHERE id IN ($placeholders)", $ids)->fetchAll();
            }
        }

        return Template::renderFile($this->getTemplatePath(), [
            'name' => $this->name,
            'label' => $this->label,
            'showLabel' => $this->showLabel,
            'value' => $csv,
            'images' => $images,
            'helperText' => $this->helperText,
        ]);
    }
}
