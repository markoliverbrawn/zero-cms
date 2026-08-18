<?php

declare(strict_types=1);

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
 * Module contract implementation for the Shop module: catalogue, cart, checkout and order models,
 * the customer account area, the product blocks, and the back-office management screens.
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
        return Migration::class;
    }

    /**
     * Retrieves the routes attribute value.
     *
     * @return mixed Response output.
     */
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

    /**
     * Init processing implementation helper.
     *
     * @return mixed Response output.
     */
    public function init()
    {
        App::registerThemeFallback('shop');

        // The module's own generic default templates for /shop/catalog, /shop/product/:slug,
        // /shop/cart, /shop/checkout, /shop/success, /shop/account -- backing the 'shop' theme
        // fallback name registered above with this module's own Views/ folder instead of a
        // bundled src/Views/themes/shop/ directory. Only registered if a host project hasn't
        // already claimed 'shop' via its own pre-bootstrap App::registerThemePath('shop', ...)
        // call -- Module::init() runs inside App::bootstrap(), which is necessarily after that,
        // so registering unconditionally here would silently clobber a host's override.
        if (!\in_array('shop', App::getRegisteredThemeNames(), true)) {
            App::registerThemePath('shop', \dirname(__FILE__) . '/Views');
        }
        App::registerModuleStylesheet('shop', APPLICATION_ROOT . '/public/assets/css/themes/shop/shop.css');

        App::registerModuleSettings('shop', [
            'currency_symbol' => [
                'type' => 'text',
                'label' => 'Currency Symbol',
                'default' => '$',
                'required' => true,
                'helper_text' => 'Prepended to every price shown in the catalog, cart, and checkout.'
            ],
            'free_shipping_threshold' => [
                'type' => 'number',
                'label' => 'Free Shipping Threshold',
                'default' => 150,
                'required' => true,
                'helper_text' => 'Orders with a subtotal at or above this amount qualify for free shipping.'
            ],
            'standard_shipping_cost' => [
                'type' => 'number',
                'label' => 'Standard Shipping Cost',
                'default' => 15,
                'required' => true,
                'helper_text' => 'Flat shipping cost charged on orders below the free-shipping threshold.'
            ]
        ]);

        App::registerModel('products', Product::class);
        App::registerModel('productvariants', ProductVariant::class);
        App::registerModel('orders', Order::class);
        App::registerModel('categories', Category::class);

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
            'admin_view' => \dirname(__FILE__) . '/Views/blocks/admin/categories.php',
            'frontend_view' => \dirname(__FILE__) . '/Views/blocks/frontend/categories.php'
        ]);

        if (\class_exists(SearchService::class)) {
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
