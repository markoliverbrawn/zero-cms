<?php

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Interfaces\Controller;

class DashboardController implements Controller
{
    public function handle($param)
    {
        App::applyAuthMiddleware();
        App::render('admin/dashboard');
        exit;
    }
}
