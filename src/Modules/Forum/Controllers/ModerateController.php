<?php

namespace Zero\Modules\Forum\Controllers;

use Zero\Interfaces\Controller;
use Zero\Core\App;
use Zero\Modules\Forum\Models\ForumThread;
use Zero\Modules\Forum\Models\ForumPost;

class ModerateController implements Controller
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

        $user = App::getCurrentUser();
        $isModerator = $user && ($user->role === 'super_admin' || $user->role === 'editor');

        if (!$isModerator) {
            http_response_code(403);
            echo "Access Denied: You do not possess moderation privileges.";
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /forum/thread/{$thread->slug}");
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

        header("Location: /forum/thread/{$thread->slug}");
        exit;
    }
}
