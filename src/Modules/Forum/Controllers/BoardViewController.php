<?php

namespace Zero\Modules\Forum\Controllers;

use Zero\Interfaces\Controller;
use Zero\Core\App;
use Zero\Modules\Forum\Models\ForumBoard;
use Zero\Modules\Forum\Models\ForumThread;

class BoardViewController implements Controller
{
    public function handle($param)
    {
        App::ensureSession();
        $siteId = App::getCurrentSiteId();
        $boardSlug = $param[1] ?? '';

        $board = ForumBoard::findBySlug($boardSlug);
        if (!$board || $board->site_id !== $siteId) {
            http_response_code(404);
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
