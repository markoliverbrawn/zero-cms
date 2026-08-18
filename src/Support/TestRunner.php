<?php

declare(strict_types=1);

/**
 * File: src/Support/TestRunner.php
 * Architectural Purpose: Global diagnostic tools, cryptographic security handlers, SMTP email transmitters, and text helpers.
 * Package: Zero\Support
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support;

/**
 * Class TestRunner
 *
 * Discovers and runs every *Test.php file under a given set of directories, each in its own
 * subprocess, running up to N concurrently. Extracted out of the former tests/run.php (now
 * bin/test) so a host project that installs Zero CMS Core via Composer can reuse the exact same
 * discovery/execution/reporting logic from its own test runner instead of hand-copying it.
 */
class TestRunner
{
    /**
     * Discover, run, and report on every *Test.php file under $root (scanned recursively, so a
     * single src/ root picks up both component-level Tests/ folders like src/Core/Tests and
     * module Tests/ folders like src/Modules/Blog/Tests). Echoes progress directly (matching the
     * original script's behavior) and returns the process exit code (0 on full success, 1 if any
     * suite failed).
     *
     * With $collectCoverage enabled, every spawned subprocess -- and every subprocess those spawn in
     * turn -- is instrumented for Xdebug line coverage, and a coverage summary is printed after the
     * test summary. Collecting coverage never changes the pass/fail exit code; it only adds a
     * report. See Zero\Support\CoverageRecorder for why instrumentation spans the whole process tree
     * rather than just this one.
     *
     * @param string $root Absolute path to scan recursively for *Test.php files.
     * @param bool $collectCoverage Whether to record and report line coverage for the run.
     * @return int
     */
    public static function run(string $root, bool $collectCoverage = false): int
    {
        $testFiles = self::discoverTestFiles($root);

        echo "\033[1;36m==================================================\033[0m\033[1m\n";
        echo "       ZERO CMS PARALLEL UNIT TEST RUNNER          \n";
        echo "==================================================\033[0m\n\n";

        echo "Found " . count($testFiles) . " test suite files matching *Test.php.\n";

        $applicationRoot = \defined('APPLICATION_ROOT') ? APPLICATION_ROOT : \dirname($root);
        $coverageEnv = [];

        if ($collectCoverage) {
            // Refusing to continue is deliberate: silently running the suite without the coverage
            // that was explicitly asked for would hand back a "0%" report indistinguishable from
            // genuinely untested code.
            if (!CoverageRecorder::isAvailable()) {
                echo "\n\033[1;31mCoverage requested but Xdebug's coverage functions are unavailable.\033[0m\n";
                echo "Enable the Xdebug extension (php -m | grep -i xdebug) and try again.\n";
                return 1;
            }

            CoverageRecorder::prepare($applicationRoot);
            $coverageEnv = CoverageRecorder::childEnvironment($applicationRoot);
            echo "Collecting line coverage (whole process tree, including nested subprocesses).\n";
        }

        $maxConcurrency = self::detectConcurrency();
        echo "Running with concurrency limit: {$maxConcurrency} workers.\n\n";

        $startTime = microtime(true);
        $completedJobs = self::executeJobs($testFiles, $maxConcurrency, $root, $coverageEnv);
        $duration = microtime(true) - $startTime;

        $exitCode = self::printSummary($completedJobs, count($testFiles), $duration);

        if ($collectCoverage) {
            self::printCoverage($applicationRoot, $root);
        }

        return $exitCode;
    }

    /**
     * Custom light-weight test assertion helper, called by every test file via the global
     * assert_test() wrapper defined in src/Support/TestBootstrap.php. Exits the current subprocess with
     * status code 1 on failure, which is what run()'s exit-code-based pass/fail detection relies
     * on -- it does not return/continue to accumulate multiple failures within one test file.
     *
     * @param bool $condition
     * @param string $message
     * @return void
     */
    public static function assertTest(bool $condition, string $message): void
    {
        if ($condition) {
            echo "  \033[32m\xE2\x9C\x85 PASS:\033[0m {$message}\n";
        } else {
            echo "  \033[31m\xE2\x9D\x8C FAIL:\033[0m {$message}\n";
            exit(1);
        }
    }

    /**
     * Recursively finds all PHP files matching *Test.php under a given directory.
     *
     * @param string $dir Target directory path.
     * @return array Array of matching absolute file paths.
     */
    private static function findTestFiles(string $dir): array
    {
        $files = [];
        if (!\is_dir($dir)) {
            return $files;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && \str_ends_with($file->getFilename(), 'Test.php')) {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    /**
     * Sort *Test.php files discovered under $root into a deterministic order.
     *
     * @param string $root
     * @return array
     */
    private static function discoverTestFiles(string $root): array
    {
        $files = self::findTestFiles($root);
        \sort($files);
        return $files;
    }

    /**
     * Determine how many subprocesses to run concurrently, based on detected CPU core count
     * (capped at 8 to avoid oversaturating DB connections), or a default of 4 if /proc/cpuinfo
     * isn't readable.
     *
     * @return int
     */
    private static function detectConcurrency(): int
    {
        $maxConcurrency = 4;
        if (\is_readable('/proc/cpuinfo')) {
            $cpuinfo = \file_get_contents('/proc/cpuinfo');
            \preg_match_all('/^processor/m', $cpuinfo, $matches);
            $cores = \count($matches[0]);
            if ($cores > 0) {
                $maxConcurrency = \min($cores, 8);
            }
        }
        return $maxConcurrency;
    }

    /**
     * Spawns and polls up to $maxConcurrency test-file subprocesses concurrently, reusing a
     * small pool of TEST_TOKEN slots (not one token per file) across the full $testFiles queue.
     * Echoes a colored [PASS]/[FAIL] progress line the instant each job completes (failures also
     * dump the job's full captured stdout+stderr immediately, to assist debugging).
     *
     * In-flight job shape (element of $activeJobs):
     *   [
     *     'process' => resource,   // proc_open() handle
     *     'pipes'   => resource[], // [0=>stdin,1=>stdout,2=>stderr]
     *     'suite'   => string,     // basename of the test file
     *     'token'   => int,        // TEST_TOKEN slot, 1..$maxConcurrency, reused after completion
     *     'output'  => string,     // accumulated stdout so far
     *     'error'   => string,     // accumulated stderr so far
     *   ]
     *
     * Returned completed-job shape (element of the returned array):
     *   [
     *     'suite'     => string,
     *     'output'    => string,
     *     'error'     => string,
     *     'exit_code' => int,
     *   ]
     *
     * @param string[] $testFiles Absolute paths, already sorted/deduped.
     * @param int $maxConcurrency Size of the token pool (from detectConcurrency()).
     * @param string $cwd proc_open()'s working directory for every spawned subprocess.
     * @param array<string, string> $extraEnv Additional environment variables handed to every
     *        subprocess (used to switch on coverage instrumentation for the whole process tree).
     * @return array List of completed-job shapes, in completion order.
     */
    private static function executeJobs(array $testFiles, int $maxConcurrency, string $cwd, array $extraEnv = []): array
    {
        $jobsQueue = $testFiles;
        $totalJobs = \count($testFiles);

        $activeJobs = [];
        $completedJobs = [];

        // Track slot assignments (TEST_TOKENs: 1 to $maxConcurrency)
        $freeSlots = \range(1, $maxConcurrency);

        while (\count($completedJobs) < $totalJobs) {
            // 1. Spawn jobs up to maximum concurrency limit
            while (!empty($freeSlots) && !empty($jobsQueue)) {
                $file = \array_shift($jobsQueue);
                $token = \array_shift($freeSlots);
                $suiteName = \basename($file);

                $descriptors = [
                    0 => ["pipe", "r"], // stdin
                    1 => ["pipe", "w"], // stdout
                    2 => ["pipe", "w"]  // stderr
                ];

                $pipes = [];
                // Inject the isolated database worker token environment variable, plus any coverage
                // instrumentation vars. These are passed explicitly rather than left to inheritance
                // because proc_open()'s $env argument replaces the child's environment outright.
                $env = \array_merge($_ENV, $extraEnv, ['TEST_TOKEN' => (string)$token]);

                $process = \proc_open("php " . \escapeshellarg($file), $descriptors, $pipes, $cwd, $env);

                if (\is_resource($process)) {
                    // Set output streams to non-blocking
                    \stream_set_blocking($pipes[1], false);
                    \stream_set_blocking($pipes[2], false);

                    $activeJobs[] = [
                        'process' => $process,
                        'pipes' => $pipes,
                        'suite' => $suiteName,
                        'token' => $token,
                        'output' => '',
                        'error' => ''
                    ];
                } else {
                    // Re-queue slot if process spawning failed
                    $freeSlots[] = $token;
                    echo "\033[1;31mError spawning test process for {$suiteName}!\033[0m\n";
                }
            }

            // 2. Poll running jobs
            foreach ($activeJobs as $index => &$job) {
                // Read non-blocking output
                $out = \fread($job['pipes'][1], 8192);
                if ($out !== false && $out !== '') {
                    $job['output'] .= $out;
                }

                $err = \fread($job['pipes'][2], 8192);
                if ($err !== false && $err !== '') {
                    $job['error'] .= $err;
                }

                // Check if process has finished
                $status = \proc_get_status($job['process']);
                if (!$status['running']) {
                    // Drain remaining buffer bytes
                    while (($out = \fread($job['pipes'][1], 8192)) !== false && $out !== '') { $job['output'] .= $out; }
                    while (($err = \fread($job['pipes'][2], 8192)) !== false && $err !== '') { $job['error'] .= $err; }

                    // Clean close
                    \fclose($job['pipes'][0]);
                    \fclose($job['pipes'][1]);
                    \fclose($job['pipes'][2]);
                    $exitCode = \proc_close($job['process']);

                    // Log and print result
                    $completedJobs[] = [
                        'suite' => $job['suite'],
                        'output' => $job['output'],
                        'error' => $job['error'],
                        'exit_code' => $exitCode
                    ];

                    // Print progress immediately
                    $progress = \count($completedJobs) . "/" . $totalJobs;
                    if ($exitCode === 0) {
                        echo "\033[1;32m  [PASS] ({$progress})\033[0m {$job['suite']}\n";
                    } else {
                        echo "\033[1;31m  [FAIL] ({$progress})\033[0m {$job['suite']} (Exit code: {$exitCode})\n";
                        // Print failing test output directly to assist debug
                        echo "\033[1;33m--- Output of Failing Suite {$job['suite']} ---\033[0m\n";
                        echo $job['output'] . "\n" . $job['error'] . "\n";
                        echo "\033[1;33m------------------------------------------------\033[0m\n\n";
                    }

                    // Free slot and token
                    $freeSlots[] = $job['token'];
                    unset($activeJobs[$index]);
                }
            }
            unset($job);

            // Non-blocking tick interval sleep (10ms) to conserve CPU usage
            \usleep(10000);
        }

        return $completedJobs;
    }

    /**
     * Aggregate every per-process coverage dump produced by the run, write the full per-file report
     * to storage/coverage/coverage.json, and echo the human-readable summary.
     *
     * @param string $applicationRoot Absolute path to the project root (where storage/ lives).
     * @param string $srcRoot Absolute path to the measured source tree.
     * @return void
     * @throws \RuntimeException If the JSON report cannot be encoded or written.
     */
    private static function printCoverage(string $applicationRoot, string $srcRoot): void
    {
        // Xdebug reports fully-resolved paths, so the prefix used to match and trim them has to be
        // resolved too -- otherwise a symlinked checkout silently matches nothing.
        $resolvedSrcRoot = \realpath($srcRoot);
        if ($resolvedSrcRoot === false) {
            throw new \RuntimeException("TestRunner: could not resolve source root '{$srcRoot}'.");
        }

        $aggregate = CoverageRecorder::aggregate(CoverageRecorder::dumpDir($applicationRoot), $resolvedSrcRoot);

        $stats = CoverageRecorder::report(
            $aggregate['coverage'],
            $resolvedSrcRoot,
            $aggregate['dumps'],
            $aggregate['corrupt']
        );

        $jsonPath = $applicationRoot . '/storage/coverage/coverage.json';
        $encoded = \json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new \RuntimeException('TestRunner: could not encode the coverage report as JSON.');
        }
        if (\file_put_contents($jsonPath, $encoded) === false) {
            throw new \RuntimeException("TestRunner: could not write the coverage report to '{$jsonPath}'.");
        }

        CoverageRecorder::renderSummary($stats, $jsonPath);
    }

    /**
     * Tally pass/fail/assertion counts across every completed job and echo the final colored
     * summary box, failed-suite list (if any), and GRAND STATUS banner.
     *
     * @param array $completedJobs Completed-job shapes, as returned by executeJobs().
     * @param int $totalJobs
     * @param float $duration Wall-clock seconds elapsed running all jobs.
     * @return int 0 if every suite passed, 1 otherwise.
     */
    private static function printSummary(array $completedJobs, int $totalJobs, float $duration): int
    {
        $passedCount = 0;
        $failedCount = 0;
        $totalAssertionsCount = 0;
        $failedSuites = [];

        foreach ($completedJobs as $job) {
            if ($job['exit_code'] === 0) {
                $passedCount++;
            } else {
                $failedCount++;
                $failedSuites[] = $job['suite'];
            }

            // Parse output lines to count individual assertion lines
            $lines = \explode("\n", $job['output']);
            foreach ($lines as $line) {
                if (\strpos($line, 'PASS:') !== false || \strpos($line, 'FAIL:') !== false) {
                    $totalAssertionsCount++;
                }
            }
        }

        echo "\n\033[1;36m==================================================\033[0m\033[1m\n";
        echo "                TEST SUITE SUMMARY                \n";
        echo "==================================================\033[0m\n";
        echo "  Total Suites Executed: " . $totalJobs . " / " . $totalJobs . "\n";
        echo "  Passed Suites:         \033[32m{$passedCount}\033[0m\n";
        echo "  Failed Suites:         " . ($failedCount > 0 ? "\033[31m{$failedCount}\033[0m" : "0") . "\n";
        echo "  Total Assertions:      \033[32m{$totalAssertionsCount}\033[0m\n";
        echo "  Duration:              " . \number_format($duration, 2) . " seconds\n";

        if ($failedCount > 0) {
            echo "\n  \033[1;31mList of Failed Test Suites:\033[0m\n";
            foreach ($failedSuites as $failedSuite) {
                echo "   - \033[31m{$failedSuite}\033[0m\n";
            }
            echo "\n\033[1;41m  GRAND STATUS: FAILURE  \033[0m\n";
            echo "\033[1;36m==================================================\033[0m\n";
            return 1;
        }

        echo "\n\033[1;42m  GRAND STATUS: SUCCESS  \033[0m\n";
        echo "\033[1;36m==================================================\033[0m\n";
        return 0;
    }
}
