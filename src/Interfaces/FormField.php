<?php

declare(strict_types=1);

/**
 * File: src/Interfaces/FormField.php
 * Architectural Purpose: Defines the contract every form-control component class must satisfy so
 * ModelController, ModuleSettingsController, and FormBuilder's submission handler can render and
 * cast values through one shared mechanism instead of three divergent hand-rolled implementations.
 * Package: Zero\Interfaces
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Interfaces;

/**
 * Interface FormField
 *
 * Defines systemic behavioral interface contract mechanisms for form-control components.
 */
interface FormField
{
    /**
     * FormField constructor.
     *
     * @param string $name Field name/POST key.
     * @param array $config Field schema config (type, label, required, options, etc.).
     */
    public function __construct(string $name, array $config);

    /**
     * Given a raw submitted-values source array (e.g. $_POST or a decoded JSON payload), return
     * the correctly-typed PHP value this field should persist. Reads its own $name key out of
     * $source itself, since only the field knows whether "absent" and "present but falsy" mean
     * different things (checkboxes) or whether multiple values should be expected (multi-select).
     *
     * @param array $source
     * @return mixed
     */
    public function castSubmittedValue(array $source);

    /**
     * Render this field's markup via Template::renderFile().
     *
     * @return string
     */
    public function render(): string;
}
