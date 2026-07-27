<?php

namespace Zero\Modules\Blog\Controllers;

use Zero\Core\App;
use Zero\Modules\Blog\Models\Post;
use Zero\Interfaces\Controller;

class PostViewController implements Controller
{
    public function handle($param)
    {
        $matches = $param;
        $slug = $matches[1];
        $post = Post::findBySlug($slug);
        if (!$post) {
            http_response_code(404);
            echo "Post not found.";
            exit;
        }
        App::render('post', ['post' => $post]);
        exit;
    }
}
