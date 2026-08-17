<?php

declare(strict_types=1);

/**
 * File: src/Support/Forms/EmailInput.php
 * Architectural Purpose: Email-address form-control component -- identical to TextInput except
 * for the HTML5 "email" input type, which gives the browser native keyboard/validation support.
 * Package: Zero\Support\Forms
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support\Forms;

/**
 * Class EmailInput
 *
 * Renders an <input type="email">, reusing TextInput's template and casting behavior.
 */
class EmailInput extends TextInput
{
    protected string $htmlInputType = 'email';
}
