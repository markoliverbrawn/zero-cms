<?php

declare(strict_types=1);

/**
 * File: src/Support/SecurityScan/SourceFile.php
 * Architectural Purpose: Tokenised view of one PHP file with the helpers rules share.
 * Package: Zero\Support\SecurityScan
 * Systemic Role: Gives every rule the same code-only token stream, line lookup, scope ranges and
 *                "was this variable validated first?" analysis, so rules stay small.
 */

namespace Zero\Support\SecurityScan;

/**
 * Class SourceFile
 *
 * Holds a file's significant tokens (whitespace and comments removed, each normalised to
 * [id, text, line]) and answers the questions rules keep asking: which function encloses a token,
 * what expression surrounds it, and whether a variable passed through a validator or came from a
 * trusted registry before being used.
 */
final class SourceFile
{
    /**
     * Calls that do not validate their argument even though their names look like checks.
     */
    private const NON_VALIDATING_CALLS = [
        'is_string', 'is_array', 'is_int', 'is_integer', 'is_numeric', 'is_bool', 'is_null',
        'is_object', 'is_file', 'is_dir', 'is_readable', 'is_writable', 'is_callable', 'is_scalar',
        'is_float', 'is_resource', 'is_iterable', 'is_countable', 'is_link', 'is_executable',
        'is_uploaded_file',
    ];

    /**
     * @var array<int, array{0: int|string, 1: string, 2: int}> Significant tokens.
     */
    public readonly array $tokens;

    /**
     * @var array<int, string> Source lines, 1-based.
     */
    private array $lines;

    /**
     * @var array<int, array<int, string>> line => rule ids silenced there.
     */
    private array $ignores = [];

    /**
     * @var array<int, int> Lines carrying a security-scan-ignore comment with no reason.
     */
    private array $invalidIgnores = [];

    /**
     * @var array<int, array{0: int, 1: int}> [startIndex, endIndex] of every function/closure body.
     */
    private array $functionRanges = [];

    /**
     * @param string $path Path relative to the scan root.
     * @param string $content File contents.
     */
    public function __construct(public readonly string $path, public readonly string $content)
    {
        $this->lines = \preg_split('/\r\n|\r|\n/', $content) ?: [];
        \array_unshift($this->lines, '');

        $significant = [];
        $line = 1;
        foreach (\token_get_all($content) as $token) {
            if (\is_array($token)) {
                $line = $token[2];
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    $this->collectIgnore($token[1], $token[2]);
                    continue;
                }
                if ($token[0] === T_WHITESPACE) {
                    continue;
                }
                $significant[] = [$token[0], $token[1], $token[2]];
            } else {
                $significant[] = [$token, $token, $line];
            }
        }
        $this->tokens = $significant;
        $this->indexFunctionRanges();
    }

    /**
     * The trimmed source text of a line.
     *
     * @param int $line 1-based line number.
     * @return string
     */
    public function lineText(int $line): string
    {
        return \trim($this->lines[$line] ?? '');
    }

    /**
     * Whether a rule is silenced at a line by a security-scan-ignore comment on that line or the
     * line above.
     *
     * @param string $rule Rule id.
     * @param int $line 1-based line number.
     * @return bool
     */
    public function isIgnored(string $rule, int $line): bool
    {
        foreach ([$line, $line - 1] as $candidate) {
            if (\in_array($rule, $this->ignores[$candidate] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lines whose security-scan-ignore comment gives no reason.
     *
     * @return array<int, int>
     */
    public function invalidIgnoreLines(): array
    {
        return $this->invalidIgnores;
    }

    /**
     * Whether token $i is a T_STRING with one of the given (case-insensitive) names.
     *
     * @param int $i Token index.
     * @param string ...$names Candidate names.
     * @return bool
     */
    public function isName(int $i, string ...$names): bool
    {
        $token = $this->tokens[$i] ?? null;
        if ($token === null || !\in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            return false;
        }
        $name = \strtolower(\ltrim((string) \preg_replace('/^.*\\\\/', '', $token[1]), '\\'));

        return \in_array($name, \array_map('strtolower', $names), true);
    }

    /**
     * Index of the token closing the bracket opened at $open ('(' / '[' / '{'), or the last index.
     *
     * @param int $open Index of the opening bracket.
     * @return int
     */
    public function matchingClose(int $open): int
    {
        $pairs = ['(' => ')', '[' => ']', '{' => '}'];
        $openText = $this->tokens[$open][0];
        if ($this->tokens[$open][0] === T_CURLY_OPEN || $this->tokens[$open][0] === T_DOLLAR_OPEN_CURLY_BRACES) {
            $openText = '{';
        }
        $closeText = $pairs[$openText] ?? ')';
        $depth = 0;
        $count = \count($this->tokens);
        for ($i = $open; $i < $count; $i++) {
            $id = $this->tokens[$i][0];
            if ($id === $openText || ($openText === '{' && ($id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES))) {
                $depth++;
            } elseif ($id === $closeText) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return $count - 1;
    }

    /**
     * Index of the first token of the innermost function/closure body containing $i, or 0.
     *
     * @param int $i Token index.
     * @return int
     */
    public function scopeStart(int $i): int
    {
        $start = 0;
        $width = PHP_INT_MAX;
        foreach ($this->functionRanges as [$from, $to]) {
            if ($from <= $i && $i <= $to && ($to - $from) < $width) {
                $start = $from;
                $width = $to - $from;
            }
        }

        return $start;
    }

    /**
     * Token index range [from, to] of the expression containing $i: bounded by ';', ',', an
     * unmatched bracket, '=>', an assignment, 'return', 'echo' or a PHP open/close tag.
     *
     * @param int $i Token index inside the expression.
     * @return array{0: int, 1: int}
     */
    public function expressionAround(int $i): array
    {
        $stops = [';', ',', '=', T_DOUBLE_ARROW, T_RETURN, T_ECHO, T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG,
            T_CONCAT_EQUAL, T_COALESCE_EQUAL, '{', '}'];
        $from = $i;
        $depth = 0;
        for ($j = $i - 1; $j >= 0; $j--) {
            $id = $this->tokens[$j][0];
            if ($id === ')' || $id === ']') {
                $depth++;
            } elseif ($id === '(' || $id === '[') {
                if ($depth === 0) {
                    break;
                }
                $depth--;
            } elseif ($depth === 0 && \in_array($id, $stops, true)) {
                break;
            }
            $from = $j;
        }

        $to = $i;
        $depth = 0;
        $count = \count($this->tokens);
        for ($j = $i + 1; $j < $count; $j++) {
            $id = $this->tokens[$j][0];
            if ($id === '(' || $id === '[') {
                $depth++;
            } elseif ($id === ')' || $id === ']') {
                if ($depth === 0) {
                    break;
                }
                $depth--;
            } elseif ($depth === 0 && \in_array($id, $stops, true)) {
                break;
            }
            $to = $j;
        }

        return [$from, $to];
    }

    /**
     * Whether $variable is passed to a validating call (is.., assert.., ensure.., validate.. helpers,
     * preg_match, in_array, is_subclass_of, ctype_*, basename, array_key_exists, isset on a
     * registry lookup) between the start of the enclosing scope and token $before.
     *
     * @param string $variable Variable name including '$'.
     * @param int $before Token index the check must precede.
     * @return bool
     */
    public function isValidatedBefore(string $variable, int $before): bool
    {
        for ($i = $this->scopeStart($before); $i < $before; $i++) {
            $token = $this->tokens[$i];
            $isCall = ($this->tokens[$i + 1][0] ?? null) === '(';
            $name = \strtolower((string) \preg_replace('/^.*\\\\/', '', $token[1]));

            $validating = false;
            if ($isCall && \in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $validating = !\in_array($name, self::NON_VALIDATING_CALLS, true) && (
                    \preg_match('/^(is|assert|ensure|validate)[a-z_]/', $name) === 1
                    || \in_array($name, ['preg_match', 'in_array', 'is_subclass_of', 'basename', 'array_key_exists'], true)
                    || \strpos($name, 'ctype_') === 0
                );
            } elseif ($token[0] === T_ISSET) {
                $validating = true;
            }

            if ($validating) {
                $close = $this->matchingClose($i + 1);
                for ($k = $i + 1; $k <= $close; $k++) {
                    if ($this->tokens[$k][0] === T_VARIABLE && $this->tokens[$k][1] === $variable) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Whether $variable's most recent assignment before $before reads from code-defined data: a
     * class constant, a static property, a ::class literal, or a registry getter
     * (get*Class/getRegistered*); or it is the value of a foreach over such a source.
     *
     * @param string $variable Variable name including '$'.
     * @param int $before Token index.
     * @return bool
     */
    public function isAssignedFromTrustedSource(string $variable, int $before): bool
    {
        for ($i = $before - 1; $i >= $this->scopeStart($before); $i--) {
            if ($this->tokens[$i][0] !== T_VARIABLE || $this->tokens[$i][1] !== $variable) {
                continue;
            }

            $next = $this->tokens[$i + 1][0] ?? null;
            if ($next === '=') {
                [, $to] = $this->expressionAround($i + 2);
                return $this->rangeReadsTrustedSource($i + 2, $to);
            }

            // foreach (<source> as $key => $variable) / foreach (<source> as $variable)
            $prev = $this->tokens[$i - 1][0] ?? null;
            if ($prev === T_AS || ($prev === T_DOUBLE_ARROW && ($this->tokens[$i - 3][0] ?? null) === T_AS)) {
                for ($j = $i; $j >= 0; $j--) {
                    if ($this->tokens[$j][0] === T_FOREACH) {
                        return $this->rangeReadsTrustedSource($j + 2, $i);
                    }
                }
            }
        }

        return false;
    }

    /**
     * Whether tokens [$from, $to] read a constant, static property, ::class or registry getter.
     *
     * @param int $from First token index.
     * @param int $to Last token index.
     * @return bool
     */
    private function rangeReadsTrustedSource(int $from, int $to): bool
    {
        for ($k = $from; $k <= $to; $k++) {
            $token = $this->tokens[$k];
            if ($token[0] === T_DOUBLE_COLON) {
                $after = $this->tokens[$k + 1] ?? null;
                if ($after !== null && ($after[0] === T_VARIABLE || $after[0] === T_CLASS
                    || ($after[0] === T_STRING && \preg_match('/^[A-Z][A-Z0-9_]*$/', $after[1]) === 1
                        && ($this->tokens[$k + 2][0] ?? null) !== '('))) {
                    return true;
                }
            }
            if ($token[0] === T_STRING && \preg_match('/^(get\w*Class(es)?|getRegistered\w*)$/i', $token[1]) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record a security-scan-ignore comment.
     *
     * @param string $comment Comment text.
     * @param int $line Line the comment starts on.
     * @return void
     */
    private function collectIgnore(string $comment, int $line): void
    {
        if (\preg_match('/security-scan-ignore:\s*([a-z0-9,\s-]+?)(?:\s+--\s*(\S.*))?\s*(?:\*\/)?$/m', $comment, $m) !== 1) {
            return;
        }
        if (\trim($m[2] ?? '') === '') {
            $this->invalidIgnores[] = $line;
            return;
        }
        // A comment spanning lines silences the line after its last line, too.
        $endLine = $line + \substr_count($comment, "\n");
        $rules = \array_values(\array_filter(\array_map('trim', \explode(',', $m[1]))));
        foreach (\array_unique([$line, $endLine, $endLine + 1]) as $target) {
            $this->ignores[$target] = \array_merge($this->ignores[$target] ?? [], $rules);
        }
    }

    /**
     * Find the token range of every function and closure body.
     *
     * @return void
     */
    private function indexFunctionRanges(): void
    {
        $count = \count($this->tokens);
        for ($i = 0; $i < $count; $i++) {
            if ($this->tokens[$i][0] !== T_FUNCTION && $this->tokens[$i][0] !== T_FN) {
                continue;
            }
            for ($j = $i + 1; $j < $count; $j++) {
                $id = $this->tokens[$j][0];
                if ($id === ';') {
                    break; // abstract / interface method
                }
                if ($id === '{') {
                    $this->functionRanges[] = [$j, $this->matchingClose($j)];
                    break;
                }
                if ($this->tokens[$i][0] === T_FN && $id === T_DOUBLE_ARROW) {
                    [, $to] = $this->expressionAround($j + 1);
                    $this->functionRanges[] = [$j, $to];
                    break;
                }
            }
        }
    }
}
