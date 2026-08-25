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

        // Standalone Sites Management (System, requires the cross-tenant sites.manage permission)
        self::registerAdminSidebarSection('sites', [
            'title' => 'Manage Sites',
            'url' => '/admin/list/sites',
            'icon' => 'home',
            'permission' => 'sites.manage',
            'is_system' => true,
            'precedence' => 400
        ]);

        // Collapsible Security Management (System). The section itself carries no permission --
        // it auto-collapses once none of its links are visible (see Admin/Views/layout.php).
        // Security-owned links (audit logs, security audits) are registered by
        // Security\Module::init(), not here, since this is a core file.
        self::registerAdminSidebarSection('security', [
            'title' => I18n::t('security'),
            'icon' => 'shield',
            'is_system' => true,
            'precedence' => 410
        ]);

        self::registerAdminSidebarLink('security', [
            'title' => I18n::t('manage_users'),
            'url' => '/admin/list/users',
            'icon' => 'user',
            'permission' => 'users.manage',
            'precedence' => 10
        ]);

        // Collapsible Module Settings (populated dynamically as each module calls
        // registerModuleSettings() during its own init() -- see ManagesModuleSettings)
        self::registerAdminSidebarSection('module_settings', [
            'title' => 'Module Settings',
            'icon' => 'settings',
            'permission' => 'modules.manage',
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
        // 1. Check RBAC permission requirement
        if (!empty($item['permission']) && !self::authorize($item['permission'])) {
            return false;
        }

        // 2. Check module dependency (applies to every role, including super admins:
        // a link gated to a module should only ever be visible if the module is enabled)
        if (!empty($item['module_dependency'])) {
            $module = $item['module_dependency'];
            if (!$site || !$site->isModuleEnabled($module)) {
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
            'permission' => null,
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
            'permission' => null,
            'is_system' => false,
            'precedence' => 100,
            'links' => []
        ], $config);
    }

}
