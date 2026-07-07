<?php
// tests/run.php
// Master Parallel Test Runner for Zero CMS exhaustive unit test suite.
// Discovers and runs all *Test.php files in parallel subprocesses with isolated databases.

define('TESTS_ROOT', __DIR__);

// Find all test files matching *Test.php
$testFiles = glob(TESTS_ROOT . '/*Test.php');

// Sort test files alphabetically for consistent run ordering
sort($testFiles);

echo "\033[1;36m==================================================\033[0m\033[1m\n";
echo "       ZERO CMS PARALLEL UNIT TEST RUNNER          \n";
echo "==================================================\033[0m\n\n";

echo "Found " . count($testFiles) . " test suite files matching *Test.php.\n";

// Determine CPU cores for concurrency
$maxConcurrency = 4;
if (is_readable('/proc/cpuinfo')) {
    $cpuinfo = file_get_contents('/proc/cpuinfo');
    preg_match_all('/^processor/m', $cpuinfo, $matches);
    $cores = count($matches[0]);
    if ($cores > 0) {
        $maxConcurrency = min($cores, 8); // Caps at 8 to prevent oversaturating DB connections
    }
}
echo "Running with concurrency limit: {$maxConcurrency} workers.\n\n";

$startTime = microtime(true);

$jobsQueue = $testFiles;
$totalJobs = count($testFiles);

$activeJobs = [];
$completedJobs = [];

// Track slot assignments (TEST_TOKENs: 1 to $maxConcurrency)
$freeSlots = range(1, $maxConcurrency);

while (count($completedJobs) < $totalJobs) {
    // 1. Spawn jobs up to maximum concurrency limit
    while (!empty($freeSlots) && !empty($jobsQueue)) {
        $file = array_shift($jobsQueue);
        $token = array_shift($freeSlots);
        $suiteName = basename($file);
        
        $descriptors = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"]  // stderr
        ];
        
        $pipes = [];
        // Inject the isolated database worker token environment variable
        $env = array_merge($_ENV, ['TEST_TOKEN' => (string)$token]);
        
        $process = proc_open("php " . escapeshellarg($file), $descriptors, $pipes, TESTS_ROOT, $env);
        
        if (is_resource($process)) {
            // Set output streams to non-blocking
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            
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
        $out = fread($job['pipes'][1], 8192);
        if ($out !== false && $out !== '') {
            $job['output'] .= $out;
        }
        
        $err = fread($job['pipes'][2], 8192);
        if ($err !== false && $err !== '') {
            $job['error'] .= $err;
        }
        
        // Check if process has finished
        $status = proc_get_status($job['process']);
        if (!$status['running']) {
            // Drain remaining buffer bytes
            while (($out = fread($job['pipes'][1], 8192)) !== false && $out !== '') { $job['output'] .= $out; }
            while (($err = fread($job['pipes'][2], 8192)) !== false && $err !== '') { $job['error'] .= $err; }
            
            // Clean close
            fclose($job['pipes'][0]);
            fclose($job['pipes'][1]);
            fclose($job['pipes'][2]);
            $exitCode = proc_close($job['process']);
            
            // Log and print result
            $completedJobs[] = [
                'suite' => $job['suite'],
                'output' => $job['output'],
                'error' => $job['error'],
                'exit_code' => $exitCode
            ];
            
            // Print progress immediately
            $progress = count($completedJobs) . "/" . $totalJobs;
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
    usleep(10000);
}

$duration = microtime(true) - $startTime;

// Summarize and count assertions
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
    $lines = explode("\n", $job['output']);
    foreach ($lines as $line) {
        if (strpos($line, 'PASS:') !== false || strpos($line, 'FAIL:') !== false) {
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
echo "  Duration:              " . number_format($duration, 2) . " seconds\n";

if ($failedCount > 0) {
    echo "\n  \033[1;31mList of Failed Test Suites:\033[0m\n";
    foreach ($failedSuites as $failedSuite) {
        echo "   - \033[31m{$failedSuite}\033[0m\n";
    }
    echo "\n\033[1;41m  GRAND STATUS: FAILURE  \033[0m\n";
    echo "\033[1;36m==================================================\033[0m\n";
    exit(1);
} else {
    echo "\n\033[1;42m  GRAND STATUS: SUCCESS  \033[0m\n";
    echo "\033[1;36m==================================================\033[0m\n";
    exit(0);
}
