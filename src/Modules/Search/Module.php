<?php

namespace Zero\Modules\Search;

use Zero\Interfaces\Module as ModuleInterface;
use Zero\Modules\Search\Controllers\SearchController;
use Zero\Modules\Search\Services\SearchService;
use Zero\Core\App;

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
