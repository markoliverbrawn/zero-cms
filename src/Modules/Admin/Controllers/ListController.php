<?php

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Interfaces\Controller;
use Exception;
use Zero\Models\Traits\IsOrderable;
use Zero\Models\User;

class ListController implements Controller
{
    public function handle($param)
    {
        $matches = $param;
        App::applyAuthMiddleware();
        $modelName = $matches[1];

        // Enforce Role-Based Access Control (RBAC) security checks
        if ($modelName === 'users' || $modelName === 'sites') {
            App::applyRoleMiddleware('super_admin');
        }

        $model = App::getModelClass($modelName);
        if (!$model) {
            throw new Exception('Invalid model class');
        }
        
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        
        // Load default pagination limit from user preferences
        $userId = $_SESSION['user_id'] ?? null;
        $perPage = 20;
        if ($userId) {
            $userPrefs = User::getPreferencesForUser($userId);
            $perPage = $userPrefs['per_page'] ?? 20;
        }

        // Check if model has IsOrderable trait or supports reordering
        $traits = class_uses($model);
        $isOrderable = isset($traits[IsOrderable::class]) || (method_exists($model, 'isOrderable') && $model::isOrderable());
        
        $sort = $_GET['sort'] ?? '';
        $defaultOrder = $isOrderable ? 'asc' : 'desc';
        $order = strtolower($_GET['order'] ?? $defaultOrder);
        if (!in_array($order, ['asc', 'desc'])) {
            $order = $defaultOrder;
        }

        $config = $model::getConfig();
        // Fallback to precedence if no sort or invalid column is supplied and model is orderable
        if (empty($sort) || !array_key_exists($sort, $config)) {
            $sort = $isOrderable ? 'precedence' : 'created_at';
        }

        $orderBy = "{$sort} {$order}";
        $filters = [
            'q' => $_GET['q'] ?? '',
            'trash' => ($_GET['status'] ?? '') === 'trash'
        ];
        $paginationData = $model::paginate($page, $perPage, $filters, $orderBy);

        App::render('admin/model/list', [ 
            'modelName' => $modelName,
            'records' => $paginationData['data'],
            'page' => $paginationData['currentPage'],
            'range' => range(1, $paginationData['totalPages']),
            'pages' => $paginationData['totalPages'],
            'q' => $paginationData['query'] ?? '',
            'sort' => $sort,
            'order' => $order,
            'config' => $config,
            'status' => $_GET['status'] ?? 'active',
            'isOrderable' => $isOrderable,
        ]);
        exit;
    }
}
