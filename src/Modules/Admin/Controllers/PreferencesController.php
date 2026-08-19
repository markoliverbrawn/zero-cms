<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Controllers/PreferencesController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Admin\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Interfaces\Controller;
use Zero\Models\User;
use Zero\Support\I18n;
use Zero\Support\Logger;

/**
 * Class PreferencesController
 *
 * Per-user preference screen at /admin/preferences, persisting the signed-in account's own
 * interface choices.
 */
class PreferencesController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($param)
    {
        App::applyAuthMiddleware();
        
        $userId = $_SESSION['user_id'];
        $method = $_SERVER['REQUEST_METHOD'];
        $success = '';
        $error = '';

        // Handle AJAX Dashboard Layout Save instantly (Zero Dependencies!)
        if ($method === 'POST' && ($_GET['action'] ?? '') === 'save_layout') {
            App::applyCsrfMiddleware();
            
            $layout = $_POST['layout'] ?? [];
            if (!\is_array($layout)) {
                $layout = [];
            }
            
            $allowedWidgets = ['recent_posts', 'recent_pages', 'recent_media', 'quick_links'];
            $layout = \array_values(\array_intersect($layout, $allowedWidgets));
            
            $prefs = User::getPreferencesForUser($userId);
            $prefs['dashboard_layout'] = $layout;
            
            User::savePreferencesForUser($userId, $prefs);
            
            \header('Content-Type: application/json');
            echo \json_encode(['success' => true]);
            exit;
        }

        if ($method === 'POST') {
            App::applyCsrfMiddleware();
            
            // Collect post fields
            $theme = $_POST['theme'] ?? 'light';
            $themePreset = $_POST['theme_preset'] ?? 'default';
            $language = $_POST['language'] ?? 'en';
            $perPage = \intval($_POST['per_page'] ?? 20);
            $dashboardLayout = $_POST['dashboard_layout'] ?? [];
            $timezone = $_POST['timezone'] ?? 'UTC';

            // Basic validation
            if (!\in_array($theme, ['light', 'dark'])) {
                $theme = 'light';
            }
            if (!\in_array($themePreset, ['default', 'vintage-greenscreen'])) {
                $themePreset = 'default';
            }
            if (!\in_array($language, ['en', 'es', 'mi', 'hr'])) {
                $language = 'en';
            }
            if (!\in_array($perPage, [10, 20, 50, 100])) {
                $perPage = 20;
            }
            if (!\is_array($dashboardLayout)) {
                $dashboardLayout = [];
            }
            
            // Filter invalid layout items
            $allowedWidgets = ['recent_posts', 'recent_pages', 'recent_media', 'quick_links'];
            $dashboardLayout = \array_values(\array_intersect($dashboardLayout, $allowedWidgets));

            $prefs = [
                'theme' => $theme,
                'theme_preset' => $themePreset,
                'language' => $language,
                'per_page' => $perPage,
                'dashboard_layout' => $dashboardLayout,
                'timezone' => $timezone
            ];

            // Save preferences
            User::savePreferencesForUser($userId, $prefs);
            Logger::log($userId, 'save_preferences', 'user', $userId, ['preferences' => $prefs]);
            
            // Reset translation cache to apply language changes on the current page load instantly
            I18n::reset();
            
            $success = I18n::t('save_success_msg', [], 'Preferences saved successfully!');
        }

        // Get updated preferences
        $prefs = User::getPreferencesForUser($userId);

        App::render('admin/preferences', [
            'prefs' => $prefs,
            'success' => $success,
            'error' => $error,
            'timezones' => \DateTimeZone::listIdentifiers()
        ]);
        exit;
    }
}
