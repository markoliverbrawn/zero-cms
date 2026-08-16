<?php

declare(strict_types=1);

/**
 * File: src/Modules/Blog/Jobs/PurgeOldCommentsJob.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Blog\Jobs
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Blog\Jobs;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Interfaces\Job;

/**
 * Class PurgeOldCommentsJob
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class PurgeOldCommentsJob implements Job
{
    /**
     * Execute the job to automatically purge rejected or spam comments older than the site's
     * configured 'spam_retention_days' setting (default 7 days).
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

        $site = App::getCurrentSite();
        $retentionDays = $site ? (int)$site->getModuleSetting('blog', 'spam_retention_days', 7) : 7;
        $cutoff = \gmdate('Y-m-d H:i:s', \strtotime("-{$retentionDays} days"));

        $pdo = DB::getPDO();
        $stmt = $pdo->prepare("
            DELETE FROM blog_comments
            WHERE site_id = ?
              AND status IN ('rejected', 'spam')
              AND created_at < ?
        ");
        $stmt->execute([$siteId, $cutoff]);
    }
}
