<?php

declare(strict_types=1);

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
 * Catch-all at the end of the admin route table, forwarding legacy or shorthand /admin/{something}
 * URLs to their current destination so older links and back-buttons keep working.
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
        if (\in_array($modelName, ['files', 'pages', 'posts', 'users'])) {
            \header('Location: /admin/list/' . $modelName);
            exit;
        }
    }
}
