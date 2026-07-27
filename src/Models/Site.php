<?php
// src/Models/Site.php

namespace Zero\Models;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Interfaces\Model;
use Zero\Models\Traits\CascadesDeletes;
use Zero\Models\Traits\IsModel;
use Zero\Support\I18n;

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
    protected static $fillable = ['name', 'domain', 'theme', 'enabled_modules', 'timezone', 'default_language', 'homepage_id', 'expires_at'];
    protected static $systemModules = ['admin', 'queue', 'security'];
    protected static array $cascadeDeletes = [
        User::class => 'site_id',
        Page::class => 'site_id',
        Media::class => 'site_id',
        PasswordReset::class => 'site_id',
        AuditLog::class => 'site_id',
        Post::class => 'site_id',
        Category::class => 'site_id',
        Product::class => 'site_id',
        Order::class => 'site_id',
        ForumBoard::class => 'site_id',
        Submission::class => 'site_id',
        SecurityAudit::class => 'site_id'
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
        $uploadDir = APPLICATION_ROOT . '/public/storage/uploads/' . $this->id;
        $this->deleteDirectoryRecursive($uploadDir);

        return $res;
    }

    /**
     * Helper to recursively delete a directory, all of its subdirectories, and files.
     */
    private function deleteDirectoryRecursive(string $dir): bool
    {
        if (!file_exists($dir)) {
            return true;
        }

        // Configure a temporary custom error handler to trap and convert filesystem warnings into exceptions,
        // avoiding any output printing (which would corrupt HTTP redirect headers).
        set_error_handler(function($errno, $errstr) {
            throw new \ErrorException($errstr, 0, $errno);
        });

        try {
            if (!is_dir($dir)) {
                return unlink($dir);
            }
            foreach (scandir($dir) as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                // Recurse without interrupting the loop if one file encounters a permission blockage
                $this->deleteDirectoryRecursive($dir . '/' . $item);
            }
            return rmdir($dir);
        } catch (\Exception $e) {
            // Rethrow descriptive file deletion or other failures to bubble out to the controller/user
            throw new \Exception("Deletion failed: Could not clean up tenant uploads folder. " . $e->getMessage());
        } finally {
            restore_error_handler();
        }
    }

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
        $uploadDir = APPLICATION_ROOT . '/public/storage/uploads/' . $this->id;
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
            if (class_exists($class)) {
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
            'created_at' => ['type' => 'datetime', 'label' => I18n::t('created_at'), 'editable' => false, 'listDisplay' => true],
            'updated_at' => ['type' => 'datetime', 'label' => I18n::t('updated_at'), 'editable' => false, 'listDisplay' => true],
        ];
    }

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

    public static function getThemeOptions(): array
    {
        $options = [];
        $themesDir = APPLICATION_ROOT . '/src/Views/themes';
        if (is_dir($themesDir)) {
            $folders = scandir($themesDir);
            foreach ($folders as $folder) {
                if ($folder === '.' || $folder === '..') {
                    continue;
                }
                if (is_dir($themesDir . '/' . $folder)) {
                    $title = match ($folder) {
                        'default' => 'Default Corporate Theme',
                        'guide' => 'Technical Guide Theme',
                        'portfolio' => 'Designer Portfolio Theme',
                        'shop' => 'Luxe Shop Theme',
                        default => ucfirst($folder) . ' Theme'
                    };
                    $options[$folder] = $title;
                }
            }
        }
        if (empty($options)) {
            $options = ['default' => 'Default Corporate Theme'];
        }
        return $options;
    }

    public static function getTimezoneOptions(): array
    {
        $timezones = \DateTimeZone::listIdentifiers();
        return array_combine($timezones, $timezones);
    }

    /**
     * Check if a specific module is registered and enabled for this site tenant.
     */
    public function isModuleEnabled(string $module): bool
    {
        if (in_array($module, self::$systemModules)) {
            return true;
        }

        if (empty($this->enabled_modules)) {
            // Default fallback: if empty/unseeded, default to all enabled for backward compatibility
            return true;
        }
        $modules = json_decode($this->enabled_modules, true);
        if (!is_array($modules)) {
            return true;
        }
        return in_array($module, $modules);
    }

    public static function registerSystemModule(string $module)
    {
        if (!in_array($module, self::$systemModules)) {
            self::$systemModules[] = $module;
        }
    }
}
