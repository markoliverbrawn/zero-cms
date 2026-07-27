<?php

namespace Zero\Modules\Queue\Jobs;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Interfaces\Job;

class PurgeOldLogsJob implements Job
{
    /**
     * Execute the job to purge old audit log entries for the current tenant.
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

        $oneYearAgo = gmdate('Y-m-d H:i:s', strtotime('-1 year'));

        $pdo = DB::getPDO();
        $stmt = $pdo->prepare("
            DELETE FROM audit_logs 
            WHERE site_id = ? AND created_at < ?
        ");
        $stmt->execute([$siteId, $oneYearAgo]);
    }
}
