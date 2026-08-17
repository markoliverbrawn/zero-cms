<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Controllers/DashboardController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Admin\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Interfaces\Controller;

/**
 * Class DashboardController
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class DashboardController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($param)
    {
        App::applyAuthMiddleware();
        App::render('admin/dashboard');
        exit;
    }
}
