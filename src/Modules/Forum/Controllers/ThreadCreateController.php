<?php

declare(strict_types=1);

/**
 * File: src/Modules/Forum/Controllers/ThreadCreateController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Forum\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Forum\Controllers;

use Zero\Core\App;
use Zero\Core\Validator;
use Zero\Interfaces\Controller;
use Zero\Modules\Forum\Models\ForumBoard;
use Zero\Modules\Forum\Models\ForumPost;
use Zero\Modules\Forum\Models\ForumThread;
use Zero\Support\Security;

/**
 * Class ThreadCreateController
 *
 * Handles new thread submissions, validating the post and attributing it to the signed-in member.
 */
class ThreadCreateController implements Controller
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

        $user = App::getCurrentUser();
        if (!$user) {
            $_SESSION['redirect_to'] = "/forum/board/{$board->slug}/create";
            \header('Location: /login');
            exit;
        }

        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'POST') {
            App::applyCsrfMiddleware();

            $rules = [
                'title' => 'required|min:5|max:255',
                'content' => 'required|min:10'
            ];

            $validator = new Validator($_POST, $rules);

            if (!$validator->validate()) {
                App::render('forum_thread_create', [
                    'board' => $board,
                    'user' => $user,
                    'errors' => $validator->getErrors(),
                    'title' => 'Create New Thread',
                    'old_title' => $_POST['title'] ?? '',
                    'old_content' => $_POST['content'] ?? ''
                ]);
                exit;
            }

            $validated = $validator->getValidatedData();

            // 1. Create and save the ForumThread
            $threadId = Security::uuidv7();
            $threadSlug = App::slugify($validated['title']);
            
            // Handle duplicate slug collision safely
            $existing = ForumThread::findBySlug($threadSlug);
            if ($existing) {
                $threadSlug .= '-' . \substr(\bin2hex(\random_bytes(3)), 0, 4);
            }

            $thread = new ForumThread([
                'id' => $threadId,
                'site_id' => $siteId,
                'board_id' => $board->id,
                'user_id' => $user->id,
                'title' => $validated['title'],
                'slug' => $threadSlug,
                'status' => 'published',
                'views_count' => 0
            ]);
            $thread->save();

            // 2. Create the original post associated with the thread
            $postId = Security::uuidv7();
            $defaultStatus = App::getModuleSetting('forum', 'default_post_status', 'approved');
            $post = new ForumPost([
                'id' => $postId,
                'site_id' => $siteId,
                'thread_id' => $threadId,
                'user_id' => $user->id,
                'content' => $validated['content'],
                'parent_id' => null,
                'status' => $defaultStatus
            ]);
            $post->save();

            \header("Location: /forum/thread/{$thread->slug}");
            exit;
        }

        App::render('forum_thread_create', [
            'board' => $board,
            'user' => $user,
            'title' => 'Create New Thread'
        ]);
        exit;
    }
}
