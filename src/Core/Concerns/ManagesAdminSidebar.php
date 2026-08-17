<?php

declare(strict_types=1);

/**
 * File: src/Core/Concerns/ManagesAdminSidebar.php
 * Architectural Purpose: The admin sidebar section/link registry, default core sidebar content,
 * and per-item role/module visibility checks. Extracted out of App.php.
 * Package: Zero\Core\Concerns
 */

namespace Zero\Core\Concerns;

use Zero\Support\I18n;

/**
 * Trait ManagesAdminSidebar
 */
trait ManagesAdminSidebar
{
    protected static $adminSidebarSections = [];

    /**
     * Get all registered admin sidebar sections sorted by precedence,
     * with their nested links also sorted by precedence.
     *
     * @return array
     */
    public static function getAdminSidebarSections(): array
    {
        // Sort sections by precedence
        \uasort(self::$adminSidebarSections, function($a, $b) {
            return ($a['precedence'] ?? 100) <=> ($b['precedence'] ?? 100);
        });

        // Sort links within each section by precedence
        foreach (self::$adminSidebarSections as $id => &$section) {
            if (!empty($section['links'])) {
                \usort($section['links'], function($a, $b) {
                    return ($a['precedence'] ?? 100) <=> ($b['precedence'] ?? 100);
                });
            }
        }
        unset($section);

        return self::$adminSidebarSections;
    }

    /**
     * Populate standard core dashboard, content, and security sidebar items.
     *
     * @return void
     */
    protected static function initializeDefaultSidebar(): void
    {
        // Standalone Dashboard
        self::registerAdminSidebarSection('dashboard', [
            'title' => I18n::t('admin_dashboard'),
            'url' => '/admin/dashboard',
            'icon' => 'dashboard',
            'precedence' => 10
        ]);

        // Collapsible Content Management
        self::registerAdminSidebarSection('content', [
            'title' => I18n::t('content_management'),
            'icon' => 'book-open',
            'precedence' => 100
        ]);

        // Core Content Links
        self::registerAdminSidebarLink('content', [
            'title' => I18n::t('manage_pages'),
            'url' => '/admin/list/pages',
            'icon' => 'file',
            'precedence' => 40
        ]);

        self::registerAdminSidebarLink('content', [
            'title' => I18n::t('media_library'),
            'url' => '/admin/list/files',
            'icon' => 'image',
            'precedence' => 50
        ]);

        // Standalone Sites Management (System, Super Admin only)
        self::registerAdminSidebarSection('sites', [
            'title' => 'Manage Sites',
            'url' => '/admin/list/sites',
            'icon' => 'home',
            'super_admin_only' => true,
            'is_system' => true,
            'precedence' => 400
        ]);

        // Collapsible Security Management (System, Super Admin only)
        self::registerAdminSidebarSection('security', [
            'title' => I18n::t('security'),
            'icon' => 'shield',
            'super_admin_only' => true,
            'is_system' => true,
            'precedence' => 410
        ]);

        self::registerAdminSidebarLink('security', [
            'title' => I18n::t('manage_users'),
            'url' => '/admin/list/users',
            'icon' => 'user',
            'precedence' => 10
        ]);

        self::registerAdminSidebarLink('security', [
            'title' => 'Security Logs',
            'url' => '/admin/list/audit_logs',
            'icon' => 'clock',
            'precedence' => 20
        ]);

        self::registerAdminSidebarLink('security', [
            'title' => 'Security Audits',
            'url' => '/admin/list/security_audits',
            'icon' => 'clipboard',
            'precedence' => 30
        ]);

        // Collapsible Module Settings (populated dynamically as each module calls
        // registerModuleSettings() during its own init() -- see ManagesModuleSettings)
        self::registerAdminSidebarSection('module_settings', [
            'title' => 'Module Settings',
            'icon' => 'settings',
            'super_admin_only' => true,
            'is_system' => true,
            'precedence' => 420
        ]);
    }

    /**
     * Helper to resolve if a sidebar item (section or link) is visible
     * to the current logged-in user under the active tenant site.
     *
     * @param array $item
     * @param \Zero\Models\Site|null $site
     * @return bool
     */
    public static function isSidebarItemVisible(array $item, ?\Zero\Models\Site $site): bool
    {
        $role = self::getCurrentUserRole();

        // 1. Check super admin only restriction
        if (!empty($item['super_admin_only']) && $role !== 'super_admin') {
            return false;
        }

        // 2. Check module dependency (super admins bypass module-disabled restrictions in the back-office)
        if (!empty($item['module_dependency'])) {
            $module = $item['module_dependency'];
            $isEnabled = $site && $site->isModuleEnabled($module);
            if (!$isEnabled && $role !== 'super_admin') {
                return false;
            }
        }

        return true;
    }

    /**
     * Register a link under a specific admin sidebar section.
     *
     * @param string $sectionId
     * @param array $linkConfig
     * @return void
     */
    public static function registerAdminSidebarLink(string $sectionId, array $linkConfig): void
    {
        if (!isset(self::$adminSidebarSections[$sectionId])) {
            self::registerAdminSidebarSection($sectionId, [
                'title' => \ucfirst($sectionId),
                'precedence' => 500
            ]);
        }

        self::$adminSidebarSections[$sectionId]['links'][] = \array_merge([
            'title' => '',
            'url' => '',
            'icon' => 'file',
            'module_dependency' => null,
            'super_admin_only' => false,
            'precedence' => 100
        ], $linkConfig);
    }

    /**
     * Register a new admin sidebar section or top-level item.
     *
     * @param string $id
     * @param array $config
     * @return void
     */
    public static function registerAdminSidebarSection(string $id, array $config): void
    {
        self::$adminSidebarSections[$id] = \array_merge([
            'id' => $id,
            'title' => '',
            'icon' => 'file',
            'url' => null,
            'module_dependency' => null,
            'super_admin_only' => false,
            'is_system' => false,
            'precedence' => 100,
            'links' => []
        ], $config);
    }

}
