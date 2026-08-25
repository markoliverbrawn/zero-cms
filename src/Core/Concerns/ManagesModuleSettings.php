<?php

declare(strict_types=1);

/**
 * File: src/Core/Concerns/ManagesModuleSettings.php
 * Architectural Purpose: The per-module settings schema registry -- lets a module declare a set
 * of site-configurable settings fields the same declarative way it already declares admin model
 * fields (getConfig()) or blocks (registerBlock()), so a single generic admin page can render and
 * persist them without any module writing its own settings controller/view.
 * Package: Zero\Core\Concerns
 */

namespace Zero\Core\Concerns;

/**
 * Trait ManagesModuleSettings
 */
trait ManagesModuleSettings
{
    protected static $moduleSettingsSchemas = [];

    /**
     * Register a module's site-configurable settings schema. Field shape mirrors a model's
     * getConfig() array: 'type' (text|number|select|checkbox|textarea), 'label', 'default',
     * 'required', 'options' (for select), 'helper_text'.
     *
     * @param string $moduleId
     * @param array $schema
     * @return void
     */
    public static function registerModuleSettings(string $moduleId, array $schema): void
    {
        self::$moduleSettingsSchemas[$moduleId] = $schema;

        // Give the module a working settings page in the admin sidebar for free -- a module only
        // has to call this one method to get a fully-rendered, persisted settings screen; it
        // doesn't need to hand-register its own sidebar link the way block/model registrations do.
        self::registerAdminSidebarLink('module_settings', [
            'title' => \ucwords(\str_replace(['-', '_'], ' ', $moduleId)) . ' Settings',
            'url' => '/admin/settings/' . $moduleId,
            'icon' => 'settings',
            'module_dependency' => $moduleId,
            'permission' => 'modules.manage',
            'precedence' => 100
        ]);
    }

    /**
     * Get a single module's registered settings schema, or an empty array if that module never
     * registered one (e.g. an unknown/disabled module id).
     *
     * @param string $moduleId
     * @return array
     */
    public static function getModuleSettingsSchema(string $moduleId): array
    {
        return self::$moduleSettingsSchemas[$moduleId] ?? [];
    }

    /**
     * Get every registered module settings schema, keyed by module id.
     *
     * @return array<string, array>
     */
    public static function getRegisteredModuleSettingsSchemas(): array
    {
        return self::$moduleSettingsSchemas;
    }

    /**
     * Convenience accessor for reading a single module setting value off the current site,
     * for the common case where a caller only needs one value and has no other use for the
     * Site object. Falls back to $default when there is no current site (e.g. CLI context).
     *
     * @param string $moduleId
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getModuleSetting(string $moduleId, string $key, $default = null)
    {
        $site = self::getCurrentSite();
        return $site ? $site->getModuleSetting($moduleId, $key, $default) : $default;
    }
}
