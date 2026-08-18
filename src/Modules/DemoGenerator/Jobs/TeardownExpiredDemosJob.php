<?php

declare(strict_types=1);

/**
 * File: src/Modules/DemoGenerator/Jobs/TeardownExpiredDemosJob.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\DemoGenerator\Jobs
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

// src/Modules/DemoGenerator/Jobs/TeardownExpiredDemosJob.php

namespace Zero\Modules\DemoGenerator\Jobs;

use Zero\Database\DB;
use Zero\Interfaces\Job;
use Zero\Models\Site;

/**
 * Class TeardownExpiredDemosJob
 *
 * Scheduled job that reaps demo tenants whose expires_at has passed, deleting each site and the
 * records cascading from it.
 */
class TeardownExpiredDemosJob implements Job
{
    /**
     * Execute the job to query and permanently purge all expired demo sites and their physical assets.
     */
    public function execute(array $payload): void
    {
        // Query expired sites (where expires_at is in the past) across all multi-tenant boundaries
        $pdo = DB::getPDO();
        $stmt = $pdo->query("SELECT id FROM sites WHERE expires_at IS NOT NULL AND expires_at < NOW() AND deleted_at IS NULL");
        $expiredRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($expiredRows as $row) {
            $site = Site::find($row['id']);
            if ($site) {
                // This triggers cascading permanent deletions of all users, pages, media records, and physical disk files/folders
                $site->forceDelete();
            }
        }
    }
}
