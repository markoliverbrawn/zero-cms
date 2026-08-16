<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/TelInput.php
 * Architectural Purpose: Telephone-number form-control component -- identical to TextInput except
 * for the HTML5 "tel" input type, which gives mobile browsers a numeric-friendly keyboard.
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

/**
 * Class TelInput
 *
 * Renders an <input type="tel">, reusing TextInput's template and casting behavior.
 */
class TelInput extends TextInput
{
    protected string $htmlInputType = 'tel';
}
