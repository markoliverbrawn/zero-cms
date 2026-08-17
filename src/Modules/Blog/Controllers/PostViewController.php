<?php

declare(strict_types=1);

/**
 * File: src/Modules/Blog/Controllers/PostViewController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Blog\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Blog\Controllers;

use Zero\Core\App;
use Zero\Interfaces\Controller;
use Zero\Modules\Blog\Models\Post;

/**
 * Class PostViewController
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class PostViewController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($param)
    {
        $matches = $param;
        $slug = $matches[1];
        $post = Post::findBySlug($slug);
        if (!$post) {
            \http_response_code(404);
            echo "Post not found.";
            exit;
        }
        App::render('post', ['post' => $post]);
        exit;
    }
}
