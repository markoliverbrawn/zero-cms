<?php
/**
 * File: src/Modules/Shop/Module.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Shop
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */



namespace Zero\Modules\Shop;

use Zero\Core\App;
use Zero\Interfaces\Module as ModuleInterface;
use Zero\Modules\Admin\Controllers\FrontendForgotController;
use Zero\Modules\Admin\Controllers\FrontendLoginController;
use Zero\Modules\Admin\Controllers\FrontendResetController;
use Zero\Modules\Admin\Controllers\RegisterController;
use Zero\Modules\Search\Services\SearchService;
use Zero\Modules\Shop\Controllers\AccountController;
use Zero\Modules\Shop\Controllers\Api\CategoriesController;
use Zero\Modules\Shop\Controllers\Api\ProductsController;
use Zero\Modules\Shop\Controllers\CartController;
use Zero\Modules\Shop\Controllers\CatalogController;
use Zero\Modules\Shop\Controllers\CheckoutController;
use Zero\Modules\Shop\Controllers\ProductViewController;
use Zero\Modules\Shop\Controllers\SuccessController;
use Zero\Modules\Shop\Database\Migration;
use Zero\Modules\Shop\Models\Category;
use Zero\Modules\Shop\Models\Order;
use Zero\Modules\Shop\Models\Product;
use Zero\Modules\Shop\Models\ProductVariant;
use Zero\Support\Seeder;

/**
 * Class Module
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class Module implements ModuleInterface
{
    /**
     * Retrieves the accent color attribute value.
     *
     * @return string Response output.
     */
    public function getAccentColor(): string
    {
        return '#f59e0b';
    }

    /**
     * Retrieves the dashboard widget view attribute value.
     *
     * @return string Response output.
     */
    public function getDashboardWidgetView(): ?string
    {
        return 'dashboard-widget';
    }

    /**
     * Retrieves the id attribute value.
     *
     * @return string Response output.
     */
    public function getId(): string
    {
        return 'shop';
    }

    /**
     * Retrieves the migration class attribute value.
     *
     * @return string Response output.
     */
    public function getMigrationClass(): ?string
    {
        return Migration::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class;
    }

    /**
     * Retrieves the routes attribute value.
     *
     * @return mixed Response output.
     */
    public function getRoutes(): array
    {
        return [
            '#^/login$#' => FrontendLoginController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/register$#' => RegisterController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/forgot$#' => FrontendForgotController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/reset$#' => FrontendResetController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/shop/account$#' => AccountController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/shop/catalog$#' => CatalogController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/shop/product/([a-zA-Z0-9\-]+)$#' => ProductViewController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/shop/cart$#' => CartController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/shop/checkout$#' => CheckoutController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/shop/success$#' => SuccessController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/api/v1/products(?:/(.*))?$#' => ProductsController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/api/v1/categories(?:/(.*))?$#' => CategoriesController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class
        ];
    }

    /**
     * Init processing implementation helper.
     *
     * @return mixed Response output.
     */
    public function init()
    {
        App::registerThemeFallback('shop');

        App::registerModel('products', Product::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class);
        App::registerModel('productvariants', ProductVariant::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class);
        App::registerModel('orders', Order::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class);
        App::registerModel('categories', Category::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class);

        App::registerAdminSidebarSection('shop', [
            'title' => 'Shop Management',
            'icon' => 'shop',
            'module_dependency' => 'shop',
            'precedence' => 200
        ]);

        App::registerAdminSidebarLink('shop', [
            'title' => 'Manage Products',
            'url' => '/admin/list/products',
            'icon' => 'package',
            'module_dependency' => 'shop',
            'precedence' => 10
        ]);

        App::registerAdminSidebarLink('shop', [
            'title' => 'Manage Categories',
            'url' => '/admin/list/categories',
            'icon' => 'tag',
            'module_dependency' => 'shop',
            'precedence' => 20
        ]);

        App::registerAdminSidebarLink('shop', [
            'title' => 'Manage Variants',
            'url' => '/admin/list/productvariants',
            'icon' => 'git-branch',
            'module_dependency' => 'shop',
            'precedence' => 30
        ]);

        App::registerAdminSidebarLink('shop', [
            'title' => 'Manage Orders',
            'url' => '/admin/list/orders',
            'icon' => 'shopping-cart',
            'module_dependency' => 'shop',
            'precedence' => 40
        ]);

        App::registerBlock('categories', [
            'label' => 'Product Categories Grid',
            'description' => 'Showcases all dynamic product categories with their respective imagery and collection links.',
            'icon' => 'shop',
            'admin_view' => dirname(__FILE__) . '/Views/blocks/admin/categories.php',
            'frontend_view' => dirname(__FILE__) . '/Views/blocks/frontend/categories.php'
        ]);

        if (class_exists(SearchService::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class)) {
            SearchService::register(Product::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class, [
                'type_label' => 'Product',
                'search_fields' => ['title', 'description', 'sku'],
                'title_field' => 'title',
                'content_field' => 'description',
                'status_field' => 'status'
            ]);
        }
    }
}
