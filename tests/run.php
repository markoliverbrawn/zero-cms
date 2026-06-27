<?php
// tests/run.php
// Master Test Runner for Zero CMS exhaustive unit test suite.
// Discovers and runs all *Test.php files in isolated subprocesses, aggregating results.

define('TESTS_ROOT', __DIR__);

// Find all test files matching *Test.php
$testFiles = glob(TESTS_ROOT . '/*Test.php');

// Sort test files alphabetically for consistent run ordering
sort($testFiles);

echo "\033[1;36m==================================================\033[0m\033[1m\n";
echo "           ZERO CMS UNIT TEST RUNNER              \n";
echo "==================================================\033[0m\n\n";

echo "Found " . count($testFiles) . " test suite files matching *Test.php.\n\n";

$passedCount = 0;
$failedCount = 0;
$failedSuites = [];

foreach ($testFiles as $index => $file) {
    $suiteName = basename($file);
    echo "\033[1;34m[" . ($index + 1) . "/" . count($testFiles) . "] Running test suite: {$suiteName}...\033[0m\n";
    echo "--------------------------------------------------\n";
    
    // Build isolated PHP CLI command to execute the test suite
    // Note: We use raw path to the PHP executable in the Docker container
    $command = "php " . escapeshellarg($file);
    
    // Execute command capturing stdout/stderr and the exit code
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);
    
    // Print captured output indented for clear structural visual layout
    foreach ($output as $line) {
        echo "  " . $line . "\n";
    }
    
    echo "--------------------------------------------------\n";
    if ($exitCode === 0) {
        echo "\033[1;32m  Result: {$suiteName} PASSED successfully.\033[0m\n\n";
        $passedCount++;
    } else {
        echo "\033[1;31m  Result: {$suiteName} FAILED (exit code: {$exitCode}).\033[0m\n\n";
        $failedCount++;
        $failedSuites[] = $suiteName;
        
        // Fail-fast: Stop further tests completely after the first failure
        echo "\033[1;33m  Fail-fast: Stopping further test executions completely.\033[0m\n\n";
        break;
    }
}

echo "\033[1;36m==================================================\033[0m\033[1m\n";
echo "                TEST SUITE SUMMARY                \n";
echo "==================================================\033[0m\n";
echo "  Total Suites Executed: " . ($passedCount + $failedCount) . " / " . count($testFiles) . "\n";
echo "  Passed Suites:         \033[32m{$passedCount}\033[0m\n";
echo "  Failed Suites:         " . ($failedCount > 0 ? "\033[31m{$failedCount}\033[0m" : "0") . "\n";

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
