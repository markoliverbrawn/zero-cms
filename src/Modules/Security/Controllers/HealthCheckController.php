<?php

declare(strict_types=1);

/**
 * File: src/Modules/Security/Controllers/HealthCheckController.php
 * Architectural Purpose: Back-office screen for the deployment health checks.
 * Package: Zero\Modules\Security\Controllers
 * Systemic Role: Shows the latest or a chosen run with the run history, and runs the checks on demand.
 */

namespace Zero\Modules\Security\Controllers;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Interfaces\Controller;
use Zero\Modules\Security\Models\SecurityAudit;
use Zero\Modules\Security\Services\HealthCheckRunner;

/**
 * Class HealthCheckController
 *
 * GET renders a run (?id=, defaulting to the latest) alongside the site's run history.
 * POST (CSRF-checked) runs every check now, archives the run and redirects to it.
 */
class HealthCheckController implements Controller
{
    /**
     * Number of past runs listed in the history table.
     */
    private const HISTORY_LIMIT = 50;

    /**
     * Handle the request.
     *
     * @param mixed $param Route matches (unused).
     * @return void
     */
    public function handle($param)
    {
        App::applyAuthMiddleware();
        App::requirePermission('security.audit');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            App::applyCsrfMiddleware();
            $this->runNow();
        }

        $siteId = App::getCurrentSiteId();
        $history = DB::query(
            "SELECT a.id, a.created_at, a.status, a.environment, u.username
             FROM security_audits a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.site_id = ? AND a.deleted_at IS NULL
             ORDER BY a.created_at DESC
             LIMIT " . self::HISTORY_LIMIT,
            [$siteId]
        )->fetchAll();

        $selectedId = (string) ($_GET['id'] ?? ($history[0]['id'] ?? ''));
        $selected = $selectedId !== '' ? SecurityAudit::findInCurrentSite($selectedId) : null;
        $results = $selected !== null ? \json_decode((string) ($selected->results ?? ''), true) : null;

        $flash = ['success' => (string) ($_SESSION['health_check_success'] ?? ''), 'error' => (string) ($_SESSION['health_check_error'] ?? '')];
        unset($_SESSION['health_check_success'], $_SESSION['health_check_error']);

        App::render('security/health_check', [
            'title' => 'Health Check',
            'flash' => $flash,
            'history' => $history,
            'selected' => $selected,
            'results' => \is_array($results) ? $results : null,
        ]);
        exit;
    }

    /**
     * Run every check now, archive the run, and redirect to it.
     *
     * @return void
     */
    private function runNow(): void
    {
        $site = App::getCurrentSite();
        try {
            $run = HealthCheckRunner::run($site, isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null);
            $_SESSION['health_check_success'] = 'Health check complete.';
            \header('Location: /admin/security/audit?id=' . \rawurlencode((string) $run['audit']->id));
        } catch (\Exception $e) {
            \error_log('HealthCheckController: health check run failed: ' . $e->getMessage());
            $_SESSION['health_check_error'] = 'The health check could not be saved.';
            \header('Location: /admin/security/audit');
        }
        exit;
    }
}
