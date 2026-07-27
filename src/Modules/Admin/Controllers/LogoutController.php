<?php

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Support\Logger;
use Zero\Interfaces\Controller;

class LogoutController implements Controller
{
    public function handle($param)
    {
        $method = $_SERVER['REQUEST_METHOD'];
        if ($method !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed';
            exit;
        }
        App::applyCsrfMiddleware();
        App::logoutUser();
        Logger::log($_SESSION['user_id'] ?? null, 'logout', 'user', $_SESSION['user_id'] ?? null, null);
        header('Location: /admin/login');
        exit;
    }
}
