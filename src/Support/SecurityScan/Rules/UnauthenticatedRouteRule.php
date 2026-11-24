<?php

declare(strict_types=1);

/**
 * File: src/Support/SecurityScan/Rules/UnauthenticatedRouteRule.php
 * Architectural Purpose: Flags /admin and /api routes whose controller never checks access.
 * Package: Zero\Support\SecurityScan\Rules
 * Systemic Role: Catches endpoints that only check "is there a session" (or nothing at all).
 */

namespace Zero\Support\SecurityScan\Rules;

use Zero\Support\SecurityScan\Finding;
use Zero\Support\SecurityScan\ProjectRule;
use Zero\Support\SecurityScan\SourceFile;

/**
 * Class UnauthenticatedRouteRule
 *
 * Reads route tables ('#^/admin...#' => Controller::class, and Router::register(pattern, class)),
 * resolves each controller class among the scanned files, and walks it and its parents for an
 * access check: applyAuthMiddleware, requirePermission, authorize, roleHas or denyAccess. Routes
 * whose controller (and ancestors) have none are reported at the route declaration. Deliberately
 * public routes (sign-in, password reset, token-authenticated webhooks) need an ignore comment
 * saying so. Controllers outside the scanned files are skipped.
 */
final class UnauthenticatedRouteRule implements ProjectRule
{
    public const ID = 'unauthenticated-route';

    /**
     * Calls that perform an authentication or permission check.
     */
    private const ACCESS_CHECKS = ['applyauthmiddleware', 'requirepermission', 'authorize', 'rolehas', 'denyaccess'];

    /**
     * {@inheritdoc}
     */
    public function needs(SourceFile $file): bool
    {
        return $this->classesIn($file) !== [] || $this->routesIn($file) !== [];
    }

    /**
     * {@inheritdoc}
     */
    public function checkProject(array $files): array
    {
        $classes = [];
        foreach ($files as $file) {
            foreach ($this->classesIn($file) as $fqcn => $info) {
                $classes[\strtolower($fqcn)] = $info;
            }
        }

        $findings = [];
        foreach ($files as $file) {
            foreach ($this->routesIn($file) as [$pattern, $class, $line]) {
                $info = $classes[\strtolower($class)] ?? null;
                if ($info === null || $this->checksAccess($info, $classes)) {
                    continue;
                }
                $findings[] = new Finding(self::ID, Finding::HIGH, $file->path, $line,
                    "Route {$pattern} dispatches to {$class}, which (with its parent classes) never calls an access check (applyAuthMiddleware / requirePermission / authorize / roleHas). Any visitor, or any signed-in account, can reach it. Add the check, or mark a deliberately public route with a security-scan-ignore comment.",
                    $file->lineText($line));
            }
        }

        return $findings;
    }

    /**
     * Whether a class or any scanned ancestor performs an access check.
     *
     * @param array{file: SourceFile, from: int, to: int, parent: ?string} $info Class info.
     * @param array<string, array{file: SourceFile, from: int, to: int, parent: ?string}> $classes All classes.
     * @return bool
     */
    private function checksAccess(array $info, array $classes): bool
    {
        $seen = 0;
        while ($info !== null && $seen++ < 20) {
            for ($k = $info['from']; $k <= $info['to']; $k++) {
                if ($info['file']->isName($k, ...self::ACCESS_CHECKS) && ($info['file']->tokens[$k + 1][0] ?? null) === '(') {
                    return true;
                }
            }
            $info = $info['parent'] !== null ? ($classes[\strtolower($info['parent'])] ?? null) : null;
        }

        return false;
    }

    /**
     * Classes declared in a file, keyed by fully-qualified name.
     *
     * @param SourceFile $file Source.
     * @return array<string, array{file: SourceFile, from: int, to: int, parent: ?string}>
     */
    private function classesIn(SourceFile $file): array
    {
        [$namespace, $uses] = $this->nameContext($file);
        $classes = [];
        $tokens = $file->tokens;
        foreach ($tokens as $i => $token) {
            if ($token[0] !== T_CLASS || ($tokens[$i + 1][0] ?? null) !== T_STRING
                || \in_array($tokens[$i - 1][0] ?? null, [T_DOUBLE_COLON, T_NEW], true)) {
                continue;
            }
            $parent = null;
            $k = $i + 2;
            if (($tokens[$k][0] ?? null) === T_EXTENDS) {
                $parent = $this->resolve($tokens[$k + 1][1], $namespace, $uses);
            }
            while (isset($tokens[$k]) && $tokens[$k][0] !== '{') {
                $k++;
            }
            $fqcn = \ltrim($namespace . '\\' . $tokens[$i + 1][1], '\\');
            $classes[$fqcn] = ['file' => $file, 'from' => $k, 'to' => $file->matchingClose($k), 'parent' => $parent];
        }

        return $classes;
    }

    /**
     * Admin/API routes declared in a file: [pattern, controller FQCN, line].
     *
     * @param SourceFile $file Source.
     * @return array<int, array{0: string, 1: string, 2: int}>
     */
    private function routesIn(SourceFile $file): array
    {
        [$namespace, $uses] = $this->nameContext($file);
        $routes = [];
        $tokens = $file->tokens;
        foreach ($tokens as $i => $token) {
            if ($token[0] !== T_CONSTANT_ENCAPSED_STRING || \preg_match('#^[\'"][\#~]\^?/(admin|api)\b#', $token[1]) !== 1) {
                continue;
            }
            $sep = $tokens[$i + 1][0] ?? null;
            if ($sep !== T_DOUBLE_ARROW && $sep !== ',') {
                continue;
            }
            $name = $tokens[$i + 2] ?? null;
            if ($name === null || !\in_array($name[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)
                || ($tokens[$i + 3][0] ?? null) !== T_DOUBLE_COLON || ($tokens[$i + 4][0] ?? null) !== T_CLASS) {
                continue;
            }
            $routes[] = [\trim($token[1], '\'"'), $this->resolve($name[1], $namespace, $uses), $token[2]];
        }

        return $routes;
    }

    /**
     * The file's namespace and its `use` imports (alias => FQCN).
     *
     * @param SourceFile $file Source.
     * @return array{0: string, 1: array<string, string>}
     */
    private function nameContext(SourceFile $file): array
    {
        $namespace = '';
        $uses = [];
        $tokens = $file->tokens;
        $braceDepth = 0;
        foreach ($tokens as $i => $token) {
            if ($token[0] === '{') {
                $braceDepth++;
            } elseif ($token[0] === '}') {
                $braceDepth--;
            } elseif ($token[0] === T_NAMESPACE && \in_array($tokens[$i + 1][0] ?? null, [T_STRING, T_NAME_QUALIFIED], true)) {
                $namespace = $tokens[$i + 1][1];
            } elseif ($token[0] === T_USE && $braceDepth === 0
                && \in_array($tokens[$i + 1][0] ?? null, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $fqcn = \ltrim($tokens[$i + 1][1], '\\');
                $alias = ($tokens[$i + 2][0] ?? null) === T_AS ? $tokens[$i + 3][1] : (string) \preg_replace('/^.*\\\\/', '', $fqcn);
                $uses[\strtolower($alias)] = $fqcn;
            }
        }

        return [$namespace, $uses];
    }

    /**
     * Resolve a class reference against a namespace and imports.
     *
     * @param string $name Name as written.
     * @param string $namespace Current namespace.
     * @param array<string, string> $uses Imports.
     * @return string Fully-qualified name without a leading backslash.
     */
    private function resolve(string $name, string $namespace, array $uses): string
    {
        if (\str_starts_with($name, '\\')) {
            return \ltrim($name, '\\');
        }
        $first = \strtolower(\explode('\\', $name)[0]);
        if (isset($uses[$first])) {
            $rest = \substr($name, \strlen($first));
            return $uses[$first] . $rest;
        }

        return \ltrim($namespace . '\\' . $name, '\\');
    }
}
