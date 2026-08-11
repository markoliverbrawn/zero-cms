---
name: input-validator
description: Explains Zero CMS's declarative OOP input validator engine (src/Core/Validator.php) — pipeline-syntax rules, built-in constraints, and how to register custom validation rules at runtime. Use when adding form/API validation, writing new validation rules, or debugging why a Validator rule isn't firing as expected.
---

# Extensible Declarative OOP Input Validator (`src/Core/Validator.php`)

To provide robust, enterprise-grade input verification and error compilation across core and dynamic modules with 0% library overhead, Zero CMS implements an extensible, fully object-oriented declarative validator engine. All modular input validations (contact forms, user profiles, e-commerce reviews, etc.) must use this engine rather than ad-hoc checks (see Rule 9 in `GEMINI.md`).

## 1. Declarative Rule Configuration

Validations are defined using pipeline-separated syntax mapping input fields to specific verification rules:

```php
$rules = [
    'email' => 'required|email|max:255',
    'age' => 'required|integer|min:18'
];
```

Filter input values down strictly to validated and declared fields using `$validator->getValidatedData()` before database operations, to mitigate unauthorized field injection vectors.

## 2. Standard Constraints

* `required`: Verifies fields are not null, empty strings, or empty arrays.
* `email`: Enforces strict RFC 5322 compliance on mail addresses.
* `phone`: Validates international/local telephone formats securely.
* `numeric` & `integer`: Verifies numbers and forces index typecasting.
* `min` & `max`: Restricts character lengths on strings, sizes on arrays, and boundaries on integers.

## 3. Extendable Custom Validations

Register anonymous lambda validation hooks dynamically using `addRule()` at runtime for specialized business rules:

```php
$validator->addRule('even', function($field, $value) {
    return is_numeric($value) && (intval($value) % 2 === 0);
}, "The {field} field must be an even number.");
```

Developers can also extend the validation rule set globally at runtime using `Validator::registerRule()`.
