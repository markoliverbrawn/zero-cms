<?php

declare(strict_types=1);

/**
 * File: src/Modules/Queue/Controllers/SchedulerApiController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Queue\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Queue\Controllers;

use Zero\Core\Env;
use Zero\Interfaces\Controller;
use Zero\Modules\Queue\Support\Scheduler;

/**
 * Class SchedulerApiController
 *
 * HTTP-triggerable entry point for the recurring-task Scheduler. Environments with no long-lived
 * process (Cloud Run and similar) cannot run bin/scheduler's daemon loop, so an external cron
 * (e.g. Cloud Scheduler) hits this endpoint instead to evaluate and dispatch due tasks.
 */
class SchedulerApiController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($param)
    {
        // Enforce Content-Type header
        \header('Content-Type: application/json');

        // 1. Enforce POST-only requests to prevent search crawler bot accidents
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            \http_response_code(405);
            echo \json_encode(['error' => 'Method Not Allowed - POST required']);
            exit;
        }

        // 2. Token-Gate Authorization to prevent anonymous DoS/attacks
        $envToken = Env::get('SCHEDULER_TRIGGER_TOKEN');
        $requestToken = $_GET['token'] ?? $_SERVER['HTTP_X_SCHEDULER_TOKEN'] ?? null;

        if (empty($envToken)) {
            \http_response_code(500);
            echo \json_encode(['error' => 'Configuration Error - SCHEDULER_TRIGGER_TOKEN must be defined inside .env first']);
            exit;
        }

        if ($requestToken !== $envToken) {
            \http_response_code(403);
            echo \json_encode(['error' => 'Forbidden - Invalid or missing scheduler trigger token']);
            exit;
        }

        // 3. Rate Limiting & Cooldown Lock to prevent thread/connection exhaustion
        $lockFile = APPLICATION_ROOT . '/storage/scheduler-web-lock.txt';
        if (\file_exists($lockFile)) {
            $lastTrigger = \filemtime($lockFile);
            if (\time() - $lastTrigger < 5) { // 5-second rate limiting cooldown
                \http_response_code(429);
                echo \json_encode(['error' => 'Too Many Requests - Rate limit cooldown active']);
                exit;
            }
        }
        \touch($lockFile);

        // Evaluate and dispatch every due recurring task across all tenants
        Scheduler::run();

        echo \json_encode(['success' => true]);
        exit;
    }
}
