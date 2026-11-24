<?php

declare(strict_types=1);

/**
 * File: src/Support/SecurityScan/Scanner.php
 * Architectural Purpose: Runs the security-scan rules over a source tree.
 * Package: Zero\Support\SecurityScan
 * Systemic Role: Engine behind bin/security-scan, the CI gate for code-level security. Works on
 *                core and on a sub-project's own src/ alike.
 */

namespace Zero\Support\SecurityScan;

use Zero\Support\SecurityScan\Rules\DynamicClassRule;
use Zero\Support\SecurityScan\Rules\DynamicPhpPathRule;
use Zero\Support\SecurityScan\Rules\HostHeaderLinkRule;
use Zero\Support\SecurityScan\Rules\ObjectInjectionRule;
use Zero\Support\SecurityScan\Rules\SqlInterpolationRule;
use Zero\Support\SecurityScan\Rules\TimingCompareRule;
use Zero\Support\SecurityScan\Rules\UnauthenticatedRouteRule;
use Zero\Support\SecurityScan\Rules\UnescapedOutputRule;
use Zero\Support\SecurityScan\Rules\UnscopedLookupRule;

/**
 * Class Scanner
 *
 * Tokenises every .php file under the given paths (skipping test suites and vendor code), runs the
 * file rules and project rules, then drops findings silenced by an inline
 * "security-scan-ignore: <rule> -- <reason>" comment or listed in a baseline.
 */
final class Scanner
{
    /**
     * @var array<int, FileRule>
     */
    private array $fileRules;

    /**
     * @var array<int, ProjectRule>
     */
    private array $projectRules;

    /**
     * @param array<int, FileRule>|null $fileRules Rules to run per file (default: all).
     * @param array<int, ProjectRule>|null $projectRules Cross-file rules (default: all).
     */
    public function __construct(?array $fileRules = null, ?array $projectRules = null)
    {
        $this->fileRules = $fileRules ?? [
            new DynamicPhpPathRule(),
            new DynamicClassRule(),
            new UnescapedOutputRule(),
            new HostHeaderLinkRule(),
            new UnscopedLookupRule(),
            new SqlInterpolationRule(),
            new TimingCompareRule(),
            new ObjectInjectionRule(),
        ];
        $this->projectRules = $projectRules ?? [new UnauthenticatedRouteRule()];
    }

    /**
     * Scan paths (files or directories) relative to $root.
     *
     * @param string $root Directory findings are reported relative to.
     * @param array<int, string> $paths Files or directories, relative to $root or absolute.
     * @param array<int, string> $baseline Fingerprints to suppress.
     * @return array<int, Finding> Findings, sorted by file and line.
     */
    public function scan(string $root, array $paths, array $baseline = []): array
    {
        $root = \rtrim((string) \realpath($root), '/');
        $files = [];
        foreach ($paths as $path) {
            $absolute = \str_starts_with($path, '/') ? $path : $root . '/' . $path;
            foreach ($this->phpFilesUnder($absolute) as $file) {
                $relative = \ltrim(\substr($file, \strlen($root)), '/');
                $files[$relative] = new SourceFile($relative, (string) \file_get_contents($file));
            }
        }

        return $this->scanSources($files, $baseline);
    }

    /**
     * Scan already-loaded sources (used by tests and by scan()).
     *
     * @param array<string, SourceFile> $files Files keyed by relative path.
     * @param array<int, string> $baseline Fingerprints to suppress.
     * @return array<int, Finding>
     */
    public function scanSources(array $files, array $baseline = []): array
    {
        $raw = [];
        foreach ($files as $file) {
            foreach ($this->fileRules as $rule) {
                \array_push($raw, ...$rule->check($file));
            }
            foreach ($file->invalidIgnoreLines() as $line) {
                $raw[] = new Finding('invalid-ignore', Finding::MEDIUM, $file->path, $line,
                    'security-scan-ignore needs a reason: "// security-scan-ignore: <rule> -- <why this is safe>".',
                    $file->lineText($line));
            }
        }
        foreach ($this->projectRules as $rule) {
            \array_push($raw, ...$rule->checkProject($files));
        }

        $baselineSet = \array_flip($baseline);
        $findings = [];
        $seen = [];
        foreach ($raw as $finding) {
            $file = $files[$finding->file] ?? null;
            if ($file !== null && $finding->rule !== 'invalid-ignore' && $file->isIgnored($finding->rule, $finding->line)) {
                continue;
            }
            if (isset($baselineSet[$finding->fingerprint()])) {
                continue;
            }
            $key = $finding->rule . '|' . $finding->file . '|' . $finding->line;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $findings[] = $finding;
        }

        \usort($findings, static fn (Finding $a, Finding $b): int => [$a->file, $a->line, $a->rule] <=> [$b->file, $b->line, $b->rule]);

        return $findings;
    }

    /**
     * Every .php file under a path, excluding test suites, vendor code and VCS metadata.
     *
     * @param string $path File or directory.
     * @return array<int, string> Absolute paths.
     */
    private function phpFilesUnder(string $path): array
    {
        if (\is_file($path)) {
            return \str_ends_with($path, '.php') ? [(string) \realpath($path)] : [];
        }
        if (!\is_dir($path)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $real = (string) $file->getRealPath();
            if ($file->getExtension() !== 'php' || \preg_match('#/(Tests|tests|vendor|node_modules|\.git)/#', $real) === 1) {
                continue;
            }
            $files[] = $real;
        }
        \sort($files);

        return $files;
    }
}
