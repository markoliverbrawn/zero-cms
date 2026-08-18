<?php

declare(strict_types=1);

/**
 * File: src/Modules/Forum/Controllers/ThreadViewController.php
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
 * Class ThreadViewController
 *
 * Renders a thread and its nested replies, resolving the reply tree for display.
 */
class ThreadViewController implements Controller
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
        $threadSlug = $param[1] ?? '';

        $thread = ForumThread::findBySlug($threadSlug);
        if (!$thread || $thread->site_id !== $siteId) {
            \http_response_code(404);
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
