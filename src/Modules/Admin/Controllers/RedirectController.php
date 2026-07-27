<?php

namespace Zero\Modules\Admin\Controllers;

use Zero\Interfaces\Controller;

class RedirectController implements Controller
{
    public function handle($param)
    {
        $matches = $param;
        $modelName = $matches[1];
        if (in_array($modelName, ['files', 'pages', 'posts', 'users', 'products', 'productvariants', 'orders'])) {
            header('Location: /admin/list/' . $modelName);
            exit;
        }
    }
}
