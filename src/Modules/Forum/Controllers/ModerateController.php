<?php

declare(strict_types=1);

/**
 * File: src/Modules/Forum/Controllers/ModerateController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Forum\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Forum\Controllers;

use Zero\Core\App;
use Zero\Interfaces\Controller;
use Zero\Modules\Forum\Models\ForumPost;
use Zero\Modules\Forum\Models\ForumThread;

/**
 * Class ModerateController
 *
 * Applies moderation decisions to forum threads and posts from the back office, changing the
 * status that determines whether an item is publicly visible.
 */
class ModerateController implements Controller
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

        $user = App::getCurrentUser();
        $isModerator = $user && ($user->role === 'super_admin' || $user->role === 'editor');

        if (!$isModerator) {
            \http_response_code(403);
            echo "Access Denied: You do not possess moderation privileges.";
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            \header("Location: /forum/thread/{$thread->slug}");
            exit;
        }

        App::applyCsrfMiddleware();
        $action = $_POST['action'] ?? '';

        if ($action === 'lock') {
            $thread->status = ($thread->status === 'locked') ? 'published' : 'locked';
            $thread->save();
        } elseif ($action === 'pin') {
            $thread->status = ($thread->status === 'pinned') ? 'published' : 'pinned';
            $thread->save();
        } elseif ($action === 'delete_post') {
            $postId = $_POST['post_id'] ?? '';
            if (!empty($postId)) {
                $post = ForumPost::find($postId);
                // Confirm post belongs to this thread and active tenant site
                if ($post && $post->thread_id === $thread->id && $post->site_id === $siteId) {
                    $post->delete(); // Soft delete via ActiveRecord IsModel delete()!
                }
            }
        }

        \header("Location: /forum/thread/{$thread->slug}");
        exit;
    }
}
