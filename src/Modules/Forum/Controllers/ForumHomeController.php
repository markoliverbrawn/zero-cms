<?php

declare(strict_types=1);

/**
 * File: src/Modules/Forum/Controllers/ForumHomeController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Forum\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Forum\Controllers;

use Zero\Core\App;
use Zero\Interfaces\Controller;
use Zero\Modules\Forum\Models\ForumBoard;

/**
 * Class ForumHomeController
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class ForumHomeController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($param)
    {
        App::ensureSession();
        $boards = ForumBoard::all('ORDER BY precedence ASC');
        $user = App::getCurrentUser();

        App::render('forum_index', [
            'boards' => $boards,
            'user' => $user,
            'title' => 'Forums Community Hub'
        ]);
        exit;
    }
}
