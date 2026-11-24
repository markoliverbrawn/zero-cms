<?php

declare(strict_types=1);

/**
 * File: src/Support/SecurityScan/Rules/UnescapedOutputRule.php
 * Architectural Purpose: Flags view output that is not escaped or sanitised.
 * Package: Zero\Support\SecurityScan\Rules
 * Systemic Role: Catches stored/reflected XSS in templates (echo $item['content']).
 */

namespace Zero\Support\SecurityScan\Rules;

use Zero\Support\SecurityScan\FileRule;
use Zero\Support\SecurityScan\Finding;
use Zero\Support\SecurityScan\SourceFile;

/**
 * Class UnescapedOutputRule
 *
 * In view files (paths containing /Views/, /views/ or /themes/), reports each `echo` / `<?=` /
 * `print` whose value is not provably safe. Safe means every concatenated part is a literal, a
 * numeric cast, or a call to an escaping, sanitising or code-owned rendering helper; ternary and
 * null-coalescing branches are checked separately. Anything else -- including Assets::url() and
 * other URL builders, which pass unrecognised input through unchanged -- is reported.
 */
final class UnescapedOutputRule implements FileRule
{
    public const ID = 'unescaped-output';

    /**
     * Calls whose result is safe to print as HTML.
     */
    private const SAFE_CALLS = [
        'escape', 'htmlspecialchars', 'htmlentities', 'sanitizehtml', 'sanitizetitlehtml', 'json_encode',
        'intval', 'floatval', 'count', 'number_format', 'date', 'gmdate', 'svg', 't', 'csrfinput',
        'csrftoken', 'renderfile', 'urlencode', 'rawurlencode', 'localizedatetime', 'filesize_human',
        // Code-owned HTML builders that escape their own inputs.
        'makeformfield', 'renderpagination', 'getnonce', 'render', 'renderblock',
    ];

    /**
     * Calls whose output is safe exactly when their arguments are.
     */
    private const PASSTHROUGH_CALLS = ['nl2br', 'trim', 'strtoupper', 'strtolower', 'ucfirst', 'ucwords', 'implode', 'sprintf'];

    /**
     * {@inheritdoc}
     */
    public function check(SourceFile $file): array
    {
        if (\preg_match('#/(Views|views|themes)/#', '/' . $file->path) !== 1) {
            return [];
        }

        $findings = [];
        $tokens = $file->tokens;
        $count = \count($tokens);
        foreach ($tokens as $i => $token) {
            if (!\in_array($token[0], [T_ECHO, T_OPEN_TAG_WITH_ECHO, T_PRINT], true)) {
                continue;
            }
            $end = $i + 1;
            $depth = 0;
            for (; $end < $count; $end++) {
                $id = $tokens[$end][0];
                if ($id === '(' || $id === '[') {
                    $depth++;
                } elseif ($id === ')' || $id === ']') {
                    $depth--;
                } elseif ($depth === 0 && ($id === ';' || $id === T_CLOSE_TAG || $id === ',')) {
                    break;
                }
            }
            if (!$this->isSafe($file, $i + 1, $end - 1)) {
                $findings[] = new Finding(self::ID, Finding::MEDIUM, $file->path, $token[2],
                    'Output is not escaped. Wrap text in Str::escape(), rich HTML in Security::sanitizeHtml(), numbers in (int), and check URLs with Security::isSafeUrl() before escaping. If the value is already-safe HTML built in code, say so with a security-scan-ignore comment.',
                    $file->lineText($token[2]));
            }
        }

        return $findings;
    }

    /**
     * Whether tokens [$from, $to] form a value safe to print.
     *
     * @param SourceFile $file Source.
     * @param int $from First token.
     * @param int $to Last token.
     * @return bool
     */
    private function isSafe(SourceFile $file, int $from, int $to): bool
    {
        if ($from > $to) {
            return true;
        }
        $tokens = $file->tokens;

        // Strip redundant outer parentheses.
        while ($tokens[$from][0] === '(' && $file->matchingClose($from) === $to) {
            $from++;
            $to--;
            if ($from > $to) {
                return true;
            }
        }

        // Ternary / null-coalescing: the condition is not printed; each printed branch must be safe.
        $parts = $this->splitTopLevel($file, $from, $to, ['?']);
        if (\count($parts) > 1) {
            $branches = $this->splitTopLevel($file, $parts[1][0], $to, [':']);
            if (\count($branches) === 1) {
                return $this->isSafe($file, $parts[1][0], $to); // short ternary ?:
            }
            return $this->isSafe($file, $branches[0][0], $branches[0][1])
                && $this->isSafe($file, $branches[1][0], $to);
        }
        // Arithmetic always yields a number.
        foreach (['+', '-', '*', '/', '%'] as $operator) {
            if (\count($this->splitTopLevel($file, $from, $to, [$operator])) > 1) {
                return true;
            }
        }
        foreach ([T_COALESCE, '.'] as $operator) {
            $operands = $this->splitTopLevel($file, $from, $to, [$operator]);
            if (\count($operands) > 1) {
                foreach ($operands as [$a, $b]) {
                    if (!$this->isSafe($file, $a, $b)) {
                        return false;
                    }
                }
                return true;
            }
        }

        $first = $tokens[$from];
        if (\in_array($first[0], [T_INT_CAST, T_DOUBLE_CAST, T_BOOL_CAST], true)) {
            return true;
        }
        if ($from === $to && (\in_array($first[0], [T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER, T_LINE, T_FILE, T_DIR], true)
            || ($first[0] === T_STRING && \preg_match('/^([A-Z][A-Z0-9_]*|true|false|null)$/i', $first[1]) === 1))) {
            return true;
        }
        // Plain double-quoted strings without interpolation tokenise as T_CONSTANT_ENCAPSED_STRING
        // (handled above); "...{$x}..." splits into parts and falls through to unsafe.

        // A call (or method chain) ending the operand is judged by its final call:
        // Name(...), Class::name(...), $obj->a(...)->name(...).
        if ($tokens[$to][0] === ')') {
            $depth = 0;
            for ($k = $to; $k >= $from; $k--) {
                $id = $tokens[$k][0];
                if ($id === ')') {
                    $depth++;
                } elseif ($id === '(') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
            }
            if ($k <= $from) {
                return false;
            }
            $name = \strtolower((string) \preg_replace('/^.*\\\\/', '', (string) $tokens[$k - 1][1]));
            if (\in_array($name, self::SAFE_CALLS, true)) {
                return true;
            }
            if (\in_array($name, self::PASSTHROUGH_CALLS, true)) {
                foreach ($this->splitTopLevel($file, $k + 1, $to - 1, [',']) as [$a, $b]) {
                    if (!$this->isSafe($file, $a, $b)) {
                        return false;
                    }
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Split [$from, $to] on top-level occurrences of the given operators.
     *
     * @param SourceFile $file Source.
     * @param int $from First token.
     * @param int $to Last token.
     * @param array<int, int|string> $operators Token ids to split on.
     * @return array<int, array{0: int, 1: int}>
     */
    private function splitTopLevel(SourceFile $file, int $from, int $to, array $operators): array
    {
        $parts = [];
        $start = $from;
        $depth = 0;
        for ($k = $from; $k <= $to; $k++) {
            $id = $file->tokens[$k][0];
            if ($id === '(' || $id === '[' || $id === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
            } elseif ($id === ')' || $id === ']' || $id === '}') {
                $depth--;
            } elseif ($depth === 0 && \in_array($id, $operators, true)) {
                $parts[] = [$start, $k - 1];
                $start = $k + 1;
            }
        }
        $parts[] = [$start, $to];

        return $parts;
    }
}
