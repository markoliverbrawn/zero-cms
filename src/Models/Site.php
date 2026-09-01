<?php

declare(strict_types=1);

/**
 * File: src/Models/Site.php
 * Architectural Purpose: Active Record data model or behavioral trait wrapping database schema representation with tenant-scoping.
 * Package: Zero\Models
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

// src/Models/Site.php

namespace Zero\Models;

use Zero\Core\App;
use Zero\Core\Storage\Storage;
use Zero\Database\DB;
use Zero\Interfaces\Model;
use Zero\Models\Traits\CascadesDeletes;
use Zero\Models\Traits\IsModel;
use Zero\Support\I18n;

/**
 * Class Site
 *
 * Active Record model for a tenant site, and the origin of most tenant scoping in the engine.
 * Resolves a site by domain, stores per-tenant module enable/disable state and module settings,
 * supplies the theme/timezone/homepage option sets the admin forms offer, and declares the cascade
 * map applied when a site is deleted.
 */
class Site implements Model
{
    use IsModel, CascadesDeletes {
        CascadesDeletes::delete insteadof IsModel;
        CascadesDeletes::forceDelete insteadof IsModel;
        IsModel::delete as traitDelete;
        IsModel::forceDelete as traitForceDelete;
    }

    protected static $tableName = 'sites';
    protected static $modelType = null;
    protected static $fillable = ['name', 'domain', 'theme', 'enabled_modules', 'timezone', 'default_language', 'homepage_id', 'expires_at', 'settings', 'email_override'];
    protected static $systemModules = ['admin', 'queue', 'security'];
    protected static array $cascadeDeletes = [
        User::class => 'site_id',
        Page::class => 'site_id',
        Media::class => 'site_id',
        PasswordReset::class => 'site_id',
        AuditLog::class => 'site_id'
    ];

    public $id;
    public $name;
    public $domain;
    public $theme;
    public $enabled_modules;
    public $timezone = 'UTC';
    public $default_language = 'en';
    public $homepage_id;
    public $expires_at;
    public $settings;
    public $email_override;
    public $created_at;
    public $updated_at;
    public $deleted_at;

    /**
     * Override standard delete to enforce strict active site deletion protection.
     */
    public function delete()
    {
        if ($this->id === App::getCurrentSiteId()) {
            throw new \Exception("Deletion blocked: You cannot delete the active tenant site.");
        }
        $this->cascadeDeleteChildren();
        
        $res = $this->traitDelete();

        // Recursively clean up the tenant uploads directory if it exists
        $uploadDir = Storage::getUploadsRoot() . '/' . $this->id;
        $this->deleteDirectoryRecursive($uploadDir);

        return $res;
    }

    /**
     * Helper to recursively delete a directory, all of its subdirectories, and files.
     */
    private function deleteDirectoryRecursive(string $dir): bool
    {
        if (!\file_exists($dir)) {
            return true;
        }

        // Configure a temporary custom error handler to trap and convert filesystem warnings into exceptions,
        // avoiding any output printing (which would corrupt HTTP redirect headers).
        \set_error_handler(function($errno, $errstr) {
            throw new \ErrorException($errstr, 0, $errno);
        });

        try {
            if (!\is_dir($dir)) {
                return \unlink($dir);
            }
            foreach (\scandir($dir) as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                // Recurse without interrupting the loop if one file encounters a permission blockage
                $this->deleteDirectoryRecursive($dir . '/' . $item);
            }
            return \rmdir($dir);
        } catch (\Exception $e) {
            // Rethrow descriptive file deletion or other failures to bubble out to the controller/user
            throw new \Exception("Deletion failed: Could not clean up tenant uploads folder. " . $e->getMessage());
        } finally {
            \restore_error_handler();
        }
    }

    /**
     * Find by domain processing implementation helper.
     *
     * @param string $domain Argument descriptor.
     * @return mixed Response output.
     */
    public static function findByDomain(string $domain)
    {
        $stmt = DB::query("SELECT * FROM sites WHERE domain = ? LIMIT 1", [$domain]);
        $row = $stmt->fetch();
        if ($row) {
            return new static($row);
        }
        return null;
    }

    /**
     * Override standard forceDelete to enforce strict active site permanent deletion protection.
     */
    public function forceDelete()
    {
        if ($this->id === App::getCurrentSiteId()) {
            throw new \Exception("Permanent deletion blocked: You cannot delete the active tenant site.");
        }
        $this->cascadeForceDeleteChildren();
        
        $res = $this->traitForceDelete();

        // Recursively clean up the tenant uploads directory if it exists
        $uploadDir = Storage::getUploadsRoot() . '/' . $this->id;
        $this->deleteDirectoryRecursive($uploadDir);

        return $res;
    }

    /**
     * Dynamically build up the list of registered models to delete based on registered modules tables that carry site_id.
     * Prevents any hardcoded Module references in the Site core model!
     */
    public function getCascadeDeletes(): array
    {
        $cascade = [];
        $registered = App::getRegisteredModels();
        foreach ($registered as $name => $class) {
            // Prevent self-referential or circular site cascading deletions
            if ($class === self::class) {
                continue;
            }
            if (\class_exists($class)) {
                try {
                    $reflector = new \ReflectionClass($class);
                    if ($reflector->hasProperty('tableName')) {
                        $prop = $reflector->getProperty('tableName');
                        $prop->setAccessible(true);
                        $tableName = $prop->getValue();
                        
                        // Verify if the table schema actually contains a 'site_id' column
                        if (DB::hasColumn($tableName, 'site_id')) {
                            $cascade[$class] = 'site_id';
                        }
                    }
                } catch (\Exception $e) {
                    // Safe fallback if model reflections or database column checks fail
                }
            }
        }
        return $cascade;
    }

    /**
     * Retrieves the config attribute value.
     *
     * @return mixed Response output.
     */
    public static function getConfig(): array
    {
        return [
            'favicon' => [
                'type' => 'text',
                'label' => 'Icon',
                'editable' => false,
                'listDisplay' => true,
                'listView' => 'fields/favicon'
            ],
            'id' => ['type' => 'int', 'label' => 'ID', 'editable' => false, 'listDisplay' => false],
            'name' => [
                'type' => 'text',
                'label' => 'Site Name',
                'editable' => true,
                'required' => true,
                'listDisplay' => true,
                'searchable' => true,
                'listView' => 'fields/site_identity'
            ],
            'domain' => [
                'type' => 'text',
                'label' => 'Domain Name',
                'editable' => true,
                'required' => true,
                'listDisplay' => false,
                'searchable' => true
            ],
            'theme' => [
                'type' => 'select',
                'label' => 'Site Theme',
                'options' => self::getThemeOptions(),
                'editable' => true,
                'listDisplay' => true,
                'required' => true
            ],
            'enabled_modules' => [
                'type' => 'modules',
                'label' => 'Enabled Modules',
                'editable' => true,
                'listDisplay' => true,
                'listView' => 'fields/modules'
            ],
            'timezone' => [
                'type' => 'select',
                'label' => 'Site Timezone',
                'options' => self::getTimezoneOptions(),
                'editable' => true,
                'listDisplay' => false,
                'required' => true
            ],
            'default_language' => [
                'type' => 'select',
                'label' => 'Default Language',
                'options' => ['en' => 'English', 'es' => 'Español', 'hr' => 'Hrvatski', 'mi' => 'Māori'],
                'editable' => true,
                'listDisplay' => false,
                'required' => true
            ],
            'homepage_id' => [
                'type' => 'select',
                'label' => 'Site Homepage',
                'options' => self::getHomepageOptions(),
                'editable' => true,
                'listDisplay' => false,
                'required' => false
            ],
            // Editing a site record at all already requires the 'sites.manage' permission (see
            // Zero\Support\Permissions), granted only to super_admin -- neither 'admin' nor
            // 'editor' can reach this field via ModelController/ModelApiController, so no
            // additional field-level check is needed to keep this super_admin-only.
            'email_override' => [
                'type' => 'email',
                'label' => 'Redirect All Site Email To (Test Mode)',
                'editable' => true,
                'listDisplay' => false,
                'required' => false
            ],
            'created_at' => ['type' => 'datetime', 'label' => I18n::t('created_at'), 'editable' => false, 'listDisplay' => true],
            'updated_at' => ['type' => 'datetime', 'label' => I18n::t('updated_at'), 'editable' => false, 'listDisplay' => true],
        ];
    }

    /**
     * Retrieves the homepage options attribute value.
     *
     * @return mixed Response output.
     */
    public static function getHomepageOptions(): array
    {
        $options = ['' => '-- Default Routing (Empty/Home slug) --'];
        try {
            $siteId = App::getCurrentSiteId();
            if ($siteId) {
                // Fetch all published and draft pages for this site ID that are not soft-deleted
                $pages = DB::query("SELECT id, title, slug FROM pages WHERE site_id = ? AND deleted_at IS NULL ORDER BY title ASC", [$siteId])->fetchAll();
                foreach ($pages as $p) {
                    $slugText = $p['slug'] === '' ? ' (Root Homepage)' : ' (/' . $p['slug'] . ')';
                    $options[$p['id']] = $p['title'] . $slugText;
                }
            }
        } catch (\Exception $e) {
            // Safe fallback
        }
        return $options;
    }

    /**
     * Retrieves the theme options attribute value.
     *
     * @return mixed Response output.
     */
    public static function getThemeOptions(): array
    {
        $options = [];
        $themesDir = APPLICATION_ROOT . '/src/Views/themes';
        if (\is_dir($themesDir)) {
            $folders = \scandir($themesDir);
            foreach ($folders as $folder) {
                if ($folder === '.' || $folder === '..') {
                    continue;
                }
                if (\is_dir($themesDir . '/' . $folder)) {
                    // Convert folder name dynamically (e.g., kebab-case or snake_case to Title Case)
                    $friendlyName = \ucwords(\str_replace(['-', '_'], ' ', $folder));
                    if ($folder === 'default') {
                        $title = 'Default Corporate Theme';
                    } else {
                        $title = $friendlyName . ' Theme';
                    }
                    $options[$folder] = $title;
                }
            }
        }
        // Include themes registered dynamically via App::registerThemePath() (e.g. contributed
        // by a host project that installs Zero CMS Core via Composer) that don't live in this repo.
        foreach (App::getRegisteredThemeNames() as $folder) {
            if (!isset($options[$folder])) {
                $options[$folder] = \ucwords(\str_replace(['-', '_'], ' ', $folder)) . ' Theme';
            }
        }

        if (empty($options)) {
            $options = ['default' => 'Default Corporate Theme'];
        }
        return $options;
    }

    /**
     * Retrieves the timezone options attribute value.
     *
     * @return mixed Response output.
     */
    public static function getTimezoneOptions(): array
    {
        $timezones = \DateTimeZone::listIdentifiers();
        return \array_combine($timezones, $timezones);
    }

    /**
     * Check if a specific module is registered and enabled for this site tenant.
     */
    public function isModuleEnabled(string $module): bool
    {
        if (\in_array($module, self::$systemModules)) {
            return true;
        }

        if (empty($this->enabled_modules)) {
            // Default fallback: if empty/unseeded, default to all enabled for backward compatibility
            return true;
        }
        $modules = \json_decode($this->enabled_modules, true);
        if (!\is_array($modules)) {
            return true;
        }
        return \in_array($module, $modules);
    }

    /**
     * Get every configured setting value for a module on this site, merged over that module's
     * registered schema defaults (App::registerModuleSettings()) so a freshly-enabled module with
     * no saved settings yet still returns sensible values.
     *
     * @param string $moduleId
     * @return array
     */
    public function getModuleSettings(string $moduleId): array
    {
        $stored = [];
        if (!empty($this->settings)) {
            $decoded = \json_decode($this->settings, true);
            if (\is_array($decoded) && isset($decoded[$moduleId]) && \is_array($decoded[$moduleId])) {
                $stored = $decoded[$moduleId];
            }
        }

        $result = [];
        foreach (App::getModuleSettingsSchema($moduleId) as $key => $fieldConfig) {
            $result[$key] = \array_key_exists($key, $stored) ? $stored[$key] : ($fieldConfig['default'] ?? null);
        }
        return $result;
    }

    /**
     * Get a single setting value for a module on this site, falling back to that field's
     * registered schema default (or $default if the field isn't registered at all).
     *
     * @param string $moduleId
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getModuleSetting(string $moduleId, string $key, $default = null)
    {
        $settings = $this->getModuleSettings($moduleId);
        return \array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    /**
     * Save a module's settings for this site, persisting into the shared 'settings' JSON column
     * alongside every other module's own settings (each module gets its own top-level key, so
     * saving one module's settings never touches another's).
     *
     * @param string $moduleId
     * @param array $values
     * @return void
     */
    public function saveModuleSettings(string $moduleId, array $values): void
    {
        $all = [];
        if (!empty($this->settings)) {
            $decoded = \json_decode($this->settings, true);
            if (\is_array($decoded)) {
                $all = $decoded;
            }
        }

        $all[$moduleId] = $values;
        $this->settings = \json_encode($all);
        $this->save();
    }

    /**
     * Registers the system module component definition dynamically.
     *
     * @param string $module Argument descriptor.
     * @return mixed Response output.
     */
    public static function registerSystemModule(string $module)
    {
        if (!\in_array($module, self::$systemModules)) {
            self::$systemModules[] = $module;
        }
    }
}
