<?php
// tests/QueueTest.php
// Unit and integration tests for the Job Queue and Runner System

require_once dirname(dirname(dirname(__DIR__))) . '/Support/TestBootstrap.php';

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Modules\Queue\Models\QueueJob;
use Zero\Modules\Queue\Support\QueueManager;
use Zero\Interfaces\Job as JobInterface;

// Define self-contained mock jobs for testing
class MockSuccessJob implements JobInterface
{
    public static bool $wasExecuted = false;
    public static ?array $receivedPayload = null;

    public function execute(array $payload): void
    {
        self::$wasExecuted = true;
        self::$receivedPayload = $payload;
    }
}

class MockFailureJob implements JobInterface
{
    public function execute(array $payload): void
    {
        throw new \Exception("Simulated job failure exception");
    }
}

echo "=== Job Queue & Runner System Tests ===\n";

// 1. Verify Queue Model Loading
echo "Testing QueueJob model loading...\n";
App::bootstrap();
$modelClassExists = class_exists('Zero\Modules\Queue\Models\QueueJob');
assert_test($modelClassExists === true, "QueueJob model class is successfully loaded and registered in the core namespaces");

// 2. Verify Database Table Schema
echo "Testing database table existence...\n";
$hasTable = DB::query("SHOW TABLES LIKE 'queue_jobs'")->fetch();
assert_test(!empty($hasTable), "Table 'queue_jobs' exists in the database schema");

// 3. Test Dispatching
echo "Testing job dispatching...\n";
$siteId = App::getCurrentSiteId();
$testPayload = ['user_id' => '123', 'action' => 'test_success'];
$jobId = QueueManager::dispatch(MockSuccessJob::class, $testPayload, $siteId);

assert_test(!empty($jobId) && strlen($jobId) === 36, "Successfully dispatched job and generated a valid UUIDv7 ID");

DB::clearIdentityMap();
$jobRow = QueueJob::find($jobId);
assert_test($jobRow !== null, "Dispatched job exists in the database");
assert_test($jobRow->status === 'pending', "Dispatched job status defaults to 'pending'");
assert_test($jobRow->attempts == 0, "Dispatched job starts with 0 attempts");
assert_test(json_decode($jobRow->payload, true) == $testPayload, "Job payload was serialized to JSON and preserved correctly");

// 4. Test Successful Execution
echo "Testing successful job execution...\n";
MockSuccessJob::$wasExecuted = false;
MockSuccessJob::$receivedPayload = null;

$processed = QueueManager::runNextPendingJob();
assert_test($processed === true, "QueueManager successfully picked up and processed the pending job");
assert_test(MockSuccessJob::$wasExecuted === true, "Job class's execute() method was physically invoked");
assert_test(MockSuccessJob::$receivedPayload == $testPayload, "Correct payload arguments were passed to execute()");

// Verify state in database
DB::clearIdentityMap();
$updatedJob = QueueJob::find($jobId);
assert_test($updatedJob->status === 'completed', "Successfully executed job status transitioned to 'completed' in database");
assert_test($updatedJob->attempts == 1, "Attempts incremented to 1");

// 5. Test Failure Handling
echo "Testing job failure handling...\n";
$failPayload = ['action' => 'test_failure'];
$failJobId = QueueManager::dispatch(MockFailureJob::class, $failPayload, $siteId);

$processedFail = QueueManager::runNextPendingJob();
assert_test($processedFail === true, "QueueManager successfully picked up and processed the failing job");

DB::clearIdentityMap();
$failedJob = QueueJob::find($failJobId);
assert_test($failedJob->status === 'failed', "Failing job status transitioned to 'failed' in database");
assert_test($failedJob->attempts == 1, "Attempts incremented to 1 on failure");
assert_test($failedJob->failed_at !== null, "failed_at timestamp was successfully populated");
assert_test(strpos($failedJob->error_message, "Simulated job failure exception") !== false, "Diagnostic error traceback was successfully captured and logged");

// 6. Test Manual Administrative Reset (Retry)
echo "Testing administrative reset/retry...\n";
$failedJob->status = 'pending';
$failedJob->save(); // Triggers customized Model::save overrides!

DB::clearIdentityMap();
$resetJob = QueueJob::find($failJobId);
assert_test($resetJob->status === 'pending', "Successfully reset status back to 'pending'");
assert_test($resetJob->attempts == 0, "Attempts were automatically reset to 0 upon pending status trigger");
assert_test($resetJob->failed_at === null, "failed_at was cleared out");
assert_test($resetJob->error_message === null, "error_message trace was cleared out");

// 7. Test Scheduler System
echo "Testing Job Scheduler system...\n";
use Zero\Modules\Queue\Support\Scheduler;

// Clean out standard jobs queue to guarantee scheduler test isolation
DB::query("TRUNCATE TABLE queue_jobs");

// Create and save a valid tenant site record with 'queue' enabled
$testSite = new \Zero\Models\Site([
    'name' => 'Test Scheduler Site',
    'domain' => 'test-scheduler.zero',
    'theme' => 'default',
    'enabled_modules' => json_encode(['queue'])
]);
$testSite->save();

// Set active site scope context
App::setCurrentSite($testSite);

// Ensure queue_scheduled_tasks exists and is cleanly truncated for testing
$hasScheduleTable = DB::query("SHOW TABLES LIKE 'queue_scheduled_tasks'")->fetch();
assert_test(!empty($hasScheduleTable), "Table 'queue_scheduled_tasks' exists in the database schema");

// Register mock scheduled job
MockSuccessJob::$wasExecuted = false;
MockSuccessJob::$receivedPayload = null;

$schedulePayload = ['trigger' => 'scheduler_daemon'];
Scheduler::register(MockSuccessJob::class, $schedulePayload, 'every_minute');

// Run scheduler evaluation loop (should dispatch the job since it has never run before!)
Scheduler::run();

// Clear identity map and query standard pending jobs to verify it was dispatched
DB::clearIdentityMap();
$pendingJobs = QueueJob::where('job_class', MockSuccessJob::class);
assert_test(!empty($pendingJobs), "Scheduler successfully identified due task and dispatched it to the queue");

// Process all queued jobs to verify execution of MockSuccessJob
MockSuccessJob::$wasExecuted = false;
$anyProcessed = false;
while (QueueManager::runNextPendingJob()) {
    $anyProcessed = true;
}
assert_test($anyProcessed === true, "QueueManager successfully picked up and processed the scheduled jobs");
assert_test(MockSuccessJob::$wasExecuted === true, "Scheduled job was physically executed");
assert_test(MockSuccessJob::$receivedPayload['trigger'] === 'scheduler_daemon', "Scheduled job was executed with correct payload");

// 8. Test PurgeOldLogsJob Scheduled Task
echo "Testing PurgeOldLogsJob Scheduled Task...\n";
use Zero\Modules\Queue\Jobs\PurgeOldLogsJob;

$siteId = $testSite->id;

// Insert mock audit log entries for testing
$oldLogId = \Zero\Support\Security::uuidv7();
$newLogId = \Zero\Support\Security::uuidv7();

// Log created 1.5 years ago (should be purged)
$oldDate = gmdate('Y-m-d H:i:s', strtotime('-500 days'));
// Log created 2 days ago (should be preserved)
$newDate = gmdate('Y-m-d H:i:s', strtotime('-2 days'));

$pdo = DB::getPDO();
$pdo->prepare("
    INSERT INTO audit_logs (id, site_id, action, created_at) 
    VALUES (?, ?, ?, ?)
")->execute([$oldLogId, $siteId, 'old_test_action', $oldDate]);

$pdo->prepare("
    INSERT INTO audit_logs (id, site_id, action, created_at) 
    VALUES (?, ?, ?, ?)
")->execute([$newLogId, $siteId, 'new_test_action', $newDate]);

// Verify both logs are in database before running job
$oldLogExists = DB::query("SELECT id FROM audit_logs WHERE id = ?", [$oldLogId])->fetch();
$newLogExists = DB::query("SELECT id FROM audit_logs WHERE id = ?", [$newLogId])->fetch();
assert_test(!empty($oldLogExists), "Old log entry exists before purge");
assert_test(!empty($newLogExists), "New log entry exists before purge");

// Dispatch and execute the PurgeOldLogsJob
$purgeJob = new PurgeOldLogsJob();
$purgeJob->execute([]);

// Verify that old log is deleted and new log is preserved
DB::clearIdentityMap();
$oldLogExistsAfter = DB::query("SELECT id FROM audit_logs WHERE id = ?", [$oldLogId])->fetch();
$newLogExistsAfter = DB::query("SELECT id FROM audit_logs WHERE id = ?", [$newLogId])->fetch();

assert_test(empty($oldLogExistsAfter), "Old log entry (older than a year) was successfully purged");
assert_test(!empty($newLogExistsAfter), "New log entry (less than a year old) was successfully preserved");

// Cleanup test logs
DB::query("DELETE FROM audit_logs WHERE id IN (?, ?)", [$oldLogId, $newLogId]);

// 9. Test Dead-Lettering After Exhausted Retry Attempts
// Simulates a job whose worker crashes every time (never reaches the caught-exception 'failed'
// path) -- its row keeps going stale and getting reclaimed. Fast-forward past that ceiling directly
// via SQL (reserved_at far enough in the past to be reclaimable, attempts already at the ceiling)
// rather than looping runNextPendingJob() MAX_ATTEMPTS times, since MockSuccessJob always succeeds.
echo "Testing dead-lettering after exhausted retry attempts...\n";
DB::query("TRUNCATE TABLE queue_jobs");
MockSuccessJob::$wasExecuted = false;

$staleJobId = QueueManager::dispatch(MockSuccessJob::class, ['action' => 'test_dead_letter'], $siteId);
DB::query(
    "UPDATE queue_jobs SET status = 'reserved', attempts = ?, reserved_at = ? WHERE id = ?",
    [QueueManager::MAX_ATTEMPTS, gmdate('Y-m-d H:i:s', time() - 1000), $staleJobId]
);

$deadLetterProcessed = QueueManager::runNextPendingJob(900);
assert_test($deadLetterProcessed === true, "runNextPendingJob() reports the exhausted job as handled");
assert_test(MockSuccessJob::$wasExecuted === false, "Job past its attempt ceiling is dead-lettered instead of executed");

DB::clearIdentityMap();
$deadLetteredJob = QueueJob::find($staleJobId);
assert_test($deadLetteredJob->status === 'failed', "Exhausted job's status transitioned to 'failed'");
assert_test($deadLetteredJob->attempts == QueueManager::MAX_ATTEMPTS + 1, "Attempts recorded the final over-ceiling increment");
assert_test(strpos($deadLetteredJob->error_message, 'Exceeded max retry attempts') !== false, "Dead-letter error_message explains why it stopped retrying");

// A subsequent call must not find the now-'failed' row claimable again (status='failed' is excluded
// from the reclaim WHERE clause), proving this doesn't loop.
$afterDeadLetter = QueueManager::runNextPendingJob(900);
assert_test($afterDeadLetter === false, "Dead-lettered job is not reclaimed again on a subsequent call");

echo "Job Queue & Runner System tests completed successfully!\n\n";
