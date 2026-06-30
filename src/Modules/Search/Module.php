<?php

namespace Zero\Modules\Search;

use Zero\Interfaces\Module as ModuleInterface;
use Zero\Modules\Search\Controllers\SearchController;
use Zero\Modules\Search\SearchService;
use Zero\Core\App;

class Module implements ModuleInterface
{
    public function getDashboardWidgetView(): string|null
    {
        return null;
    }

    public function getId(): string
    {
        return 'site-search';
    }

    public function getMigrationClass(): string|null
    {
        return null;
    }

    public function getRoutes(): array
    {
        return [
            '#^/search$#' => SearchController::class,
        ];
    }

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
