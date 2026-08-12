<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Controllers/Api/ModelApiController.php
 * Architectural Purpose: Generic REST API endpoint for any registered model (create/edit/delete/
 * reorder/cascade-check), driven entirely by App's model registry (App::getModelClass()) so it
 * works for every model without per-model controllers.
 * Package: Zero\Modules\Admin\Controllers\Api
 */

namespace Zero\Modules\Admin\Controllers\Api;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Models\Traits\IsOrderable;
use Zero\Support\Logger;

/**
 * Class ModelApiController
 */
class ModelApiController extends AdminApiControllerBase
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $matches Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($matches)
    {
        $this->authenticate();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = \parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $body = $this->parseBody();

        // Route: /api/v1/admin/models/([a-zA-Z0-9_-]+)/?$ (POST/create)
        if (\preg_match('#^/api/v1/admin/models/([a-zA-Z0-9_-]+)/?$#', $uri, $routeMatches)) {
            $modelName = $routeMatches[1];
            if ($method === 'POST') {
                $this->handleSaveModel($modelName, null, $body);
            }
        }

        // Route: /api/v1/admin/models/([a-zA-Z0-9_-]+)/reorder (POST)
        if (\preg_match('#^/api/v1/admin/models/([a-zA-Z0-9_-]+)/reorder/?$#', $uri, $routeMatches)) {
            $modelName = $routeMatches[1];
            if ($method === 'POST') {
                $this->handleReorderModel($modelName, $body);
            }
        }

        // Route: /api/v1/admin/models/([a-zA-Z0-9_-]+)/([a-zA-Z0-9\-]+)/cascade-check (GET)
        if (\preg_match('#^/api/v1/admin/models/([a-zA-Z0-9_-]+)/([a-zA-Z0-9\-]+)/cascade-check$#', $uri, $routeMatches)) {
            $modelName = $routeMatches[1];
            $id = $routeMatches[2];
            if ($method === 'GET') {
                $this->handleCascadeCheck($modelName, $id);
            }
        }

        // Route: /api/v1/admin/models/([a-zA-Z0-9_-]+)/([a-zA-Z0-9\-]+) (PATCH/edit or DELETE/delete)
        if (\preg_match('#^/api/v1/admin/models/([a-zA-Z0-9_-]+)/([a-zA-Z0-9\-]+)$#', $uri, $routeMatches)) {
            $modelName = $routeMatches[1];
            $id = $routeMatches[2];

            if ($method === 'DELETE') {
                $this->handleDeleteModel($modelName, $id);
            } elseif ($method === 'PATCH' || $method === 'POST') {
                $this->handleSaveModel($modelName, $id, $body);
            }
        }

        $this->respond(['success' => false, 'error' => 'Endpoint not found or method not allowed'], 404);
    }

    /**
     * Eager on-demand calculation of cascading deletes impact details for a target record ID.
     */
    protected function handleCascadeCheck($modelName, $id)
    {
        $model = App::getModelClass($modelName);
        if (!$model) {
            $this->respond(['success' => false, 'error' => 'Invalid model Name'], 400);
        }

        $record = $model::find($id);
        if (!$record && \method_exists($model, 'findTrashed')) {
            $record = $model::findTrashed($id);
        }
        if (!$record) {
            $this->respond(['success' => false, 'error' => 'Record not found'], 404);
        }

        // Check if model uses CascadesDeletes trait or has getCascadeDeletes method
        $cascadeModels = [];
        if (\method_exists($record, 'getCascadeDeletes')) {
            $cascadeModels = $record->getCascadeDeletes();
        } elseif (\property_exists($model, 'cascadeDeletes')) {
            try {
                $reflector = new \ReflectionClass($model);
                if ($reflector->hasProperty('cascadeDeletes')) {
                    $prop = $reflector->getProperty('cascadeDeletes');
                    $prop->setAccessible(true);
                    $cascadeModels = $prop->getValue();
                }
            } catch (\Exception $e) {
                // Fail-safe fallback
            }
        }

        $labels = [];
        if (!empty($cascadeModels)) {
            foreach ($cascadeModels as $childClass => $foreignKey) {
                if (\class_exists($childClass)) {
                    try {
                        $reflector = new \ReflectionClass($childClass);
                        $prop = $reflector->getProperty('tableName');
                        $prop->setAccessible(true);
                        $childTable = $prop->getValue();

                        // Defense-in-depth: $childTable/$foreignKey come from hardcoded
                        // $cascadeDeletes class metadata, never from request input, but table/
                        // column identifiers can't be bound via PDO placeholders -- so validate
                        // both are plain SQL identifiers before interpolating them.
                        if (!\preg_match('/^[a-zA-Z0-9_]+$/', $childTable) || !\preg_match('/^[a-zA-Z0-9_]+$/', $foreignKey)) {
                            continue;
                        }

                        // Count matching child records that are not soft-deleted
                        $count = (int)DB::query("
                            SELECT COUNT(*) FROM {$childTable}
                            WHERE {$foreignKey} = ? AND deleted_at IS NULL
                        ", [$id])->fetchColumn();

                        if ($count > 0) {
                            $shortName = $reflector->getShortName();
                            $labels[] = "• " . $count . " " . ($count === 1 ? $shortName : $shortName . "s");
                        }
                    } catch (\Exception $e) {
                        // Safe fallback
                    }
                }
            }
        }

        $details = '';
        if (!empty($labels)) {
            $details = "Soft deleting this record will automatically cascade into:\n" . \implode("\n", $labels);
        }

        $this->respond([
            'success' => true,
            'details' => $details,
            'has_cascade' => !empty($labels)
        ]);
    }

    /**
     * Handle delete model processing implementation helper.
     *
     * @param mixed $modelName Argument descriptor.
     * @param mixed $id Argument descriptor.
     * @return mixed Response output.
     */
    protected function handleDeleteModel($modelName, $id)
    {
        $model = App::getModelClass($modelName);

        if (!$model) {
            $this->respond(['success' => false, 'error' => 'Invalid model name'], 400);
        }

        // Apply Super Admin middleware protection for highly sensitive tables or force-deletes
        $isForce = isset($_GET['force']) && $_GET['force'] === 'true';
        if ($modelName === 'users' || $modelName === 'sites' || $isForce) {
            App::applyRoleMiddleware('super_admin');
        }

        $record = $isForce ? $model::findTrashed($id) : $model::find($id);
        if ($record) {
            try {
                if ($isForce) {
                    $record->forceDelete();
                    Logger::log($_SESSION['user_id'] ?? null, 'force_delete', $modelName, $id, [
                        'title' => $record->title ?? ($record->filename ?? ($record->username ?? ''))
                    ]);
                } else {
                    $record->delete();
                    Logger::log($_SESSION['user_id'] ?? null, 'delete', $modelName, $id, [
                        'title' => $record->title ?? ($record->filename ?? ($record->username ?? ''))
                    ]);
                }
                $this->respond(['success' => true]);
            } catch (\Exception $e) {
                $this->respond(['success' => false, 'error' => $e->getMessage()], 500);
            }
        }

        $this->respond(['success' => false, 'error' => 'Record not found or already deleted'], 404);
    }

    /**
     * Handle reorder model processing implementation helper.
     *
     * @param mixed $modelName Argument descriptor.
     * @param mixed $body Argument descriptor.
     * @return mixed Response output.
     */
    protected function handleReorderModel($modelName, $body)
    {
        $model = App::getModelClass($modelName);

        if (!$model) {
            $this->respond(['success' => false, 'error' => 'Invalid model name'], 400);
        }

        // Apply Super Admin middleware protection for highly sensitive tables
        if ($modelName === 'users' || $modelName === 'sites') {
            App::applyRoleMiddleware('super_admin');
        }

        // Check if model has IsOrderable trait or supports reordering
        $traits = \class_uses($model);
        $isOrderable = isset($traits[IsOrderable::class]) || (\method_exists($model, 'isOrderable') && $model::isOrderable());

        if (!$isOrderable) {
            $this->respond(['success' => false, 'error' => 'Model is not orderable'], 400);
        }

        $ids = $body['ids'] ?? [];
        if (!\is_array($ids)) {
            $this->respond(['success' => false, 'error' => 'Invalid or missing ids array'], 400);
        }

        // Perform reordering!
        $model::reorder($ids);

        Logger::log($_SESSION['user_id'] ?? null, 'update', $modelName, null, [
            'title' => 'Reordered list of ' . $modelName
        ]);

        $this->respond(['success' => true]);
    }

    /**
     * Handle save model processing implementation helper.
     *
     * @param mixed $modelName Argument descriptor.
     * @param mixed $id Argument descriptor.
     * @param mixed $body Argument descriptor.
     * @return mixed Response output.
     */
    protected function handleSaveModel($modelName, $id, $body)
    {
        $model = App::getModelClass($modelName);

        if (!$model) {
            $this->respond(['success' => false, 'error' => 'Invalid model name'], 400);
        }

        // Apply Super Admin middleware protection for highly sensitive tables
        if ($modelName === 'users' || $modelName === 'sites') {
            App::applyRoleMiddleware('super_admin');
        }

        $config = $model::getConfig();
        $data = [];
        foreach ($config as $field => $fieldConfig) {
            if ($fieldConfig['editable'] ?? false) {
                $val = $body[$field] ?? '';
                // Automatically json_encode array values (such as enabled_modules checkbox arrays!)
                if (\is_array($val)) {
                    $val = \json_encode($val);
                }
                $data[$field] = $val;
            }
        }

        // Auto-generate slug if the model has a slug property and title is set (bypassed for pages which compiles slugs hierarchically)
        if ($modelName !== 'pages' && \property_exists($model, 'slug') && isset($data['title'])) {
            $inputSlug = $data['slug'] ?? '';
            if (empty($inputSlug)) {
                $data['slug'] = App::slugify($data['title']);
            } else {
                $data['slug'] = App::slugifyPath($inputSlug);
            }
        }

        if ($id && $id !== 'new') {
            // Edit existing record
            $record = $model::find($id);
            if (!$record) {
                $this->respond(['success' => false, 'error' => 'Record not found'], 404);
            }
            foreach ($data as $key => $value) {
                $record->$key = $value;
            }
            $record->save();
            Logger::log($_SESSION['user_id'] ?? null, 'update', $modelName, $id, [
                'title' => $data['title'] ?? ($data['filename'] ?? ($data['username'] ?? ''))
            ]);

            $this->respond([
                'success' => true,
                'id' => $id
            ]);
        } else {
            // Create new record
            $record = new $model($data);
            $newId = $record->save();

            Logger::log($_SESSION['user_id'] ?? null, 'create', $modelName, $newId, [
                'title' => $data['title'] ?? ($data['filename'] ?? ($data['username'] ?? ''))
            ]);

            $this->respond([
                'success' => true,
                'id' => $newId
            ]);
        }
    }
}
