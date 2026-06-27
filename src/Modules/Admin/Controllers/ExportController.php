<?php

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Core\CSVExporter;
use Zero\Interfaces\Controller;
use Exception;

class ExportController implements Controller
{
    /**
     * Handle incoming request to export model records as a CSV download.
     */
    public function handle($param)
    {
        $matches = $param;
        App::applyAuthMiddleware();
        
        $modelName = $matches[1] ?? '';

        // Restrict sensitive model exports to Super Administrators
        if ($modelName === 'audit_logs' || $modelName === 'users' || $modelName === 'sites') {
            App::applyRoleMiddleware('super_admin');
        }

        $modelClass = App::getModelClass($modelName);
        if (!$modelClass) {
            throw new Exception('Invalid model class for export');
        }

        // Retrieve all records for the active tenant site (ignores pagination limits for full export)
        $records = $modelClass::all([], 'created_at desc');

        // Formulate custom headers mapping for clear, premium CSV look
        $headers = [];
        if ($modelName === 'audit_logs') {
            $headers = [
                'created_at' => 'Timestamp (UTC)',
                'user_id' => 'Actor User ID',
                'action' => 'Security Action',
                'object_type' => 'Target Object Type',
                'object_id' => 'Target Record ID',
                'meta' => 'Metadata Details'
            ];
        }

        $filename = App::getCurrentSite()->name . '-' . $modelName . '-export-' . date('Ymd-His') . '.csv';
        $filename = strtolower(str_replace(' ', '-', $filename));

        CSVExporter::download($filename, $records, $headers);
        exit;
    }
}
