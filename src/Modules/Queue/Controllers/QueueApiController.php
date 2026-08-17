<?php

declare(strict_types=1);

/**
 * File: src/Modules/Queue/Controllers/QueueApiController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Queue\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Queue\Controllers;

use Zero\Core\Env;
use Zero\Interfaces\Controller;
use Zero\Modules\Queue\Support\QueueManager;

/**
 * Class QueueApiController
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class QueueApiController implements Controller
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
        $envToken = Env::get('QUEUE_TRIGGER_TOKEN');
        $requestToken = $_GET['token'] ?? $_SERVER['HTTP_X_QUEUE_TOKEN'] ?? null;

        if (empty($envToken)) {
            \http_response_code(500);
            echo \json_encode(['error' => 'Configuration Error - QUEUE_TRIGGER_TOKEN must be defined inside .env first']);
            exit;
        }

        if ($requestToken !== $envToken) {
            \http_response_code(403);
            echo \json_encode(['error' => 'Forbidden - Invalid or missing queue trigger token']);
            exit;
        }

        // 3. Rate Limiting & Cooldown Lock to prevent thread/connection exhaustion
        $lockFile = APPLICATION_ROOT . '/storage/queue-web-lock.txt';
        if (\file_exists($lockFile)) {
            $lastTrigger = \filemtime($lockFile);
            if (\time() - $lastTrigger < 5) { // 5-second rate limiting cooldown
                \http_response_code(429);
                echo \json_encode(['error' => 'Too Many Requests - Rate limit cooldown active']);
                exit;
            }
        }
        \touch($lockFile);

        // Execute non-blocking job run
        $processed = QueueManager::runNextPendingJob();

        echo \json_encode([
            'success' => true,
            'processed' => $processed
        ]);
        exit;
    }
}
