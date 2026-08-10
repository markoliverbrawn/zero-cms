<?php
/**
 * File: src/Modules/Queue/Support/QueueManager.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Queue\Support
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */



namespace Zero\Modules\Queue\Support;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Interfaces\Job as JobInterface;
use Zero\Models\Site;
use Zero\Modules\Queue\Models\QueueJob;
use Zero\Support\Logger;
use Exception;
use Throwable;

/**
 * Class QueueManager
 *
 * Provides structural platform implementation and operational encapsulation.
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
            'payload' => json_encode($payload),
            'status' => 'pending',
            'attempts' => 0
        ]);
        $job->save();
        
        return $job->id;
    }

    /**
     * Atomically selects and processes the next pending or expired job row.
     *
     * @param int|null $lockTimeout Inseconds (default: 900)
     * @return bool True if a job was found and processed, false otherwise
     */
    public static function runNextPendingJob(?int $lockTimeout = 900): bool
    {
        $pdo = DB::getPDO();
        $now = gmdate('Y-m-d H:i:s');
        $expiredTime = gmdate('Y-m-d H:i:s', time() - $lockTimeout);

        try {
            // Start reservation transaction
            $pdo->beginTransaction();

            // 1. Row-lock selection for double-locking race safety
            $stmt = $pdo->prepare("
                SELECT id, site_id, job_class, payload, attempts FROM queue_jobs 
                WHERE (
                    status = 'pending' 
                    OR (status = 'reserved' AND reserved_at < ?)
                )
                AND deleted_at IS NULL 
                ORDER BY created_at ASC 
                LIMIT 1 
                FOR UPDATE
            ");
            $stmt->execute([$expiredTime]);
            $row = $stmt->fetch();

            if (!$row) {
                $pdo->rollBack();
                return false; // No jobs available
            }

            $jobId = $row['id'];
            $siteId = $row['site_id'];
            $jobClass = $row['job_class'];
            $payloadData = json_decode($row['payload'], true) ?? [];
            $attempts = intval($row['attempts']) + 1;

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
        $startTime = microtime(true);

        try {
            // Apply Site Tenant context scoping boundaries
            $site = Site::find($siteId);
            if ($site) {
                App::setCurrentSite($site);
                // Flush identity mapping cache of models to ensure absolute tenant separation
                DB::clearIdentityMap();
            }

            // Suppress output stream using buffering wrapper
            ob_start();

            if (!class_exists($jobClass)) {
                throw new Exception("Job class '{$jobClass}' not found on disk");
            }

            $jobInstance = new $jobClass();
            if (!($jobInstance instanceof JobInterface)) {
                throw new Exception("Job class '{$jobClass}' must implement \\Zero\\Interfaces\\Job interface");
            }

            // Execute job
            $jobInstance->execute($payloadData);

            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            // Mark job as completed
            $completeStmt = $pdo->prepare("
                UPDATE queue_jobs 
                SET status = 'completed', 
                    updated_at = ? 
                WHERE id = ?
            ");
            $completeStmt->execute([gmdate('Y-m-d H:i:s'), $jobId]);

            // Log completion event inside audit logs
            Logger::log(
                userId: null,
                action: 'job_completed',
                objectType: 'queue_jobs',
                objectId: $jobId,
                meta: [
                    'job_class' => $jobClass,
                    'attempts' => $attempts,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000)
                ]
            );

        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            $errorTrace = $e->getMessage() . "\n\n" . $e->getTraceAsString();

            // Mark as failed and write down diagnostic error traceback
            $failedTime = gmdate('Y-m-d H:i:s');
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
}
