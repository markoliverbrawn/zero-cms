<?php

namespace Zero\Modules\Blog\Controllers;

use Zero\Core\App;
use Zero\Modules\Blog\Models\Post;
use Zero\Interfaces\Controller;

class BlogController implements Controller
{
    public function handle($param)
    {
        $pageRecord = $param;

        // Load current page from query string (default to 1)
        $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

        // Leverage the standard Paginates trait of Post model
        // 6 posts per page is visually spectacular for masonry grids or vertical card flows
        $pagination = Post::paginate($currentPage, 6);

        // Render the dedicated "blog" landing page view
        App::render('blog', [
            'post' => $pageRecord,
            'posts' => $pagination['data'],
            'pagination' => $pagination
        ]);
    }
}
