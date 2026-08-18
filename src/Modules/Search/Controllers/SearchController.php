<?php

declare(strict_types=1);

/**
 * File: src/Modules/Search/Controllers/SearchController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Search\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Search\Controllers;

use Zero\Core\App;
use Zero\Interfaces\Controller;
use Zero\Modules\Search\Services\SearchService;

/**
 * Class SearchController
 *
 * Handles a public search request, passing the query to the configured search driver and rendering
 * the results in the active theme.
 */
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
        $q = \trim(\strval($_GET['q'] ?? ''));
        $currentPage = isset($_GET['page']) ? \max(1, \intval($_GET['page'])) : 1;
        $perPage = (int)App::getModuleSetting('site-search', 'results_per_page', 10);
        $offset = ($currentPage - 1) * $perPage;

        $searchData = SearchService::search($q, [
            'limit' => $perPage,
            'offset' => $offset
        ]);

        $results = $searchData['results'] ?? [];
        $total = $searchData['total'] ?? 0;
        $totalPages = \max(1, \ceil($total / $perPage));

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
