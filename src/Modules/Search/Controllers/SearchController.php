<?php

namespace Zero\Modules\Search\Controllers;

use Zero\Interfaces\Controller;
use Zero\Core\App;
use Zero\Modules\Search\Services\SearchService;

class SearchController implements Controller
{
    public function handle($param)
    {
        $q = strval($_GET['q'] ?? '');
        $results = SearchService::search($q);

        App::render('search', [
            'results' => $results,
            'q' => $q
        ]);
        exit;
    }
}
