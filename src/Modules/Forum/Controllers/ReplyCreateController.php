<?php

declare(strict_types=1);

/**
 * File: src/Modules/Forum/Controllers/ReplyCreateController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Forum\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Forum\Controllers;

use Zero\Core\App;
use Zero\Core\Validator;
use Zero\Interfaces\Controller;
use Zero\Modules\Forum\Models\ForumPost;
use Zero\Modules\Forum\Models\ForumThread;
use Zero\Support\Security;

/**
 * Class ReplyCreateController
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class ReplyCreateController implements Controller
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

        // Lock check: block replies if thread is locked
        if ($thread->status === 'locked') {
            \http_response_code(403);
            echo "This thread is locked and cannot receive replies.";
            exit;
        }

        $user = App::getCurrentUser();
        if (!$user) {
            $_SESSION['redirect_to'] = "/forum/thread/{$thread->slug}";
            \header('Location: /login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            \header("Location: /forum/thread/{$thread->slug}");
            exit;
        }

        App::applyCsrfMiddleware();
        App::applyRateLimitMiddleware('forum_reply', 5);

        $rules = [
            'content' => 'required|min:5'
        ];

        $validator = new Validator($_POST, $rules);

        if (!$validator->validate()) {
            $_SESSION['reply_error_msg'] = 'Your reply must be at least 5 characters long.';
            $parentId = $_POST['parent_id'] ?? null;
            if (!empty($parentId)) {
                $_SESSION['reply_parent_id'] = $parentId;
                $_SESSION['reply_content_draft'] = $_POST['content'] ?? '';
            } else {
                $_SESSION['reply_quick_content_draft'] = $_POST['content'] ?? '';
            }
            \header("Location: /forum/thread/{$thread->slug}");
            exit;
        }

        $validated = $validator->getValidatedData();
        $parentId = $_POST['parent_id'] ?? null;
        if (empty($parentId)) {
            $parentId = null;
        }

        // If parent_id is set, verify that the parent post actually exists and belongs to this thread
        if ($parentId !== null) {
            $parentPost = ForumPost::find($parentId);
            if (!$parentPost || $parentPost->thread_id !== $thread->id) {
                \http_response_code(400);
                echo "Invalid parent post for nesting.";
                exit;
            }
        }

        $postId = Security::uuidv7();
        $post = new ForumPost([
            'id' => $postId,
            'site_id' => $siteId,
            'thread_id' => $thread->id,
            'user_id' => $user->id,
            'content' => $validated['content'],
            'parent_id' => $parentId,
            'status' => 'approved'
        ]);
        $post->save();

        \header("Location: /forum/thread/{$thread->slug}");
        exit;
    }
}
