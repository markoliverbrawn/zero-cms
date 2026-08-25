<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Controllers/Api/AuditLogApiController.php
 * Architectural Purpose: REST API endpoint for purging audit log records, either for the active
 * tenant site or (super admin only) globally across all sites.
 * Package: Zero\Modules\Admin\Controllers\Api
 */

namespace Zero\Modules\Admin\Controllers\Api;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Support\Logger;
use Zero\Support\Permissions;

/**
 * Class AuditLogApiController
 */
class AuditLogApiController extends AdminApiControllerBase
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $matches Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($matches)
    {
        $user = $this->authenticate();
        $siteId = App::getCurrentSiteId();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = \parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $body = $this->parseBody();

        if (\preg_match('#^/api/v1/admin/audit-logs/purge/?$#', $uri) && $method === 'POST') {
            $this->handlePurgeAuditLogs($siteId, $user, $body);
        }

        $this->respond(['success' => false, 'error' => 'Endpoint not found or method not allowed'], 404);
    }

    /**
     * Handle purge audit logs processing implementation helper.
     *
     * @param string $siteId Argument descriptor.
     * @param array $user Argument descriptor.
     * @param array $body Argument descriptor.
     * @return mixed Response output.
     */
    protected function handlePurgeAuditLogs(string $siteId, array $user, array $body)
    {
        $purgeAll = !empty($body['purge_all_sites']);

        if ($purgeAll) {
            // Enforce that only roles granted audit.purge_global can purge all sites globally!
            if (!Permissions::roleHas($user['role'] ?? '', 'audit.purge_global')) {
                $this->respond([
                    'success' => false,
                    'error' => 'Forbidden: Only super administrators can purge logs globally across all sites'
                ], 403);
            }

            // Execute global delete statement on audit_logs table!
            DB::query("DELETE FROM audit_logs");

            // Also log this global purge action itself in the logs (since it was just emptied, this starts fresh)
            Logger::log('purge_all_audit_logs', 'audit_logs', '*', [
                'user' => $user['username'],
                'scope' => 'global'
            ]);

            $this->respond([
                'success' => true,
                'message' => 'Successfully purged all audit logs globally across all sites'
            ]);
        } else {
            // Enforce that the role has rights to purge this tenant's own logs
            if (!Permissions::roleHas($user['role'] ?? '', 'audit.purge')) {
                $this->respond([
                    'success' => false,
                    'error' => 'Forbidden: You do not have permission to purge audit logs'
                ], 403);
            }

            // Purge only the active site's logs!
            DB::query("DELETE FROM audit_logs WHERE site_id = ?", [$siteId]);

            // Log the purge action itself for the site
            Logger::log('purge_audit_logs', 'audit_logs', '*', [
                'user' => $user['username'],
                'scope' => 'site'
            ]);

            $this->respond([
                'success' => true,
                'message' => 'Successfully purged all audit logs for this site'
            ]);
        }
    }
}
