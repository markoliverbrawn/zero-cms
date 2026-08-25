<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Module.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Admin
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Admin;

use Zero\Core\App;
use Zero\Interfaces\Module as ModuleInterface;

/**
 * Class Module
 *
 * Module contract implementation for the Admin module: the back-office route table, the
 * authentication and CRUD screens, the dashboard, and the shared theme-side authentication
 * controllers that Shop and Forum register into their own routes.
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
     * Retrieves the name attribute value.
     *
     * @return string Response output.
     */
    public function getName(): string
    {
        return 'Admin';
    }

    /**
     * Retrieves the description attribute value.
     *
     * @return string Response output.
     */
    public function getDescription(): string
    {
        return 'Back-office authentication, dashboard, and CRUD screens';
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
            '#^/admin/?$#' => Controllers\LoginController::class,
            '#^/admin/login$#' => Controllers\LoginController::class,
            '#^/admin/logout$#' => Controllers\LogoutController::class,
            '#^/admin/forgot$#' => Controllers\ForgotController::class,
            '#^/admin/reset$#' => Controllers\ResetController::class,

            // Admin back-office routes
            '#^/admin/dashboard$#' => Controllers\DashboardController::class,
            '#^/admin/list/files/edit/([a-zA-Z0-9\-]+)$#' => Controllers\FilesController::class,
            '#^/admin/list/files$#' => Controllers\FilesController::class,
            '#^/admin/files$#' => Controllers\FilesController::class,
            '#^/admin/files/([a-zA-Z0-9_-]+)$#' => Controllers\FilesController::class,
            '#^/admin/secure-download/([a-zA-Z0-9\-]+)$#' => Controllers\SecureDownloadController::class,
            '#^/admin/preferences$#' => Controllers\PreferencesController::class,
            '#^/admin/settings/([a-zA-Z0-9_-]+)$#' => Controllers\ModuleSettingsController::class,
            '#^/admin/theme-switcher$#' => Controllers\ThemeSwitcherController::class,
            '#^/admin/google-callback$#' => Controllers\GoogleAuthController::class,

            // CRUD Model routes
            '#^/admin/list/([a-zA-Z0-9_-]+)$#' => Controllers\ListController::class,
            '#^/admin/edit/([a-zA-Z0-9_-]+)/([a-zA-Z0-9\-]+)$#' => Controllers\ModelController::class,
            '#^/admin/delete/([a-zA-Z0-9_-]+)$#' => Controllers\ModelController::class,
            '#^/admin/restore/([a-zA-Z0-9_-]+)$#' => Controllers\ModelController::class,
            '#^/admin/force-delete/([a-zA-Z0-9_-]+)$#' => Controllers\ModelController::class,
            '#^/admin/export/([a-zA-Z0-9_-]+)$#' => Controllers\ExportController::class,

            // Admin REST API routes, one focused controller per resource (see
            // src/Modules/Admin/Controllers/Api/ and FileManagerService for the shared
            // file-manager logic also used by the traditional /admin/files/* routes above)
            '#^/api/v1/admin/files/?$#' => Controllers\Api\FilesApiController::class,
            '#^/api/v1/admin/models/([a-zA-Z0-9_-]+)/?$#' => Controllers\Api\ModelApiController::class,
            '#^/api/v1/admin/models/([a-zA-Z0-9_-]+)/reorder/?$#' => Controllers\Api\ModelApiController::class,
            '#^/api/v1/admin/models/([a-zA-Z0-9_-]+)/([a-zA-Z0-9\-]+)/cascade-check$#' => Controllers\Api\ModelApiController::class,
            '#^/api/v1/admin/models/([a-zA-Z0-9_-]+)/([a-zA-Z0-9\-]+)$#' => Controllers\Api\ModelApiController::class,
            '#^/api/v1/admin/audit-logs/purge/?$#' => Controllers\Api\AuditLogApiController::class,
            '#^/api/v1/admin/preferences/?$#' => Controllers\Api\PreferencesApiController::class,
            '#^/api/v1/admin/block-preview/?$#' => Controllers\Api\BlockPreviewApiController::class,
            '#^/api/v1/admin/ai/generate-summary/?$#' => Controllers\Api\AiApiController::class,
            '#^/api/v1/user/send-welcome$#' => Controllers\Api\SendWelcomeController::class,

            // Redirect route for backward compatibility / back links
            '#^/admin/([a-zA-Z0-9_-]+)$#' => Controllers\RedirectController::class,
        ];
    }

    /**
     * Init processing implementation helper.
     *
     * @return mixed Response output.
     */
    public function init()
    {
        App::registerModuleSettings('admin', [
            'password_reset_expiry_minutes' => [
                'type' => 'number',
                'label' => 'Password Reset Link Expiry (Minutes)',
                'default' => 60,
                'required' => true,
                'min' => 1,
                'max' => 1440,
                'helper_text' => 'How long a password reset link remains valid after being requested.'
            ]
        ]);

        App::registerBlock('hero', [
            'label' => 'Hero Block',
            'description' => 'A bold headline hero block featuring an H1 title and content paragraphs.',
            'icon' => 'home',
            'admin_view' => \dirname(__FILE__) . '/Views/blocks/admin/hero.php',
            'frontend_view' => \dirname(__FILE__) . '/Views/blocks/frontend/hero.php'
        ]);
        App::registerBlock('grid', [
            'label' => 'Responsive Grid',
            'description' => 'A fully responsive grid layout of stacked image and text cards supporting links and sorting.',
            'icon' => 'grid',
            'admin_view' => \dirname(__FILE__) . '/Views/blocks/admin/grid.php',
            'frontend_view' => \dirname(__FILE__) . '/Views/blocks/frontend/grid.php'
        ]);
        App::registerBlock('text', [
            'label' => 'Rich Text Block',
            'description' => 'A standard content block with full-featured rich inline HTML editing capabilities.',
            'icon' => 'file',
            'admin_view' => \dirname(__FILE__) . '/Views/blocks/admin/text.php',
            'frontend_view' => \dirname(__FILE__) . '/Views/blocks/frontend/text.php'
        ]);
        App::registerBlock('text_image', [
            'label' => 'Rich Text Grid',
            'description' => 'Two-column text-and-image container block, optimized for visual/metadata side layouts.',
            'icon' => 'image',
            'admin_view' => \dirname(__FILE__) . '/Views/blocks/admin/text_image.php',
            'frontend_view' => \dirname(__FILE__) . '/Views/blocks/frontend/text_image.php'
        ]);
        App::registerBlock('gallery', [
            'label' => 'Responsive Grid Gallery',
            'description' => 'An elegant masonry style media gallery with interactive fullscreen asset selection previews.',
            'icon' => 'image',
            'admin_view' => \dirname(__FILE__) . '/Views/blocks/admin/gallery.php',
            'frontend_view' => \dirname(__FILE__) . '/Views/blocks/frontend/gallery.php'
        ]);
        App::registerBlock('masonry', [
            'label' => 'Pinterest Masonry Grid',
            'description' => 'Asymmetrical multi-column masonry card grid, ideal for lookbooks or designer portfolio displays.',
            'icon' => 'image',
            'admin_view' => \dirname(__FILE__) . '/Views/blocks/admin/masonry.php',
            'frontend_view' => \dirname(__FILE__) . '/Views/blocks/frontend/masonry.php'
        ]);
        App::registerBlock('testimonials', [
            'label' => 'Testimonials Carousel',
            'description' => 'An autoplaying client quote carousel slider with configurable slide duration and elegant transition states.',
            'icon' => 'settings',
            'admin_view' => \dirname(__FILE__) . '/Views/blocks/admin/testimonials.php',
            'frontend_view' => \dirname(__FILE__) . '/Views/blocks/frontend/testimonials.php'
        ]);
        App::registerBlock('accordion', [
            'label' => 'Accordion FAQ List',
            'description' => 'A sleek list of expandable/collapsible questions and answers with smooth height transitions.',
            'icon' => 'file',
            'admin_view' => \dirname(__FILE__) . '/Views/blocks/admin/accordion.php',
            'frontend_view' => \dirname(__FILE__) . '/Views/blocks/frontend/accordion.php'
        ]);
        App::registerBlock('sub_pages', [
            'label' => 'Sub-Pages List',
            'description' => 'A dynamic grid list of all sub-pages nested under the current page slug in the database.',
            'icon' => 'book-open',
            'admin_view' => \dirname(__FILE__) . '/Views/blocks/admin/sub_pages.php',
            'frontend_view' => \dirname(__FILE__) . '/Views/blocks/frontend/sub_pages.php'
        ]);
        App::registerBlock('code', [
            'label' => 'Source Code Block',
            'description' => 'A clean source code block with high-contrast syntax highlighting.',
            'icon' => 'file',
            'admin_view' => \dirname(__FILE__) . '/Views/blocks/admin/code.php',
            'frontend_view' => \dirname(__FILE__) . '/Views/blocks/frontend/code.php'
        ]);
        App::registerBlock('chart', [
            'label' => 'Performance Chart',
            'description' => 'A beautifully animated, zero-dependency SVG bar chart block to visualize comparative statistics.',
            'icon' => 'zap',
            'admin_view' => \dirname(__FILE__) . '/Views/blocks/admin/chart.php',
            'frontend_view' => \dirname(__FILE__) . '/Views/blocks/frontend/chart.php'
        ]);
    }
}
