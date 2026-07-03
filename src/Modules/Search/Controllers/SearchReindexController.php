<?php

namespace Zero\Modules\Search\Controllers;

use Zero\Interfaces\Controller;
use Zero\Core\App;
use Zero\Database\DB;
use Zero\Modules\Search\Services\SearchService;
use Zero\Support\Security;

class SearchReindexController implements Controller
{
    /**
     * Process the back-office re-indexing AJAX triggers.
     * Handles both 'start' and 'batch' endpoints inside a single controller handler.
     *
     * @param mixed $param Custom parameter from Router
     * @return void
     */
    public function handle($param)
    {
        // Enforce administrative permissions
        $user = App::getCurrentUser();
        if (!$user || ($user->role !== 'super_admin' && $user->role !== 'editor')) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized back-office access.']);
            exit;
        }

        // Parse path routing
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($uri, PHP_URL_PATH);

        if (str_ends_with($path, '/start')) {
            $this->handleStart();
        } elseif (str_ends_with($path, '/batch')) {
            $this->handleBatch();
        } else {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['error' => 'API endpoint not found.']);
            exit;
        }
    }

    /**
     * Execute the batch indexing step.
     *
     * @return void
     */
    protected function handleBatch()
    {
        header('Content-Type: application/json');
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?? [];

        $modelClass = $input['model'] ?? '';
        $ids = $input['ids'] ?? [];

        if (empty($modelClass) || empty($ids)) {
            echo json_encode(['success' => false, 'error' => 'Missing model class or IDs.']);
            exit;
        }

        if (!class_exists($modelClass)) {
            echo json_encode(['success' => false, 'error' => 'Model class does not exist.']);
            exit;
        }

        $indexed = 0;
        $tableName = $modelClass::getTableName();
        $siteId = App::getCurrentSiteId();

        // Query only the requested batch IDs inside tenant scoping
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$siteId], $ids);
        $sql = "SELECT * FROM {$tableName} WHERE site_id = ? AND id IN ({$placeholders}) AND deleted_at IS NULL";
        
        $rows = DB::query($sql, $params)->fetchAll();
        foreach ($rows as $row) {
            $model = new $modelClass($row);
            $model->indexInSearch();
            $indexed++;
        }

        echo json_encode(['success' => true, 'indexed' => $indexed]);
        exit;
    }

    /**
     * Clear the search index and scan/retrieve all searchable record IDs.
     *
     * @return void
     */
    protected function handleStart()
    {
        header('Content-Type: application/json');
        $siteId = App::getCurrentSiteId();

        // 1. Clear existing search index for this site
        SearchService::clear($siteId);

        // 2. Scan and aggregate all record IDs that need indexing across searchable models
        $batches = [];
        $totalCount = 0;

        foreach (SearchService::getSearchables() as $modelClass => $config) {
            if (class_exists($modelClass)) {
                $tableName = $modelClass::getTableName();
                // Fetch all IDs for this model
                $sql = "SELECT id FROM {$tableName} WHERE site_id = ? AND deleted_at IS NULL";
                $rows = DB::query($sql, [$siteId])->fetchAll();
                
                $ids = array_column($rows, 'id');
                if (!empty($ids)) {
                    $batches[] = [
                        'model' => $modelClass,
                        'ids' => $ids
                    ];
                    $totalCount += count($ids);
                }
            }
        }

        echo json_encode([
            'success' => true,
            'total' => $totalCount,
            'batches' => $batches
        ]);
        exit;
    }
}
