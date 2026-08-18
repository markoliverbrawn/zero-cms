<?php

declare(strict_types=1);

/**
 * File: src/Support/Logger.php
 * Architectural Purpose: Global diagnostic tools, cryptographic security handlers, SMTP email transmitters, and text helpers.
 * Package: Zero\Support
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support;

use Zero\Core\App;
use Zero\Database\DB;

/**
 * Class Logger
 *
 * Writes audit-log entries, resolving the active tenant automatically so a caller records what
 * happened without also having to state which site it happened on.
 */
class Logger
{
    /**
     * Dispatch an audit log entry, dynamically resolving the active tenant site_id.
     */
    public static function log($userId, $action, $objectType = null, $objectId = null, $meta = null)
    {
        $metaJson = $meta ? \json_encode($meta) : null;
        $id = Security::uuidv7();
        $siteId = App::getCurrentSiteId() ?: null;
        
        DB::query(
            'INSERT INTO audit_logs (id, site_id, user_id, action, object_type, object_id, meta, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())', 
            [$id, $siteId, $userId, $action, $objectType, $objectId, $metaJson]
        );
    }
}
