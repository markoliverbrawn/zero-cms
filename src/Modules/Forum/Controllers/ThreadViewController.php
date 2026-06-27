<?php

namespace Zero\Modules\Forum\Controllers;

use Zero\Interfaces\Controller;
use Zero\Core\App;
use Zero\Modules\Forum\Models\ForumBoard;
use Zero\Modules\Forum\Models\ForumThread;

class ThreadViewController implements Controller
{
    public function handle($param)
    {
        App::ensureSession();
        $siteId = App::getCurrentSiteId();
        $threadSlug = $param[1] ?? '';

        $thread = ForumThread::findBySlug($threadSlug);
        if (!$thread || $thread->site_id !== $siteId) {
            http_response_code(404);
            echo "Forum thread not found.";
            exit;
        }

        // Increment views count dynamically
        $thread->views_count++;
        $thread->save();

        $board = ForumBoard::find($thread->board_id);
        $posts = $thread->getPosts();
        $user = App::getCurrentUser();

        App::render('forum_thread', [
            'board' => $board,
            'thread' => $thread,
            'posts' => $posts,
            'user' => $user,
            'title' => $thread->title
        ]);
        exit;
    }
}
