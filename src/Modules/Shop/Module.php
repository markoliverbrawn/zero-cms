<?php

namespace Zero\Modules\Shop;

use Zero\Core\App;
use Zero\Interfaces\Module as ModuleInterface;
use Zero\Modules\Admin\Controllers\FrontendLoginController;
use Zero\Modules\Admin\Controllers\FrontendForgotController;
use Zero\Modules\Admin\Controllers\FrontendResetController;
use Zero\Modules\Admin\Controllers\RegisterController;
use Zero\Modules\Shop\Controllers\AccountController;
use Zero\Modules\Shop\Controllers\CatalogController;
use Zero\Modules\Shop\Controllers\ProductViewController;
use Zero\Modules\Shop\Controllers\CartController;
use Zero\Modules\Shop\Controllers\CheckoutController;
use Zero\Modules\Shop\Controllers\SuccessController;
use Zero\Modules\Shop\Controllers\Api\ProductsController;
use Zero\Modules\Shop\Controllers\Api\CategoriesController;
use Zero\Modules\Shop\Database\Migration;
use Zero\Modules\Shop\Models\Category;
use Zero\Modules\Shop\Models\Order;
use Zero\Modules\Shop\Models\Product;
use Zero\Modules\Shop\Models\ProductVariant;
use Zero\Modules\Search\Services\SearchService;

class Module implements ModuleInterface
{
    public function getAccentColor(): string
    {
        return '#f59e0b';
    }

    public function getDashboardWidgetView(): ?string
    {
        return 'dashboard-widget';
    }

    
    public function getId(): string
    {
        return 'shop';
    }

    

    public function getMigrationClass(): ?string
    {
        return Migration::class;
    }

    

    public function getRoutes(): array
    {
        return [
            '#^/login$#' => FrontendLoginController::class,
            '#^/register$#' => RegisterController::class,
            '#^/forgot$#' => FrontendForgotController::class,
            '#^/reset$#' => FrontendResetController::class,
            '#^/shop/account$#' => AccountController::class,
            '#^/shop/catalog$#' => CatalogController::class,
            '#^/shop/product/([a-zA-Z0-9\-]+)$#' => ProductViewController::class,
            '#^/shop/cart$#' => CartController::class,
            '#^/shop/checkout$#' => CheckoutController::class,
            '#^/shop/success$#' => SuccessController::class,
            '#^/api/v1/products(?:/(.*))?$#' => ProductsController::class,
            '#^/api/v1/categories(?:/(.*))?$#' => CategoriesController::class
        ];
    }

    

    public function init()
    {
        App::registerThemeFallback('shop');

        App::registerModel('products', Product::class);
        App::registerModel('productvariants', ProductVariant::class);
        App::registerModel('orders', Order::class);
        App::registerModel('categories', Category::class);

        App::registerBlock('categories', [
            'label' => 'Product Categories Grid',
            'description' => 'Showcases all dynamic product categories with their respective imagery and collection links.',
            'icon' => 'shop',
            'admin_view' => dirname(__FILE__) . '/Views/blocks/admin/categories.php',
            'frontend_view' => dirname(__FILE__) . '/Views/blocks/frontend/categories.php'
        ]);

        if (class_exists(SearchService::class)) {
            SearchService::register(Product::class, [
                'type_label' => 'Product',
                'search_fields' => ['title', 'description', 'sku'],
                'title_field' => 'title',
                'content_field' => 'description',
                'status_field' => 'status'
            ]);
        }
    }
}
