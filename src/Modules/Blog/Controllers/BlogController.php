<?php
/**
 * Zero CMS - Blog Landing Page Controller
 *
 * This controller manages loading, paginating, and rendering blog publication posts
 * on the main blog index page, supporting dynamic page builder block layout structures.
 *
 * PHP version 8.3
 *
 * @package    Zero\Modules\Blog\Controllers
 * @author     Zero CMS Team
 * @copyright  2026 Zero CMS
 */

namespace Zero\Modules\Blog\Controllers;

use Zero\Core\App;
use Zero\Modules\Blog\Models\Post;
use Zero\Interfaces\Controller;
use Zero\Models\Traits\SupportsBlocks;

/**
 * Class BlogController
 *
 * Handles rendering the multi-tenant paginated blog posts index grid view.
 */
class BlogController implements Controller
{
    use SupportsBlocks;

    /**
     * Handle the incoming request action to display the blog posts listing page.
     *
     * @param mixed $param The parent Page model record representing this blog index.
     * @return void
     */
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
