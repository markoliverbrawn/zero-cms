<?php

namespace Zero\Modules\Blog\Jobs;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Interfaces\Job;

class PurgeOldCommentsJob implements Job
{
    /**
     * Execute the job to automatically purge rejected or spam comments more than 7 days old.
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

        $sevenDaysAgo = gmdate('Y-m-d H:i:s', strtotime('-7 days'));

        $pdo = DB::getPDO();
        $stmt = $pdo->prepare("
            DELETE FROM blog_comments 
            WHERE site_id = ? 
              AND status IN ('rejected', 'spam') 
              AND created_at < ?
        ");
        $stmt->execute([$siteId, $sevenDaysAgo]);
    }
}
