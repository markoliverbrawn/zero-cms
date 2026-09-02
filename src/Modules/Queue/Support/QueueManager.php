<?php

declare(strict_types=1);

/**
 * File: src/Modules/Queue/Support/QueueManager.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Queue\Support
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Queue\Support;

use Exception;
use Throwable;
use Zero\Core\App;
use Zero\Database\DB;
use Zero\Interfaces\Job as JobInterface;
use Zero\Models\Site;
use Zero\Modules\Queue\Models\QueueJob;
use Zero\Support\Logger;

/**
 * Class QueueManager
 *
 * The job queue itself: dispatch() persists a job with its payload for later execution, and
 * runNextPendingJob() is what the worker process calls to claim and run the next one, recording
 * success or failure against the row.
 */
class QueueManager
{
    /**
     * Dispatch a new stateless job to the queue.
     *
     * @param string $jobClass FQN of the job class
     * @param array $payload Primitive parameters payload
     * @param string|null $siteId Optional site ID fallback
     * @return string Generated job UUIDv7 ID
     */
    public static function dispatch(string $jobClass, array $payload, ?string $siteId = null): string
    {
        $siteId = $siteId ?? App::getCurrentSiteId();
        
        $job = new QueueJob([
            'site_id' => $siteId,
            'job_class' => $jobClass,
            'payload' => \json_encode($payload),
            'status' => 'pending',
            'attempts' => 0
        ]);
        $job->save();
        
        return $job->id;
    }

    /**
     * Jobs that never reach a caught try/catch failure (an OOM kill, a hard process termination, a
     * request timeout mid-execute()) leave their row in 'reserved' forever, only for it to become
     * reclaimable again once $lockTimeout elapses -- with no ceiling, a job whose worker crashes
     * every single time would retry forever, every $lockTimeout seconds, indefinitely. This caps how
     * many times a row may be reclaimed via the stale-lock path before it's dead-lettered instead.
     * Caught-exception failures (see the outer catch below) are unaffected -- they already terminate
     * in a single attempt via status='failed', which this same ceiling also applies to for jobs that
     * fail every retry through that path.
     */
    public const MAX_ATTEMPTS = 5;

    /**
     * Atomically selects and processes the next pending or expired job row.
     *
     * @param int|null $lockTimeout Inseconds (default: 900)
     * @param int $maxAttempts Reclaim/attempt ceiling before a job is dead-lettered (default: self::MAX_ATTEMPTS)
     * @return bool True if a job was found and handled (run or dead-lettered), false otherwise
     */
    public static function runNextPendingJob(?int $lockTimeout = 900, int $maxAttempts = self::MAX_ATTEMPTS): bool
    {
        $pdo = DB::getPDO();
        $now = \gmdate('Y-m-d H:i:s');
        $expiredTime = \gmdate('Y-m-d H:i:s', \time() - $lockTimeout);

        try {
            // Start reservation transaction
            $pdo->beginTransaction();

            // 1a. Fair-share candidate selection: rank each site's own claimable jobs oldest-first,
            // then across sites prefer whichever site's turn hasn't come up yet (lowest per-site
            // rank first, ties broken by age). This stops one tenant's burst of dispatches from
            // monopolizing the worker ahead of a site with a single older-arriving, lower-volume
            // job -- pure global "oldest job wins" would otherwise let a high-volume site's entire
            // backlog run before a quiet site's job ever gets a turn. This is a plain read (no lock)
            // just to pick a candidate; it's re-validated and locked in step 1b.
            $candidateStmt = $pdo->prepare("
                SELECT id FROM (
                    SELECT id, created_at,
                           ROW_NUMBER() OVER (PARTITION BY site_id ORDER BY created_at ASC) AS site_turn
                    FROM queue_jobs
                    WHERE (
                        status = 'pending'
                        OR (status = 'reserved' AND reserved_at < ?)
                    )
                    AND deleted_at IS NULL
                ) candidates
                ORDER BY site_turn ASC, created_at ASC
                LIMIT 1
            ");
            $candidateStmt->execute([$expiredTime]);
            $candidateId = $candidateStmt->fetchColumn();

            if (!$candidateId) {
                $pdo->rollBack();
                return false; // No jobs available
            }

            // 1b. Row-lock the chosen candidate for double-locking race safety. If a concurrent
            // worker already claimed it between 1a and here, treat this call as having found
            // nothing rather than retrying -- the next invocation re-evaluates fairness fresh.
            $stmt = $pdo->prepare("
                SELECT id, site_id, job_class, payload, attempts FROM queue_jobs
                WHERE id = ?
                AND (
                    status = 'pending'
                    OR (status = 'reserved' AND reserved_at < ?)
                )
                AND deleted_at IS NULL
                FOR UPDATE
            ");
            $stmt->execute([$candidateId, $expiredTime]);
            $row = $stmt->fetch();

            if (!$row) {
                $pdo->rollBack();
                return false; // Candidate claimed by a concurrent worker between selection and lock
            }

            $jobId = $row['id'];
            $siteId = $row['site_id'];
            $jobClass = $row['job_class'];
            $payloadData = \json_decode($row['payload'], true) ?? [];
            $attempts = \intval($row['attempts']) + 1;

            // 1c. Dead-letter a job that has exhausted its retry ceiling rather than reclaiming it
            // again -- this only fires for rows that keep going stale without ever reaching the
            // caught-exception failure path below, since that path already stops retries in one shot.
            if ($attempts > $maxAttempts) {
                $deadLetterTime = \gmdate('Y-m-d H:i:s');
                $deadLetterStmt = $pdo->prepare("
                    UPDATE queue_jobs
                    SET status = 'failed',
                        attempts = ?,
                        failed_at = ?,
                        error_message = ?,
                        updated_at = ?
                    WHERE id = ?
                ");
                $deadLetterStmt->execute([
                    $attempts,
                    $deadLetterTime,
                    "Exceeded max retry attempts ({$maxAttempts}) after repeatedly going stale without completing -- worker likely crashed, was killed, or timed out mid-execution each time.",
                    $deadLetterTime,
                    $jobId
                ]);
                $pdo->commit();

                Logger::log(
                    userId: null,
                    action: 'job_dead_lettered',
                    objectType: 'queue_jobs',
                    objectId: $jobId,
                    meta: ['job_class' => $jobClass, 'attempts' => $attempts]
                );

                return true; // A row was found and handled (dead-lettered), even though not executed
            }

            // 2. Transition state immediately to reserved
            $updateStmt = $pdo->prepare("
                UPDATE queue_jobs
                SET status = 'reserved',
                    attempts = ?,
                    reserved_at = ?,
                    updated_at = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$attempts, $now, $now, $jobId]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }

        // 3. SHIFT STATE & EXECUTE
        $originalSite = App::getCurrentSite();
        $startTime = \microtime(true);

        try {
            // Apply Site Tenant context scoping boundaries
            $site = Site::find($siteId);
            if ($site) {
                App::setCurrentSite($site);
                // Flush identity mapping cache of models to ensure absolute tenant separation
                DB::clearIdentityMap();
            }

            // Suppress output stream using buffering wrapper
            \ob_start();

            if (!\class_exists($jobClass)) {
                throw new Exception("Job class '{$jobClass}' not found on disk");
            }

            $jobInstance = new $jobClass();
            if (!($jobInstance instanceof JobInterface)) {
                throw new Exception("Job class '{$jobClass}' must implement \\Zero\\Interfaces\\Job interface");
            }

            // Execute job
            $jobInstance->execute($payloadData);

            if (\ob_get_level() > 0) {
                \ob_end_clean();
            }

            // Mark job as completed
            $completeStmt = $pdo->prepare("
                UPDATE queue_jobs 
                SET status = 'completed', 
                    updated_at = ? 
                WHERE id = ?
            ");
            $completeStmt->execute([\gmdate('Y-m-d H:i:s'), $jobId]);

            // Log completion event inside audit logs
            Logger::log(
                userId: null,
                action: 'job_completed',
                objectType: 'queue_jobs',
                objectId: $jobId,
                meta: [
                    'job_class' => $jobClass,
                    'attempts' => $attempts,
                    'duration_ms' => \round((\microtime(true) - $startTime) * 1000)
                ]
            );

        } catch (Throwable $e) {
            if (\ob_get_level() > 0) {
                \ob_end_clean();
            }

            $errorTrace = $e->getMessage() . "\n\n" . $e->getTraceAsString();

            // Mark as failed and write down diagnostic error traceback
            $failedTime = \gmdate('Y-m-d H:i:s');
            $failStmt = $pdo->prepare("
                UPDATE queue_jobs 
                SET status = 'failed', 
                    failed_at = ?, 
                    error_message = ?, 
                    updated_at = ? 
                WHERE id = ?
            ");
            $failStmt->execute([$failedTime, $errorTrace, $failedTime, $jobId]);

            // Log failure event inside audit logs
            Logger::log(
                userId: null,
                action: 'job_failed',
                objectType: 'queue_jobs',
                objectId: $jobId,
                meta: [
                    'job_class' => $jobClass,
                    'error' => $e->getMessage()
                ]
            );
        } finally {
            // Restore previous environment context
            if ($originalSite) {
                App::setCurrentSite($originalSite);
                DB::clearIdentityMap();
            }
        }

        return true;
    }

    /**
     * Drains the queue by repeatedly calling runNextPendingJob() within a single call, instead of
     * processing just one job. A stateless HTTP trigger (Cloud Scheduler hitting the API endpoint
     * every 5 minutes) that only ever ran a single job per invocation was capped at 1 job / 5 min
     * of throughput regardless of how many jobs were actually waiting or how much of the request's
     * time budget went unused -- this lets one invocation work through the backlog until either the
     * queue is empty or the time/job budget runs out.
     *
     * @param int $maxDurationSeconds Wall-clock budget for this call (default: 800s, leaving margin
     *                                under Cloud Run's 900s request timeout for the in-flight job to
     *                                finish cleanly rather than being killed mid-execute()).
     * @param int|null $maxJobs Optional cap on jobs processed in one call, regardless of time left.
     * @return int Number of jobs handled (run or dead-lettered) during this call.
     */
    public static function runPendingJobs(int $maxDurationSeconds = 800, ?int $maxJobs = null): int
    {
        $startTime = \microtime(true);
        $processedCount = 0;

        while (true) {
            if (\microtime(true) - $startTime >= $maxDurationSeconds) {
                break;
            }
            if ($maxJobs !== null && $processedCount >= $maxJobs) {
                break;
            }
            if (!self::runNextPendingJob()) {
                break; // Queue is empty
            }
            $processedCount++;
        }

        return $processedCount;
    }
}
