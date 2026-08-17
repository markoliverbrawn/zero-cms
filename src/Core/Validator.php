<?php

declare(strict_types=1);

/**
 * File: src/Core/Validator.php
 * Architectural Purpose: Core bootstrapping, system environment configuration, and utility class of the framework.
 * Package: Zero\Core
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Core;

use Zero\Support\I18n;

/**
 * Class Validator
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class Validator
{
    protected array $data = [];
    protected array $rules = [];
    protected array $errors = [];
    protected static array $customRules = [];

    /**
     * Instantiate Validator with optional data and rules.
     */
    public function __construct(array $data = [], array $rules = [])
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    /**
     * Add a validation error message.
     */
    protected function addError(string $field, string $rule, string $defaultMessage, ?string $ruleParam = null)
    {
        // Support custom error messages localized if possible (using I18n lookup)
        $langKey = "validation.{$field}.{$rule}";
        $translated = I18n::t($langKey);
        
        // If no localized translation is found (translating returns the key itself), fallback to default message
        if ($translated !== $langKey) {
            $this->errors[$field][] = $translated;
        } else {
            // Also check global key, e.g. "validation.required"
            $globalKey = "validation.{$rule}";
            $globalTranslated = I18n::t($globalKey);
            if ($globalTranslated !== $globalKey) {
                // Support variable replacements in global translations, e.g. ":field must be valid"
                $msg = \str_replace(':field', $field, $globalTranslated);
                if ($ruleParam !== null) {
                    $msg = \str_replace(':value', $ruleParam, $msg);
                }
                $this->errors[$field][] = $msg;
            } else {
                $this->errors[$field][] = $defaultMessage;
            }
        }
    }

    /**
     * Retrieve all validation error messages.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the first error message for a specific field.
     */
    public function getFirstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Get the validated data (only fields that had validation rules).
     */
    public function getValidatedData(): array
    {
        $validated = [];
        foreach (\array_keys($this->rules) as $field) {
            if (isset($this->data[$field])) {
                $validated[$field] = $this->data[$field];
            }
        }
        return $validated;
    }
/**
     * Statically register a custom validation rule callback.
     * E.g.: Validator::registerRule('zip', function($value, $param, $data) { ... })
     */
    public static function registerRule(string $name, callable $callback)
    {
        self::$customRules[$name] = $callback;
    }

    /**
     * Run validation across all fields and rules.
     * Returns true if all rules pass, false otherwise.
     */
    public function validate(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $fieldRules) {
            // Support rules declared as pipe-separated string "required|email" or array ["required", "email"]
            if (\is_string($fieldRules)) {
                $fieldRules = \explode('|', $fieldRules);
            }

            $value = $this->data[$field] ?? null;

            foreach ($fieldRules as $ruleDeclaration) {
                // Support rules with parameters, e.g. "min:3" or "max:50"
                $parts = \explode(':', $ruleDeclaration, 2);
                $ruleName = $parts[0];
                $ruleParam = $parts[1] ?? null;

                // 1. Check for 'required' rule separately because other rules can be bypassed if value is empty/null
                if ($ruleName === 'required') {
                    if ($value === null || $value === '' || (\is_array($value) && empty($value))) {
                        $this->addError($field, 'required', "The {$field} field is required.", $ruleParam);
                        break; // Stop running further rules for this field once required check fails
                    }
                    continue;
                }

                // If value is empty/null and not required, we can skip other validations (optional fields)
                if ($value === null || $value === '') {
                    continue;
                }

                // 2. Process built-in rules
                $passed = true;
                $message = '';

                switch ($ruleName) {
                    case 'email':
                        $passed = (bool)\filter_var($value, FILTER_VALIDATE_EMAIL);
                        $message = "The {$field} field must be a valid email address.";
                        break;

                    case 'phone':
                        // Extensible telephone validator (e.g., standard digits and formats)
                        // Allow digits, spaces, hyphens, parentheses, plus signs, e.g. +1 (555) 123-4567
                        $passed = (bool)\preg_match('/^[0-9+\s()\-]{7,20}$/', $value);
                        $message = "The {$field} field must be a valid telephone number.";
                        break;

                    case 'numeric':
                        $passed = \is_numeric($value);
                        $message = "The {$field} field must be numeric.";
                        break;

                    case 'integer':
                        $passed = \filter_var($value, FILTER_VALIDATE_INT) !== false;
                        $message = "The {$field} field must be an integer.";
                        break;

                    case 'min':
                        if (\is_numeric($value)) {
                            $passed = $value >= $ruleParam;
                            $message = "The {$field} field must be at least {$ruleParam}.";
                        } else {
                            $passed = \strlen($value) >= $ruleParam;
                            $message = "The {$field} field must be at least {$ruleParam} characters.";
                        }
                        break;

                    case 'max':
                        if (\is_numeric($value)) {
                            $passed = $value <= $ruleParam;
                            $message = "The {$field} field must not exceed {$ruleParam}.";
                        } else {
                            $passed = \strlen($value) <= $ruleParam;
                            $message = "The {$field} field must not exceed {$ruleParam} characters.";
                        }
                        break;

                    case 'regex':
                        $passed = (bool)\preg_match($ruleParam, $value);
                        $message = "The {$field} field format is invalid.";
                        break;

                    default:
                        // 3. Process custom registered rules
                        if (isset(self::$customRules[$ruleName])) {
                            $callback = self::$customRules[$ruleName];
                            $passed = $callback($value, $ruleParam, $this->data);
                            $message = "The {$field} field is invalid.";
                        } else {
                            // Unknown rule, raise exception
                            throw new \InvalidArgumentException("Validation rule '{$ruleName}' is not defined.");
                        }
                        break;
                }

                if (!$passed) {
                    $this->addError($field, $ruleName, $message, $ruleParam);
                }
            }
        }

        return empty($this->errors);
    }

    }
