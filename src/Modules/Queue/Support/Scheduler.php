<?php
/**
 * File: src/Modules/Queue/Support/Scheduler.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Queue\Support
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Queue\Support;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Support\Security;
use Zero\Support\Logger;
use Zero\Modules\Queue\Support\QueueManager;

/**
 * Class Scheduler
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class Scheduler
{
    protected static array $tasks = [];

    /**
     * Determine if a scheduled task is due based on its interval and last run time.
     *
     * @param string $expression Interval expression (every_minute, hourly, daily, weekly)
     * @param string|null $lastRunAt ISO UTC timestamp
     * @return bool True if due to run, false otherwise
     */
    protected static function isDue(string $expression, ?string $lastRunAt): bool
    {
        if ($lastRunAt === null) {
            return true; // Never run before, so it's definitely due!
        }

        $lastTime = strtotime($lastRunAt . ' UTC');
        $diff = time() - $lastTime;

        switch ($expression) {
            case 'every_minute':
                return $diff >= 60;
            case 'hourly':
                return $diff >= 3600;
            case 'daily':
                return $diff >= 86400;
            case 'weekly':
                return $diff >= 604800;
            default:
                return false;
        }
    }

    /**
     * Register a job class to run on a specific interval schedule.
     *
     * @param string $jobClass FQN of the job class
     * @param array $payload Arguments payload
     * @param string $expression Interval expression (every_minute, hourly, daily, weekly)
     * @return void
     */
    public static function register(string $jobClass, array $payload, string $expression): void
    {
        self::$tasks[] = [
            'job_class' => $jobClass,
            'payload' => $payload,
            'expression' => $expression
        ];
    }

    /**
     * Evaluate and dispatch all registered scheduled tasks across active multi-tenant sites.
     *
     * @return void
     */
    public static function run(): void
    {
        $originalSite = App::getCurrentSite();
        $sites = \Zero\Models\Site::all();
        $now = gmdate('Y-m-d H:i:s');
        $pdo = DB::getPDO();

        foreach ($sites as $site) {
            if (!$site->isModuleEnabled('queue')) {
                continue;
            }

            // Set tenant scope
            App::setCurrentSite($site);
            $siteId = $site->id;

            foreach (self::$tasks as $task) {
                $jobClass = $task['job_class'];
                $payload = $task['payload'];
                $expression = $task['expression'];

                try {
                    // Check if task is already tracked in database
                    $stmt = $pdo->prepare("
                        SELECT id, last_run_at FROM queue_scheduled_tasks 
                        WHERE site_id = ? AND task_key = ? AND deleted_at IS NULL
                    ");
                    $stmt->execute([$siteId, $jobClass]);
                    $row = $stmt->fetch();

                    $lastRunAt = $row ? $row['last_run_at'] : null;

                    if (self::isDue($expression, $lastRunAt)) {
                        // 1. Dispatch job to queue asynchronously
                        QueueManager::dispatch($jobClass, $payload, $siteId);

                        // 2. Insert or update the scheduled tasks tracking row
                        if ($row) {
                            $updateStmt = $pdo->prepare("
                                UPDATE queue_scheduled_tasks 
                                SET last_run_at = ?, updated_at = ? 
                                WHERE id = ?
                            ");
                            $updateStmt->execute([$now, $now, $row['id']]);
                        } else {
                            $id = Security::uuidv7();
                            $insertStmt = $pdo->prepare("
                                INSERT INTO queue_scheduled_tasks 
                                (id, site_id, task_key, payload, expression, last_run_at, created_at, updated_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $insertStmt->execute([
                                $id, 
                                $siteId, 
                                $jobClass, 
                                json_encode($payload), 
                                $expression, 
                                $now, 
                                $now, 
                                $now
                            ]);
                        }
                    }
                } catch (\Throwable $e) {
                    Logger::log(
                        userId: null,
                        action: 'scheduler_error',
                        objectType: 'queue_scheduled_tasks',
                        objectId: null,
                        meta: [
                            'job_class' => $jobClass,
                            'error' => $e->getMessage()
                        ]
                    );
                }
            }
        }

        // Restore context
        if ($originalSite) {
            App::setCurrentSite($originalSite);
        }
    }
}
