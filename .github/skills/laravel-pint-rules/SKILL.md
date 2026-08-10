---
name: laravel-pint-rules
description: A hybrid ruleset merging official Laravel Pint layout standards with custom global-function performance optimizations. Apply these guidelines directly when generating or editing PHP files.
---

# Laravel Pint Code Style System (Performance Hybrid)

This ruleset is a customized hybrid. It combines the aesthetic standards of the official Laravel Pint tool with strict compile-time performance optimizations. You must adhere to these instructions when formatting PHP and Blade code natively without external binaries.

## Core Rules for PHP Files

### 1. Structure and Layout
- Use exactly 4 spaces for indentation. Never use tabs.
- Ensure all PHP files use Unix-style LF (`\n`) line endings.
- All files must end with a single, blank trailing line.
- The opening `<?php` tag must be on its own line with a blank line after it.
- Force `declare(strict_types=1);` at the top of every file immediately below the opening tag.

### 2. Namespaces, Imports, and Hybrid Function Optimizations
- Keep exactly one blank line after the namespace declaration.
- Group and alphabetically sort all class `use` import statements.
- Unused `use` imports must be aggressively stripped away.
- Use a single space after commas in grouped imports.
- **Performance Optimization (Hybrid Rule)**: Always prefix global native PHP functions (e.g., `\in_array`, `\is_null`, `\count`, `\explode`) with a leading backslash `\` when called inside namespaced files. This explicitly bypasses runtime namespace lookup resolution.

### 3. Classes and Functions
- The opening brace `{` for classes, methods, and functions must be placed on the line *following* the declaration.
- The closing brace `}` must be on its own line following the body.
- Visibility modifiers (`public`, `protected`, `private`) are mandatory for all properties and methods.
- Method arguments with defaults must always be placed at the end of the argument list.
- Return type declarations must have a single space before the colon (e.g., `(): void`).

### 4. Arrays and Spacing
- Use short array syntax `[]` exclusively; never use `array()`.
- Multi-line arrays must have each element on a new line, indented by 4 spaces.
- Multi-line arrays must include a trailing comma after the final item. Single-line flat arrays must not have a trailing comma.
- Do not add spaces inside the parentheses of a function call or array bracket (e.g., `func($a, $b)`).

### 5. Control Structures and Operators
- Keywords (`if`, `elseif`, `else`, `foreach`, `for`, `while`) must have a single trailing space before parentheses.
- Opening braces `{` for control structures must remain on the *same line* as the condition.
- Use `strict` comparisons (`===` and `!==`) instead of loose comparisons (`==` and `!=`).
- Explicitly enforce single spaces around the string concatenation dot operator (e.g., `$fullName = $firstName . ' ' . $lastName;`).
- Use the logical negation operator `!` with a single trailing space following it (e.g., `if (! $user)`).

### 6. Simplification and Refactoring Guidelines
- Simplify redundant array merging or loops into clean, modern associative array operations or Laravel Collection pipelines where possible.
- Convert multiple consecutive `isset` statements checking elements in the same array block into a single clean conditional chain.

## Core Rules for Blade Templates
- Align and format HTML elements using 4-space indentation levels.
- Sort Tailwind CSS classes using standard utility order.
- Ensure any raw PHP snippets inside `@php ... @endphp` tags strictly follow the PHP rulesets defined above.
