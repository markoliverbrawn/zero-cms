<?php

declare(strict_types=1);

/**
 * File: src/Modules/Search/Module.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Search
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Search;

use Zero\Core\App;
use Zero\Interfaces\Module as ModuleInterface;
use Zero\Modules\Search\Controllers\SearchController;
use Zero\Modules\Search\Services\SearchService;

/**
 * Class Module
 *
 * Module contract implementation for the Search module: the index and its driver, the public
 * search route, the search block, and back-office reindexing.
 */
class Module implements ModuleInterface
{
    /**
     * Get the brand accent color associated with this module.
     *
     * @return string
     */
    public function getAccentColor(): string
    {
        return '#f43f5e';
    }

    /**
     * Get the relative view path of this module's dashboard widget.
     *
     * @return string|null
     */
    public function getDashboardWidgetView(): string|null
    {
        return 'search_widget';
    }

    /**
     * Get the unique ID of this module.
     *
     * @return string
     */
    public function getId(): string
    {
        return 'site-search';
    }

    /**
     * Get the Migration class associated with this module if any.
     *
     * @return string|null
     */
    public function getMigrationClass(): string|null
    {
        return null;
    }

    /**
     * Get the route mappings of this module.
     *
     * @return array
     */
    public function getRoutes(): array
    {
        return [
            '#^/search$#' => SearchController::class,
            '#^/api/v1/admin/search/reindex/start$#' => \Zero\Modules\Search\Controllers\SearchReindexController::class,
            '#^/api/v1/admin/search/reindex/batch$#' => \Zero\Modules\Search\Controllers\SearchReindexController::class,
        ];
    }

    /**
     * Bootstrapping initializations for this module.
     * Registers default core searchables.
     *
     * @return void
     */
    public function init()
    {
        // Folded into the compiled theme bundle rather than linked from the search view. A <link>
        // emitted mid-body cost an extra blocking request on every search, could not be cached
        // immutably, and sat after the theme in the cascade -- so the module's own base styles
        // outranked the theme that should have been able to restyle them. Registered here, it is
        // concatenated only for sites with this module enabled, ahead of the theme.
        App::registerModuleStylesheet('site-search', APPLICATION_ROOT . '/public/assets/css/search.css');

        App::registerModuleSettings('site-search', [
            'results_per_page' => [
                'type' => 'number',
                'label' => 'Search Results Per Page',
                'default' => 10,
                'required' => true,
                'min' => 1,
                'helper_text' => 'Number of results shown per page on the site search results screen.'
            ]
        ]);

        // Register core Pages searchable
        SearchService::register(\Zero\Models\Page::class, [
            'type_label' => 'Page',
            'search_fields' => ['title', 'content', 'summary'],
            'title_field' => 'title',
            'content_field' => 'content',
            'status_field' => 'status'
        ]);
    }
}
