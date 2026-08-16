<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/AbstractFormField.php
 * Architectural Purpose: Shared base for every concrete form-control component -- owns the
 * properties and helper behavior common to all field types (label/value/required/width/helper
 * text/attribute passthrough) so concrete classes only implement their type-specific render() and
 * castSubmittedValue() logic.
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

use Zero\Interfaces\FormField;
use Zero\Support\I18n;
use Zero\Support\Str;

/**
 * Class AbstractFormField
 *
 * Provides shared structural implementation for all concrete FormField components.
 */
abstract class AbstractFormField implements FormField
{
    protected array $attributes;
    protected array $config;
    protected bool $disabled;
    protected ?string $helperText;
    protected string $label;
    protected string $name;
    protected bool $required;
    protected bool $showLabel;
    protected $value;
    protected string $width;

    /**
     * AbstractFormField constructor.
     *
     * @param string $name Field name/POST key.
     * @param array $config Field schema config (type, label, required, options, value, etc.).
     */
    public function __construct(string $name, array $config)
    {
        $this->name = $name;
        $this->config = $config;
        $this->label = $config['label'] ?? \ucwords(\str_replace(['_', '-'], ' ', $name));
        $this->value = $config['value'] ?? null;
        $this->required = $config['required'] ?? false;
        $this->disabled = $config['disabled'] ?? false;
        $this->width = $config['width'] ?? 'full';
        $this->attributes = $config['attributes'] ?? [];
        $this->showLabel = $config['showLabel'] ?? true;
        $this->helperText = $this->resolveHelperText($name, $config);
    }

    /**
     * Default no-op asset declaration; overridden by field types needing bespoke JS/CSS not
     * already covered by a globally-loaded admin script.
     *
     * @return array{css: string[], js: string[]}
     */
    public function getRequiredAssets(): array
    {
        return ['css' => [], 'js' => []];
    }

    /**
     * Absolute path to this field type's template file, resolved by the concrete subclass.
     *
     * @return string
     */
    abstract protected function getTemplatePath(): string;

    /**
     * Render this field's markup via Template::renderFile().
     *
     * @return string
     */
    abstract public function render(): string;

    /**
     * Build an escaped HTML attribute string from the passthrough $attributes array.
     *
     * @param string[] $exclude Attribute names to skip (e.g. 'class', when a subclass merges it
     * with a fixed class of its own rather than dumping it verbatim).
     * @return string
     */
    protected function renderAttributes(array $exclude = []): string
    {
        $parts = [];
        foreach ($this->attributes as $attr => $val) {
            if (\in_array($attr, $exclude, true)) {
                continue;
            }
            $parts[] = Str::escape((string)$attr) . '="' . Str::escape((string)$val) . '"';
        }
        return \implode(' ', $parts);
    }

    /**
     * Resolve helper/description text via the same fallback chain used across every admin field
     * today: explicit helper_text -> explicit description -> {field}_help i18n key -> {field}_desc
     * i18n key. The i18n-key-guessing steps only apply when the field's own name is a fixed,
     * developer-controlled identifier (as with model/getConfig() and module settings schemas) --
     * a caller whose field names are arbitrary, end-user-chosen strings (e.g. FormBuilder, where
     * a site admin might legitimately name a contact-form field "email") must pass
     * 'guessHelperTextKey' => false, otherwise an unrelated i18n key coined for a completely
     * different context (e.g. the admin User model's own "email_help") could leak in by accident.
     *
     * @param string $field
     * @param array $fieldConfig
     * @return string
     */
    protected function resolveHelperText(string $field, array $fieldConfig): string
    {
        if (!empty($fieldConfig['helper_text'])) {
            return I18n::t($fieldConfig['helper_text']);
        }
        if (!empty($fieldConfig['description'])) {
            return I18n::t($fieldConfig['description']);
        }
        if (($fieldConfig['guessHelperTextKey'] ?? true) === false) {
            return '';
        }
        $helpKey = $field . '_help';
        $descKey = $field . '_desc';
        $translatedHelp = I18n::t($helpKey);
        if ($translatedHelp !== $helpKey) {
            return $translatedHelp;
        }
        $translatedDesc = I18n::t($descKey);
        return ($translatedDesc !== $descKey) ? $translatedDesc : '';
    }
}
