<?php
/**
 * File: src/Modules/Admin/Controllers/LogoutController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Admin\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Support\Logger;
use Zero\Interfaces\Controller;

/**
 * Class LogoutController
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class LogoutController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
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
