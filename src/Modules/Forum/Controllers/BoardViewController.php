<?php

declare(strict_types=1);

/**
 * File: src/Modules/Forum/Controllers/BoardViewController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Forum\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Forum\Controllers;

use Zero\Core\App;
use Zero\Interfaces\Controller;
use Zero\Modules\Forum\Models\ForumBoard;
use Zero\Modules\Forum\Models\ForumThread;

/**
 * Class BoardViewController
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class BoardViewController implements Controller
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
        $siteId = App::getCurrentSiteId();
        $boardSlug = $param[1] ?? '';

        $board = ForumBoard::findBySlug($boardSlug);
        if (!$board || $board->site_id !== $siteId) {
            \http_response_code(404);
            echo "Forum board not found.";
            exit;
        }

        // Fetch threads with author and replies count eager loaded using JOINs
        $threads = ForumThread::getBoardThreads($board->id);
        $user = App::getCurrentUser();

        App::render('forum_board', [
            'board' => $board,
            'threads' => $threads,
            'user' => $user,
            'title' => $board->title
        ]);
        exit;
    }
}
