<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/ImagePickerField.php
 * Architectural Purpose: Single media/image picker form-control component -- ports the existing
 * admin media-picker widget (text input + "Choose Image" button + live thumbnail preview, wired
 * to model_edit.js's window.openImagePicker via the .image-picker-container/.image-picker-input
 * class names) into a reusable component, including its legacy value-resolution fallback (a
 * pre-media-library record may still have a raw 36-character UUID stored directly in the display
 * path column instead of a separate "{field}_id" column).
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

use Zero\Core\Template;
use Zero\Models\Media;

/**
 * Class ImagePickerField
 *
 * Config accepts 'mediaId' (the "{field}_id" column's value, if the model has one) alongside the
 * inherited 'value' (the display/path column's value). Renders the same markup structure the
 * legacy model/edit.php hand-wrote inline, unchanged, so model_edit.js's existing wiring keeps
 * working without modification.
 */
class ImagePickerField extends AbstractFormField
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
        return APPLICATION_ROOT . '/src/Views/components/forms/image_picker_field.php';
    }

    /**
     * @return string
     */
    public function render(): string
    {
        $mediaId = $this->config['mediaId'] ?? '';
        $mediaPath = $this->value ?? '';

        // Legacy fallback: a record saved before the "{field}_id" column existed may still have
        // the raw 36-character media UUID stored directly in the display/path column.
        if (empty($mediaId) && !empty($mediaPath) && \strlen((string)$mediaPath) === 36) {
            $mediaId = $mediaPath;
            $media = Media::find($mediaId);
            $mediaPath = $media ? $media->path : '';
        }

        return Template::renderFile($this->getTemplatePath(), [
            'name' => $this->name,
            'label' => $this->label,
            'showLabel' => $this->showLabel,
            'mediaId' => $mediaId,
            'mediaPath' => $mediaPath,
            'required' => $this->required,
            'helperText' => $this->helperText,
        ]);
    }
}
