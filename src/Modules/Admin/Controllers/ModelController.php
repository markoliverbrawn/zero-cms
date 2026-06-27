<?php

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Support\Logger;
use Zero\Interfaces\Controller;

class ModelController implements Controller
{
    public function handle($param)
    {
        $matches = $param;
        App::applyAuthMiddleware();
        
        $modelName = $matches[1];
        
        // Distinguish action and ID based on restful path variables
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $isDelete = (strpos($uri, '/admin/delete/') === 0);
        $isRestore = (strpos($uri, '/admin/restore/') === 0);
        $isForceDelete = (strpos($uri, '/admin/force-delete/') === 0);

        if ($isDelete) {
            $action = 'delete';
            $id = $_POST['id'] ?? null;
        } elseif ($isRestore) {
            $action = 'restore';
            $id = $matches[2];
        } elseif ($isForceDelete) {
            $action = 'force-delete';
            $id = $matches[2];
        } else {
            $id = $matches[2]; // Can be UUIDv7 or the literal word 'new'
            $action = ($id === 'new') ? 'new' : 'edit';
        }

        // Enforce Role-Based Access Control (RBAC) security checks
        if ($modelName === 'users' || $modelName === 'sites') {
            App::applyRoleMiddleware('super_admin');
        }

        $model = App::getModelClass($modelName);

        if (!$model) {
            http_response_code(404);
            echo "Invalid model";
            exit;
        }

        $method = $_SERVER['REQUEST_METHOD'];

        if ($action === 'delete') {
            if ($method !== 'POST') {
                http_response_code(405);
                echo "Method not allowed";
                exit;
            }
            App::applyCsrfMiddleware();
            
            if ($id) {
                $record = $model::find($id);
                if ($record) {
                    $record->delete();
                    Logger::log($_SESSION['user_id'] ?? null, 'delete', $modelName, $id, [
                        'title' => $record->title ?? ($record->filename ?? ($record->username ?? ''))
                    ]);
                }
            }
            header('Location: /admin/list/' . $modelName);
            exit;
        }

        if ($action === 'restore') {
            if ($id) {
                $record = $model::findTrashed($id);
                if ($record) {
                    $record->restore();
                    Logger::log($_SESSION['user_id'] ?? null, 'restore', $modelName, $id, [
                        'title' => $record->title ?? ($record->filename ?? ($record->username ?? ''))
                    ]);
                }
            }
            header('Location: /admin/list/' . $modelName . '?status=trash');
            exit;
        }

        if ($action === 'force-delete') {
            if ($id) {
                $record = $model::findTrashed($id);
                if ($record) {
                    $record->forceDelete();
                    Logger::log($_SESSION['user_id'] ?? null, 'force_delete', $modelName, $id, [
                        'title' => $record->title ?? ($record->filename ?? ($record->username ?? ''))
                    ]);
                }
            }
            header('Location: /admin/list/' . $modelName . '?status=trash');
            exit;
        }

        if ($method === 'POST') {
            App::applyCsrfMiddleware();
            $formId = $_POST['id'] ?? null;
            $config = $model::getConfig();
            $data = [];
            foreach ($config as $field => $fieldConfig) {
                if ($fieldConfig['editable'] ?? false) {
                    $val = $_POST[$field] ?? '';
                    // Automatically json_encode array values (such as enabled_modules checkbox arrays!)
                    if (is_array($val)) {
                        $val = json_encode($val);
                    }
                    $data[$field] = $val;
                }
            }

            // Auto-generate slug if the model has a slug property and title is set
            if (property_exists($model, 'slug') && isset($data['title'])) {
                $inputSlug = $_POST['slug'] ?? '';
                if (empty($inputSlug)) {
                    $data['slug'] = App::slugify($data['title']);
                } else {
                    $data['slug'] = App::slugifyPath($inputSlug);
                }
            }

            $submitAction = $_POST['submit_action'] ?? 'save_return';
            $targetId = ($action === 'edit') ? $id : $formId;

            if ($action === 'edit' && $id) {
                $record = $model::find($id);
                if ($record) {
                    foreach ($data as $key => $value) {
                        $record->$key = $value;
                    }
                    $record->save();
                    Logger::log($_SESSION['user_id'] ?? null, 'update', $modelName, $id, [
                        'title' => $data['title'] ?? ($data['filename'] ?? ($data['username'] ?? ''))
                    ]);
                }
            } else {
                $record = new $model($data);
                $newId = $record->save();
                $targetId = $newId;
                Logger::log($_SESSION['user_id'] ?? null, 'create', $modelName, $newId, [
                    'title' => $data['title'] ?? ($data['filename'] ?? ($data['username'] ?? ''))
                ]);
            }

            if ($submitAction === 'save_continue' && $targetId) {
                header('Location: /admin/edit/' . $modelName . '/' . $targetId);
            } else {
                header('Location: /admin/list/' . $modelName);
            }
            exit;
        }

        $record = null;
        if ($action === 'edit' && $id) {
            $record = $model::find($id);
        }

        App::render('admin/model/edit', [
            'modelName' => $modelName,
            'record' => $record,
            'config' => $model::getConfig(),
        ]);
        exit;
    }
}
