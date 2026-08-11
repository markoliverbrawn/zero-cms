<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Controllers/Api/PreferencesApiController.php
 * Architectural Purpose: REST API endpoint for saving the logged-in user's back-office
 * preferences (dashboard layout, theme, language, timezone, pagination size).
 * Package: Zero\Modules\Admin\Controllers\Api
 */

namespace Zero\Modules\Admin\Controllers\Api;

use Zero\Database\DB;
use Zero\Models\User;
use Zero\Support\I18n;

/**
 * Class PreferencesApiController
 */
class PreferencesApiController extends AdminApiControllerBase
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $matches Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($matches)
    {
        $user = $this->authenticate();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = \parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $body = $this->parseBody();

        if (\preg_match('#^/api/v1/admin/preferences/?$#', $uri) && ($method === 'PATCH' || $method === 'POST')) {
            $this->handleSavePreferences($user['id'], $body);
        }

        $this->respond(['success' => false, 'error' => 'Endpoint not found or method not allowed'], 404);
    }

    /**
     * Handle save preferences processing implementation helper.
     *
     * @param mixed $userId Argument descriptor.
     * @param mixed $body Argument descriptor.
     * @return mixed Response output.
     */
    protected function handleSavePreferences($userId, $body)
    {
        $action = $_GET['action'] ?? $body['action'] ?? '';

        if ($action === 'save_layout') {
            $layout = $body['layout'] ?? [];
            if (!\is_array($layout)) {
                $this->respond(['success' => false, 'error' => 'Invalid layout data'], 400);
            }

            // Fetch current preferences, update dashboard layout and save
            $prefs = User::getPreferencesForUser($userId);
            $prefs['dashboard_layout'] = $layout;

            DB::query("UPDATE users SET preferences = ? WHERE id = ?", [\json_encode($prefs), $userId]);
            $this->respond(['success' => true]);
        }

        // Generic Preferences Save (ThemeSwitcher, Layout toggles etc.)
        $theme = $body['theme'] ?? 'light';
        $themePreset = $body['theme_preset'] ?? 'default';
        $widgets = $body['widgets'] ?? [];
        $language = $body['language'] ?? 'en';
        $timezone = $body['timezone'] ?? 'UTC';
        $perPage = \intval($body['per_page'] ?? 20);

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

        $prefs = User::getPreferencesForUser($userId);
        $prefs['theme'] = $theme;
        $prefs['theme_preset'] = $themePreset;
        $prefs['dashboard_layout'] = $widgets; // Save layout properly under dashboard_layout!
        $prefs['language'] = $language;
        $prefs['timezone'] = $timezone;
        $prefs['per_page'] = $perPage;

        // Reset translation cache to apply language changes on the current page load instantly
        I18n::reset();

        DB::query("UPDATE users SET preferences = ? WHERE id = ?", [\json_encode($prefs), $userId]);
        $this->respond(['success' => true]);
    }
}
