<?php

namespace Zero\Modules\Search\Controllers;

use Zero\Interfaces\Controller;
use Zero\Core\App;
use Zero\Modules\Search\Services\SearchService;

class SearchController implements Controller
{
    /**
     * Process site-wide global search request with index pagination.
     *
     * @param mixed $param Custom parameter from Router
     * @return void
     */
    public function handle($param)
    {
        $q = trim(strval($_GET['q'] ?? ''));
        $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $perPage = 10;
        $offset = ($currentPage - 1) * $perPage;

        $searchData = SearchService::search($q, [
            'limit' => $perPage,
            'offset' => $offset
        ]);

        $results = $searchData['results'] ?? [];
        $total = $searchData['total'] ?? 0;
        $totalPages = max(1, ceil($total / $perPage));

        App::render('search', [
            'results' => $results,
            'q' => $q,
            'pagination' => [
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'totalItems' => $total,
                'perPage' => $perPage
            ]
        ]);
        exit;
    }
}
