<?php
/**
 * File: src/Modules/Blog/Controllers/Api/PostsController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Blog\Controllers\Api
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */



namespace Zero\Modules\Blog\Controllers\Api;

use Zero\Http\Controllers\ApiController;
use Zero\Modules\Blog\Models\Post;

/**
 * Class PostsController
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class PostsController extends ApiController
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $matches Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($matches)
    {
        // 1. Authenticate Request
        $user = $this->authenticate();

        $param = $matches[1] ?? '';

        if (empty($param)) {
            $posts = Post::all();
            $output = [];
            foreach ($posts as $post) {
                $output[] = [
                    'id' => $post->id,
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'status' => $post->status,
                    'created_at' => $post->created_at
                ];
            }
            $this->respond([
                'success' => true,
                'total' => count($output),
                'posts' => $output
            ]);
        } else {
            $post = null;
            if (strlen($param) === 36) {
                $post = Post::find($param);
            }
            if (!$post) {
                $post = Post::findBySlug($param);
            }

            if (!$post) {
                $this->respond([
                    'success' => false,
                    'error' => 'Post not found'
                ], 404);
            }

            $this->respond([
                'success' => true,
                'post' => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'content' => $post->content,
                    'status' => $post->status,
                    'created_at' => $post->created_at,
                    'updated_at' => $post->updated_at
                ]
            ]);
        }
    }
}
