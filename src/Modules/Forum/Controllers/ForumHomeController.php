<?php

namespace Zero\Modules\Forum\Controllers;

use Zero\Interfaces\Controller;
use Zero\Core\App;
use Zero\Modules\Forum\Models\ForumBoard;

class ForumHomeController implements Controller
{
    public function handle($param)
    {
        App::ensureSession();
        $boards = ForumBoard::all('ORDER BY precedence ASC');
        $user = App::getCurrentUser();

        App::render('forum_index', [
            'boards' => $boards,
            'user' => $user,
            'title' => 'Forums Community Hub'
        ]);
        exit;
    }
}
