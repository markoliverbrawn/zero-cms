<?php
/**
 * File: src/Modules/Admin/Module.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Admin
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Admin;

use Zero\Interfaces\Module as ModuleInterface;
use Zero\Core\App;

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
        return '#2563eb';
    }

    /**
     * Retrieves the dashboard widget view attribute value.
     *
     * @return string Response output.
     */
    public function getDashboardWidgetView(): ?string
    {
        return null;
    }

    /**
     * Retrieves the id attribute value.
     *
     * @return string Response output.
     */
    public function getId(): string
    {
        return 'admin';
    }

    /**
     * Retrieves the migration class attribute value.
     *
     * @return string Response output.
     */
    public function getMigrationClass(): ?string
    {
        return null;
    }

    /**
     * Retrieves the routes attribute value.
     *
     * @return mixed Response output.
     */
    public function getRoutes(): array
    {
        return [
            // Admin authentication routes
            '#^/admin/?$#' => Controllers\LoginController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/admin/login$#' => Controllers\LoginController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/admin/logout$#' => Controllers\LogoutController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/admin/forgot$#' => Controllers\ForgotController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/admin/reset$#' => Controllers\ResetController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,

            // Admin back-office routes
            '#^/admin/dashboard$#' => Controllers\DashboardController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/admin/list/files/edit/([a-zA-Z0-9\-]+)$#' => Controllers\FilesController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/admin/list/files$#' => Controllers\FilesController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/admin/files$#' => Controllers\FilesController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/admin/files/([a-zA-Z0-9_-]+)$#' => Controllers\FilesController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/admin/secure-download/([a-zA-Z0-9\-]+)$#' => Controllers\SecureDownloadController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/admin/preferences$#' => Controllers\PreferencesController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/admin/theme-switcher$#' => Controllers\ThemeSwitcherController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/admin/google-callback$#' => Controllers\GoogleAuthController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,

            // CRUD Model routes
            '#^/admin/list/([a-zA-Z0-9_-]+)$#' => Controllers\ListController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/admin/edit/([a-zA-Z0-9_-]+)/([a-zA-Z0-9\-]+)$#' => Controllers\ModelController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/admin/delete/([a-zA-Z0-9_-]+)$#' => Controllers\ModelController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/admin/restore/([a-zA-Z0-9_-]+)$#' => Controllers\ModelController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/admin/force-delete/([a-zA-Z0-9_-]+)$#' => Controllers\ModelController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/admin/export/([a-zA-Z0-9_-]+)$#' => Controllers\ExportController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,

            // Admin REST API routes
            '#^/api/v1/admin/([a-zA-Z0-9_/\-]+)$#' => Controllers\Api\AdminApiController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
            '#^/api/v1/user/send-welcome$#' => Controllers\Api\SendWelcomeController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,

            // Redirect route for backward compatibility / back links
            '#^/admin/([a-zA-Z0-9_-]+)$#' => Controllers\RedirectController::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class,
        ];
    }

    /**
     * Init processing implementation helper.
     *
     * @return mixed Response output.
     */
    public function init()
    {
        App::registerBlock('baseline', [
            'label' => 'Baseline Hero Block',
            'description' => 'A bold headline hero block featuring an H1 title and content paragraphs.',
            'icon' => 'home',
            'admin_view' => dirname(__FILE__) . '/Views/blocks/admin/baseline.php',
            'frontend_view' => dirname(__FILE__) . '/Views/blocks/frontend/baseline.php'
        ]);
        App::registerBlock('grid', [
            'label' => 'Responsive Grid',
            'description' => 'A fully responsive grid layout of stacked image and text cards supporting links and sorting.',
            'icon' => 'grid',
            'admin_view' => dirname(__FILE__) . '/Views/blocks/admin/grid.php',
            'frontend_view' => dirname(__FILE__) . '/Views/blocks/frontend/grid.php'
        ]);
        App::registerBlock('text', [
            'label' => 'Rich Text Block',
            'description' => 'A standard content block with full-featured rich inline HTML editing capabilities.',
            'icon' => 'file',
            'admin_view' => dirname(__FILE__) . '/Views/blocks/admin/text.php',
            'frontend_view' => dirname(__FILE__) . '/Views/blocks/frontend/text.php'
        ]);
        App::registerBlock('text_image', [
            'label' => 'Rich Text Grid',
            'description' => 'Two-column text-and-image container block, optimized for visual/metadata side layouts.',
            'icon' => 'image',
            'admin_view' => dirname(__FILE__) . '/Views/blocks/admin/text_image.php',
            'frontend_view' => dirname(__FILE__) . '/Views/blocks/frontend/text_image.php'
        ]);
        App::registerBlock('gallery', [
            'label' => 'Responsive Grid Gallery',
            'description' => 'An elegant masonry style media gallery with interactive fullscreen asset selection previews.',
            'icon' => 'image',
            'admin_view' => dirname(__FILE__) . '/Views/blocks/admin/gallery.php',
            'frontend_view' => dirname(__FILE__) . '/Views/blocks/frontend/gallery.php'
        ]);
        App::registerBlock('masonry', [
            'label' => 'Pinterest Masonry Grid',
            'description' => 'Asymmetrical multi-column masonry card grid, ideal for lookbooks or designer portfolio displays.',
            'icon' => 'image',
            'admin_view' => dirname(__FILE__) . '/Views/blocks/admin/masonry.php',
            'frontend_view' => dirname(__FILE__) . '/Views/blocks/frontend/masonry.php'
        ]);
        App::registerBlock('testimonials', [
            'label' => 'Testimonials Carousel',
            'description' => 'An autoplaying client quote carousel slider with configurable slide duration and elegant transition states.',
            'icon' => 'settings',
            'admin_view' => dirname(__FILE__) . '/Views/blocks/admin/testimonials.php',
            'frontend_view' => dirname(__FILE__) . '/Views/blocks/frontend/testimonials.php'
        ]);
        App::registerBlock('accordion', [
            'label' => 'Accordion FAQ List',
            'description' => 'A sleek list of expandable/collapsible questions and answers with smooth height transitions.',
            'icon' => 'file',
            'admin_view' => dirname(__FILE__) . '/Views/blocks/admin/accordion.php',
            'frontend_view' => dirname(__FILE__) . '/Views/blocks/frontend/accordion.php'
        ]);
        App::registerBlock('sub_pages', [
            'label' => 'Sub-Pages List',
            'description' => 'A dynamic grid list of all sub-pages nested under the current page slug in the database.',
            'icon' => 'book-open',
            'admin_view' => dirname(__FILE__) . '/Views/blocks/admin/sub_pages.php',
            'frontend_view' => dirname(__FILE__) . '/Views/blocks/frontend/sub_pages.php'
        ]);
        App::registerBlock('code', [
            'label' => 'Source Code Block',
            'description' => 'A clean source code block with high-contrast syntax highlighting.',
            'icon' => 'file',
            'admin_view' => dirname(__FILE__) . '/Views/blocks/admin/code.php',
            'frontend_view' => dirname(__FILE__) . '/Views/blocks/frontend/code.php'
        ]);
        App::registerBlock('chart', [
            'label' => 'Performance Chart',
            'description' => 'A beautifully animated, zero-dependency SVG bar chart block to visualize comparative statistics.',
            'icon' => 'zap',
            'admin_view' => dirname(__FILE__) . '/Views/blocks/admin/chart.php',
            'frontend_view' => dirname(__FILE__) . '/Views/blocks/frontend/chart.php'
        ]);
    }
}
