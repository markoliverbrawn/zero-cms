<?php

declare(strict_types=1);

/**
 * File: src/Lang/en.php
 * Architectural Purpose: Handles operations and business logic within the system.
 * Package: Zero
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

return [
    // Sidebar
    'home' => 'Home',
    'view_site' => 'View Site',
    'admin_dashboard' => 'Admin Dashboard',
    'content_management' => 'Manage Content',
    'security' => 'Security',
    'manage_pages' => 'Manage Pages',
    'media_library' => 'Media Library',
    'manage_users' => 'Manage Users',
    'preferences' => 'Preferences',

    // Header
    'logged_in_as' => 'Logged in as',
    'logout' => 'Logout',

    // Dashboard
    'configure_dashboard' => 'Configure Dashboard',
    'quick_links' => 'Quick Links',
    'recent_pages' => 'Recent Pages',
    'recent_media' => 'Recent Media',
    'no_pages_found' => 'No pages found.',
    'no_media_found' => 'No media uploads found.',
    'view_media_library' => 'View Media Library',
    'dashboard_empty_title' => 'Your Dashboard is Empty',
    'dashboard_empty_desc' => 'You have disabled all widgets. Click the button below to choose which cards you would like to show on your screen.',
    'configure_widgets' => 'Configure Widgets',

    // Preferences View
    'user_preferences' => 'User Preferences',
    'interface_appearance' => 'Interface & Appearance',
    'language_localization' => 'Language & Localization',
    'workspace_dashboard' => 'Workspace & Dashboard',
    'color_preset' => 'Color Preset',
    'theme_preset_desc' => 'Select a premium color scheme to apply globally.',
    'theme_mode' => 'Theme Mode',
    'theme_mode_desc' => 'Adjust brightness scheme (supported by all presets).',
    'light_mode' => 'Light Mode',
    'dark_mode' => 'Dark Mode',
    'default_pagination_limit' => 'Default Pagination Limit',
    'pagination_limit_desc' => 'Set default listing size for lists and tables.',
    'user_timezone' => 'User Timezone',
    'timezone_desc' => 'Select timezone for dates and logs.',
    'dashboard_configurations' => 'Dashboard Configurations',
    'dashboard_configurations_desc' => 'Choose which summary widget panels to display on your admin dashboard workspace:',
    'back_to_dashboard' => 'Back to Dashboard',
    'save_preferences' => 'Save Preferences',
    'language' => 'Language',
    'language_desc' => 'Select your preferred language for the admin interface.',
    'save_success_msg' => 'Changes saved successfully!',

    // Models & Crud Labels
    'title' => 'Title',
    'slug' => 'Slug',
    'content' => 'Content',
    'publish_status' => 'Publish Status',
    'status' => 'Publish Status',
    'created_at' => 'Created At',
    'updated_at' => 'Updated At',
    'username' => 'Username',
    'email' => 'Email',
    'id' => 'ID',
    'draft' => 'draft',
    'published' => 'published',

    // Input Helper Texts
    'title_help' => 'The user-facing headline or title for this entry.',
    'slug_help' => 'The unique, web-safe URL portion generated from the title.',
    'content_help' => 'The main body layout composed of structured visual blocks.',
    'controller_help' => 'Optional PHP controller override responsible for dynamic request mapping.',
    'view_help' => 'Optional theme view template file name (without .php extension) to override layout.',
    'precedence_help' => 'Display weight order value. Higher numbers appear first in lists.',
    'status_help' => 'Visibility status determining if the record is accessible on the website.',
    'username_help' => 'The unique handle used to log in to the secure back-office workspace.',
    'email_help' => 'Primary user contact address used for system emails and recovery resets.',
    'role_help' => 'Account privilege level regulating security access and site management.',
    'name_help' => 'The official name of this multi-tenant site partition.',
    'domain_help' => 'The HTTP host domain string mapped to route to this site tenant.',
    'theme_help' => 'The design skin template used to style the front-end interface.',
    'enabled_modules_help' => 'Check which specialized premium functional areas are active.',
    'description_help' => 'A descriptive summary of this item.',
    'no_records_found' => 'No records found.',
];
