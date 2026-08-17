<?php

declare(strict_types=1);

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
use Zero\Interfaces\Controller;
use Zero\Models\Traits\SupportsBlocks;
use Zero\Modules\Blog\Models\Post;

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
        $currentPage = isset($_GET['page']) ? \max(1, \intval($_GET['page'])) : 1;

        // Leverage the standard Paginates trait of Post model, using the site's configured
        // posts-per-page setting (default 6, visually spectacular for masonry grids)
        $postsPerPage = (int)App::getModuleSetting('blog', 'posts_per_page', 6);
        $pagination = Post::paginate($currentPage, $postsPerPage);

        // Render the dedicated "blog" landing page view
        App::render('blog', [
            'post' => $pageRecord,
            'posts' => $pagination['data'],
            'pagination' => $pagination
        ]);
    }
}
