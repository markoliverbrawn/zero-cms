<?php

declare(strict_types=1);

/**
 * File: src/Modules/Queue/Support/Scheduler.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Queue\Support
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Queue\Support;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Modules\Queue\Support\QueueManager;
use Zero\Support\Logger;
use Zero\Support\Security;

/**
 * Class Scheduler
 *
 * Recurring-task registry and runner. Tasks are registered with a job class, a payload, and a
 * cron-style expression; run() works out which are now due and dispatches them, so scheduling is
 * declared in code while execution stays with the queue.
 */
class Scheduler
{
    /**
     * Expression meaning "never run" -- lets a per-site schedule switch a task off for that site.
     */
    public const OFF = 'off';

    /**
     * Every expression isDue() understands, plus OFF.
     */
    public const EXPRESSIONS = [self::OFF, 'every_minute', 'hourly', 'daily', 'weekly'];

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
        if ($expression === self::OFF) {
            return false;
        }

        if ($lastRunAt === null) {
            return true; // Never run before, so it's definitely due!
        }

        $lastTime = \strtotime($lastRunAt . ' UTC');
        $diff = \time() - $lastTime;

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
     * @param string|callable $expression Interval expression (see EXPRESSIONS), or a callable
     *                                    receiving the Site being evaluated and returning one, for
     *                                    schedules that come from per-site settings
     * @return void
     */
    public static function register(string $jobClass, array $payload, $expression): void
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
        $now = \gmdate('Y-m-d H:i:s');
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
                $expression = self::resolveExpression($task['expression'], $site, $jobClass);
                if ($expression === self::OFF) {
                    continue;
                }

                try {
                    $pdo->beginTransaction();

                    // Ensure a tracking row exists first (no-op upsert when already present), so the
                    // SELECT ... FOR UPDATE below always has a real row to lock -- a lock request
                    // against a not-yet-existing row cannot be relied on to serialize concurrent
                    // callers the way a row lock can. Concurrent HTTP-triggered invocations of this
                    // method (see SchedulerApiController) must not both see the same task as due and
                    // dispatch it twice, which an unlocked SELECT-then-decide would allow.
                    $insertStmt = $pdo->prepare("
                        INSERT INTO queue_scheduled_tasks
                        (id, site_id, task_key, payload, expression, last_run_at, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, NULL, ?, ?)
                        ON DUPLICATE KEY UPDATE task_key = task_key
                    ");
                    $insertStmt->execute([
                        Security::uuidv7(),
                        $siteId,
                        $jobClass,
                        \json_encode($payload),
                        $expression,
                        $now,
                        $now
                    ]);

                    $stmt = $pdo->prepare("
                        SELECT id, last_run_at FROM queue_scheduled_tasks
                        WHERE site_id = ? AND task_key = ? AND deleted_at IS NULL
                        FOR UPDATE
                    ");
                    $stmt->execute([$siteId, $jobClass]);
                    $row = $stmt->fetch();

                    if (self::isDue($expression, $row['last_run_at'])) {
                        // 1. Dispatch job to queue asynchronously
                        QueueManager::dispatch($jobClass, $payload, $siteId);

                        // 2. Record that this task just ran, while still holding the row lock
                        $updateStmt = $pdo->prepare("
                            UPDATE queue_scheduled_tasks
                            SET last_run_at = ?, updated_at = ?
                            WHERE id = ?
                        ");
                        $updateStmt->execute([$now, $now, $row['id']]);
                    }

                    $pdo->commit();
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

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

    /**
     * Resolve a task's expression for one site, treating anything unrecognized as OFF.
     *
     * An unknown expression would otherwise run once (no last_run_at yet) and then never again,
     * silently; logging it and switching the task off makes the misconfiguration visible.
     *
     * @param string|callable $expression The registered expression or resolver.
     * @param \Zero\Models\Site $site The site being evaluated.
     * @param string $jobClass The task's job class, for the log message.
     * @return string A member of EXPRESSIONS.
     */
    protected static function resolveExpression($expression, $site, string $jobClass): string
    {
        $resolved = \is_string($expression) ? $expression : $expression($site);
        $resolved = \is_string($resolved) ? \trim($resolved) : '';

        if (!\in_array($resolved, self::EXPRESSIONS, true)) {
            \error_log("Scheduler: unknown schedule '{$resolved}' for {$jobClass} on site {$site->id}; not running it.");
            return self::OFF;
        }

        return $resolved;
    }
}
