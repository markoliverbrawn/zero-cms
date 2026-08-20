<?php

declare(strict_types=1);

/**
 * File: src/Support/CoverageRecorder.php
 * Architectural Purpose: Global diagnostic tools, cryptographic security handlers, SMTP email transmitters, and text helpers.
 * Package: Zero\Support
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support;

/**
 * Class CoverageRecorder
 *
 * Subprocess-aware Xdebug line-coverage collector for the zero-dependency test suite.
 *
 * The test suite runs every *Test.php file in its own proc_open() subprocess, and several test
 * files spawn subprocesses of their own (ApiControllerTest.php pipes code to a fresh `php` over
 * stdin; SeederScriptTest.php shell_exec()s bin/seed). A conventional "start coverage in the test
 * process, read it at the end" approach therefore measures almost none of the work those tests
 * actually drive -- it reported ApiController.php at 3% when the real figure is 100%, and showed
 * all 30 migration files as never loaded because their up()/down() runs inside bin/seed.
 *
 * This class solves that by instrumenting the whole process tree instead of one process. prepare()
 * writes a scratch php.ini fragment that sets auto_prepend_file to src/Support/CoveragePrepend.php
 * and forces xdebug.mode=coverage; childEnvironment() exposes it via PHP_INI_SCAN_DIR (with a
 * leading ":" so the default conf.d still loads and Xdebug itself stays enabled). Because that
 * variable is inherited through the environment -- and this build runs variables_order=EGPCS, so
 * $_ENV is populated and forwarded by tests that pass $_ENV to proc_open() -- every descendant PHP
 * process auto-loads the prepend, calls record(), and dumps its own hit map on shutdown. aggregate()
 * then merges every dump into one map, so nested work is credited to the files it actually executed.
 *
 * Recording is deliberately fail-quiet inside child processes: a crash or stray output from an
 * auto-prepended file would corrupt unrelated test assertions that parse subprocess stdout. When a
 * dump cannot be written the child simply records nothing, and the orchestrating parent surfaces it
 * by reporting how many dumps it found -- the failure is reported once, centrally, rather than
 * suppressed per process.
 */
class CoverageRecorder
{
    /**
     * Environment variable used to tell every descendant process where to write its coverage dump.
     */
    public const DUMP_DIR_ENV = 'ZERO_COVERAGE_DUMP_DIR';

    /**
     * Basenames of files that are test scaffolding rather than application code under measurement.
     * Excluding the suite's own machinery keeps the headline figure about the product rather than
     * about the harness measuring it. Add a file here whenever new test infrastructure lands under
     * src/, or it will quietly dilute the percentage.
     */
    private const EXCLUDED_BASENAMES = [
        'CoveragePrepend.php',
        'CoverageRecorder.php',
        'TestBootstrap.php',
        'TestRequest.php',
        'TestRunner.php',
    ];

    /**
     * Merge every per-process coverage dump in $dumpDir into a single hit map.
     *
     * Dumps are merged with "best status wins" precedence: an executed line (1) beats a missed line
     * (-1), which in turn beats a dead/unreachable line (-2). This matters because the same file is
     * loaded by many suites, and a line missed by one suite is frequently covered by another.
     *
     * @param string $dumpDir Directory holding the *.cov dumps written by record().
     * @param string $srcRoot Absolute path to src/; anything outside it is discarded.
     * @return array{coverage: array<string, array<int, int>>, dumps: int, corrupt: int}
     */
    public static function aggregate(string $dumpDir, string $srcRoot): array
    {
        $coverage = [];
        $dumps = 0;
        $corrupt = 0;

        $files = \glob($dumpDir . '/*.cov');
        if ($files === false) {
            throw new \RuntimeException("CoverageRecorder: unable to scan dump directory '{$dumpDir}'.");
        }

        foreach ($files as $dumpFile) {
            $payload = \file_get_contents($dumpFile);

            // A serialized array always begins "a:"; checking up front avoids feeding a truncated
            // dump (a child killed mid-write) to unserialize() and tripping a notice for it.
            if ($payload === false || \strncmp($payload, 'a:', 2) !== 0) {
                $corrupt++;
                continue;
            }

            $decoded = \unserialize($payload, ['allowed_classes' => false]);
            if (!\is_array($decoded)) {
                $corrupt++;
                continue;
            }

            $dumps++;

            foreach ($decoded as $file => $lines) {
                if (!\is_string($file) || !\is_array($lines) || \strncmp($file, $srcRoot, \strlen($srcRoot)) !== 0) {
                    continue;
                }
                if (self::isExcludedFile($file)) {
                    continue;
                }

                foreach ($lines as $lineNumber => $status) {
                    $existing = $coverage[$file][$lineNumber] ?? -2;

                    if ($status === 1 || $existing === 1) {
                        $coverage[$file][$lineNumber] = 1;
                    } elseif ($status === -1 || $existing === -1) {
                        $coverage[$file][$lineNumber] = -1;
                    } else {
                        $coverage[$file][$lineNumber] = -2;
                    }
                }
            }
        }

        return ['coverage' => $coverage, 'dumps' => $dumps, 'corrupt' => $corrupt];
    }

    /**
     * Environment variables that must be injected into every spawned test subprocess so that it --
     * and anything it spawns in turn -- records coverage.
     *
     * @param string $applicationRoot Absolute path to the project root.
     * @return array<string, string>
     */
    public static function childEnvironment(string $applicationRoot): array
    {
        $iniDir = self::iniDir($applicationRoot);
        $scanDir = \getenv('PHP_INI_SCAN_DIR');

        // A leading ":" keeps PHP's compiled-in scan directory in play, so the extension ini files
        // that load Xdebug itself still apply. Dropping it would disable Xdebug and silently
        // collect nothing at all.
        $prefix = ($scanDir === false || $scanDir === '') ? ':' : \rtrim($scanDir, ':') . ':';

        return [
            'PHP_INI_SCAN_DIR' => $prefix . $iniDir,
            self::DUMP_DIR_ENV => self::dumpDir($applicationRoot),
        ];
    }

    /**
     * Absolute path to the directory holding per-process coverage dumps.
     *
     * @param string $applicationRoot Absolute path to the project root.
     * @return string
     */
    public static function dumpDir(string $applicationRoot): string
    {
        return $applicationRoot . '/storage/coverage/dumps';
    }

    /**
     * Whether this PHP build can collect coverage at all.
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return \function_exists('xdebug_start_code_coverage') && \function_exists('xdebug_get_code_coverage');
    }

    /**
     * Create the scratch directories, write the php.ini fragment that auto-prepends the recorder
     * into every descendant process, and clear any dumps left behind by an earlier run.
     *
     * @param string $applicationRoot Absolute path to the project root.
     * @return string Absolute path to the (now empty) dump directory.
     * @throws \RuntimeException If a required directory or file cannot be created.
     */
    public static function prepare(string $applicationRoot): string
    {
        $dumpDir = self::dumpDir($applicationRoot);
        $iniDir = self::iniDir($applicationRoot);

        foreach ([$dumpDir, $iniDir] as $directory) {
            if (!\is_dir($directory) && !\mkdir($directory, 0775, true) && !\is_dir($directory)) {
                throw new \RuntimeException("CoverageRecorder: could not create directory '{$directory}'.");
            }
            if (!\is_writable($directory)) {
                throw new \RuntimeException("CoverageRecorder: directory '{$directory}' is not writable.");
            }
        }

        $stale = \glob($dumpDir . '/*.cov');
        if ($stale === false) {
            throw new \RuntimeException("CoverageRecorder: unable to scan dump directory '{$dumpDir}'.");
        }
        foreach ($stale as $dumpFile) {
            if (!\unlink($dumpFile)) {
                throw new \RuntimeException("CoverageRecorder: could not remove stale dump '{$dumpFile}'.");
            }
        }

        $prependFile = __DIR__ . '/CoveragePrepend.php';
        if (!\is_file($prependFile)) {
            throw new \RuntimeException("CoverageRecorder: prepend script missing at '{$prependFile}'.");
        }

        $ini = "auto_prepend_file = {$prependFile}\n"
            . "xdebug.mode = coverage\n";

        if (\file_put_contents($iniDir . '/00-coverage.ini', $ini) === false) {
            throw new \RuntimeException("CoverageRecorder: could not write ini fragment into '{$iniDir}'.");
        }

        return $dumpDir;
    }

    /**
     * Begin recording coverage for the current process and arrange for it to be written on
     * shutdown. Invoked by src/Support/CoveragePrepend.php in every instrumented PHP process,
     * including ones spawned by an individual test file.
     *
     * Registering via register_shutdown_function() is what makes this survive the test suite's
     * assertion model: assert_test() terminates the process with exit(1) on the first failure, and
     * shutdown functions still run on exit(), so a failing suite still reports what it executed.
     *
     * @return void
     */
    public static function record(): void
    {
        if (!self::isAvailable() || \xdebug_code_coverage_started()) {
            return;
        }

        $dumpDir = \getenv(self::DUMP_DIR_ENV);
        if ($dumpDir === false || $dumpDir === '' || !\is_dir($dumpDir) || !\is_writable($dumpDir)) {
            return;
        }

        \xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);

        \register_shutdown_function(static function () use ($dumpDir): void {
            if (!\xdebug_code_coverage_started()) {
                return;
            }

            $coverage = \xdebug_get_code_coverage();
            \xdebug_stop_code_coverage();

            $target = $dumpDir . '/' . \getmypid() . '_' . \bin2hex(\random_bytes(6)) . '.cov';

            // Deliberately unchecked: this runs inside an auto-prepended shutdown handler in every
            // descendant process, where throwing or echoing would corrupt the very test output the
            // suite parses. A dump that fails to land simply lowers the parent's dump count, which
            // renderSummary() reports.
            \file_put_contents($target, \serialize($coverage));
        });
    }

    /**
     * Echo the coverage summary: overall percentage, a per-component breakdown, and the least
     * covered files. Mirrors the colored box style of the test suite's own summary.
     *
     * @param array $stats Report structure as returned by report().
     * @param string $jsonPath Absolute path of the machine-readable report written alongside it.
     * @return void
     */
    public static function renderSummary(array $stats, string $jsonPath): void
    {
        $overall = $stats['overall'];

        echo "\n\033[1;36m==================================================\033[0m\033[1m\n";
        echo "                 CODE COVERAGE                    \n";
        echo "==================================================\033[0m\n";

        if ($overall['dumps'] === 0) {
            echo "  \033[1;31mNo coverage dumps were produced.\033[0m\n";
            echo "  Xdebug did not record anything -- check that the php83 container has Xdebug\n";
            echo "  enabled and that storage/coverage is writable.\n";
            echo "\033[1;36m==================================================\033[0m\n";
            return;
        }

        $colour = self::tintFor($overall['percent']);

        echo "  Line coverage:  {$colour}" . \number_format($overall['percent'], 2) . "%\033[0m"
            . " ({$overall['covered']} / {$overall['executable']} executable lines)\n";
        echo "  Files:          {$overall['measured_files']} measured, {$overall['unmeasured_files']} never loaded"
            . " (of {$overall['total_files']})\n";
        echo "  Process dumps:  {$overall['dumps']} collected";
        if ($overall['corrupt'] > 0) {
            echo ", \033[33m{$overall['corrupt']} unreadable\033[0m";
        }
        echo "\n";

        echo "\n  \033[1mPer component\033[0m\n";
        foreach ($stats['components'] as $name => $component) {
            $tint = self::tintFor($component['percent']);
            \printf(
                "    %-24s {$tint}%5.1f%%\033[0m  (%d/%d lines, %d files",
                $name,
                $component['percent'],
                $component['covered'],
                $component['executable'],
                $component['files']
            );
            echo $component['unmeasured_files'] > 0 ? ", {$component['unmeasured_files']} never loaded)\n" : ")\n";
        }

        if (!empty($stats['lowest'])) {
            echo "\n  \033[1mLeast covered files\033[0m\n";
            foreach ($stats['lowest'] as $file => $entry) {
                $tint = self::tintFor($entry['percent']);
                \printf("    %-52s {$tint}%5.1f%%\033[0m  (%d/%d)\n", $file, $entry['percent'], $entry['covered'], $entry['executable']);
            }
        }

        echo "\n  Full per-file report: {$jsonPath}\n";
        echo "\033[1;36m==================================================\033[0m\n";
    }

    /**
     * Turn an aggregated hit map into overall, per-component and per-file statistics.
     *
     * Files that were never loaded by any process are still counted, at zero, so the figure cannot
     * be flattered by simply not touching a file. Lines Xdebug marks dead (-2) are excluded from
     * both sides of the ratio, since unreachable code is not something a test could have covered.
     *
     * @param array<string, array<int, int>> $coverage Aggregated hit map from aggregate().
     * @param string $srcRoot Absolute path to src/.
     * @param int $dumps Number of process dumps that fed the hit map.
     * @param int $corrupt Number of dumps that could not be read.
     * @param int $lowestLimit How many of the least-covered files to single out.
     * @return array
     */
    public static function report(
        array $coverage,
        string $srcRoot,
        int $dumps,
        int $corrupt,
        int $lowestLimit = 12
    ): array {
        $perFile = [];
        $components = [];
        $totalCovered = 0;
        $totalExecutable = 0;
        $unmeasured = 0;

        foreach (self::discoverSourceFiles($srcRoot) as $absolutePath) {
            $lines = $coverage[$absolutePath] ?? [];
            $covered = 0;
            $missed = 0;

            foreach ($lines as $status) {
                if ($status === 1) {
                    $covered++;
                } elseif ($status === -1) {
                    $missed++;
                }
            }

            $executable = $covered + $missed;
            $measured = $executable > 0;
            if (!$measured) {
                $unmeasured++;
            }

            $relative = \ltrim(\substr($absolutePath, \strlen($srcRoot)), '/');
            $perFile[$relative] = [
                'covered' => $covered,
                'executable' => $executable,
                'percent' => $measured ? \round($covered / $executable * 100, 1) : 0.0,
                'measured' => $measured,
            ];

            $totalCovered += $covered;
            $totalExecutable += $executable;

            $component = self::componentFor($relative);
            if (!isset($components[$component])) {
                $components[$component] = ['covered' => 0, 'executable' => 0, 'files' => 0, 'unmeasured_files' => 0];
            }
            $components[$component]['covered'] += $covered;
            $components[$component]['executable'] += $executable;
            $components[$component]['files']++;
            if (!$measured) {
                $components[$component]['unmeasured_files']++;
            }
        }

        foreach ($components as $name => $component) {
            $components[$name]['percent'] = $component['executable'] > 0
                ? \round($component['covered'] / $component['executable'] * 100, 1)
                : 0.0;
        }
        \uasort($components, static fn(array $a, array $b): int => $b['percent'] <=> $a['percent']);

        // Only files with a meaningful amount of executable code are worth singling out; a
        // three-line helper sitting at 0% is noise next to a 200-line service at 20%.
        $lowest = \array_filter($perFile, static fn(array $entry): bool => $entry['measured'] && $entry['executable'] >= 10);
        \uasort($lowest, static fn(array $a, array $b): int => $a['percent'] <=> $b['percent']);

        return [
            'overall' => [
                'percent' => $totalExecutable > 0 ? \round($totalCovered / $totalExecutable * 100, 2) : 0.0,
                'covered' => $totalCovered,
                'executable' => $totalExecutable,
                'total_files' => \count($perFile),
                'measured_files' => \count($perFile) - $unmeasured,
                'unmeasured_files' => $unmeasured,
                'dumps' => $dumps,
                'corrupt' => $corrupt,
            ],
            'components' => $components,
            'lowest' => \array_slice($lowest, 0, $lowestLimit, true),
            'files' => $perFile,
        ];
    }

    /**
     * Roll a src-relative path up to the component it belongs to, keeping each module distinct
     * (e.g. "Modules/Search") while collapsing everything else to its top-level directory.
     *
     * @param string $relativePath Path relative to src/.
     * @return string
     */
    private static function componentFor(string $relativePath): string
    {
        $segments = \explode('/', $relativePath);

        if ($segments[0] === 'Modules' && isset($segments[1])) {
            return 'Modules/' . $segments[1];
        }

        return $segments[0];
    }

    /**
     * Every measurable PHP file under src/, sorted for stable output.
     *
     * @param string $srcRoot Absolute path to src/.
     * @return string[] Absolute file paths.
     */
    private static function discoverSourceFiles(string $srcRoot): array
    {
        $files = [];

        if (!\is_dir($srcRoot)) {
            throw new \RuntimeException("CoverageRecorder: source root '{$srcRoot}' does not exist.");
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcRoot));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            if (self::isExcludedFile($file->getPathname())) {
                continue;
            }
            $files[] = $file->getPathname();
        }

        \sort($files);

        return $files;
    }

    /**
     * Whether a file is test scaffolding rather than application code under measurement. Excluding
     * the suite's own machinery keeps the headline figure about the product, not about the harness
     * measuring it.
     *
     * @param string $absolutePath
     * @return bool
     */
    private static function isExcludedFile(string $absolutePath): bool
    {
        $basename = \basename($absolutePath);

        return \str_ends_with($basename, 'Test.php')
            || \in_array($basename, self::EXCLUDED_BASENAMES, true);
    }

    /**
     * Absolute path to the directory holding the generated php.ini fragment.
     *
     * @param string $applicationRoot Absolute path to the project root.
     * @return string
     */
    private static function iniDir(string $applicationRoot): string
    {
        return $applicationRoot . '/storage/coverage/ini';
    }

    /**
     * ANSI colour for a coverage percentage, so weak areas are visible at a glance.
     *
     * @param float $percent
     * @return string
     */
    private static function tintFor(float $percent): string
    {
        if ($percent >= 70.0) {
            return "\033[32m";
        }
        if ($percent >= 45.0) {
            return "\033[33m";
        }

        return "\033[31m";
    }
}
