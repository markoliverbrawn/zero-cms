<?php
/**
 * File: src/Modules/Admin/Controllers/RedirectController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Admin\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */



namespace Zero\Modules\Admin\Controllers;

use Zero\Interfaces\Controller;

/**
 * Class RedirectController
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class RedirectController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
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
