<?php

declare(strict_types=1);

/**
 * File: src/Modules/Queue/Jobs/PurgeOldLogsJob.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Queue\Jobs
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Queue\Jobs;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Interfaces\Job;

/**
 * Class PurgeOldLogsJob
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class PurgeOldLogsJob implements Job
{
    /**
     * Execute the job to purge old audit log entries for the current tenant, older than the
     * site's configured 'audit_log_retention_days' setting (default 365 days).
     *
     * @param array $payload
     * @return void
     */
    public function execute(array $payload): void
    {
        $siteId = App::getCurrentSiteId();
        if (!$siteId) {
            return;
        }

        $retentionDays = (int)App::getModuleSetting('queue', 'audit_log_retention_days', 365);
        $cutoff = \gmdate('Y-m-d H:i:s', \strtotime("-{$retentionDays} days"));

        $pdo = DB::getPDO();
        $stmt = $pdo->prepare("
            DELETE FROM audit_logs
            WHERE site_id = ? AND created_at < ?
        ");
        $stmt->execute([$siteId, $cutoff]);
    }
}
