<?php

declare(strict_types=1);

/**
 * File: src/Core/Concerns/ManagesModules.php
 * Architectural Purpose: Module discovery/registration (bundled src/Modules plus any directories
 * contributed via registerModulePath()) and the module-derived lookups it powers. Extracted out
 * of App.php.
 * Package: Zero\Core\Concerns
 */

namespace Zero\Core\Concerns;

use Zero\Http\Router;
use Zero\Interfaces\Module as ModuleInterface;

/**
 * Trait ManagesModules
 */
trait ManagesModules
{
    protected static $modules = [];
    protected static $modulePaths = [];

    /**
     * Scan the src/Modules directory to automatically discover all active module classes
     * and register their custom namespaces and routes in the Router.
     */
    public static function discoverAndRegisterModules()
    {
        self::discoverModules();

        // Dynamically register each module's namespaces, routes, and views with central Router & App
        foreach (self::$modules as $module) {
            // Dynamically derive the namespace prefix using reflection under PSR-4
            $ref = new \ReflectionClass($module);
            $namespace = $ref->getNamespaceName() . '\\';
            Router::registerModuleNamespace($namespace, $module->getId());
            Router::register($module->getRoutes(), null, $module->getId());

            // Convention-based View Registration: Check if a /Views directory exists inside the module folder
            $moduleDir = \dirname($ref->getFileName());
            $viewsDir = $moduleDir . '/Views';
            if (\is_dir($viewsDir)) {
                self::registerViewDir($module->getId(), $viewsDir);
            }

            // Run optional initialization method on module (for dynamic block registrations, filters, etc.)
            if (\method_exists($module, 'init')) {
                $module->init();
            }
        }
    }

    /**
     * Discover modules processing implementation helper.
     *
     * @return mixed Response output.
     */
    public static function discoverModules()
    {
        if (!empty(self::$modules)) {
            return;
        }

        foreach (self::getModuleSearchPaths() as $modulesDir => $namespacePrefix) {
            if (!\is_dir($modulesDir)) {
                continue;
            }

            $folders = \scandir($modulesDir);
            foreach ($folders as $folder) {
                if ($folder === '.' || $folder === '..') {
                    continue;
                }

                $className = $namespacePrefix . $folder . '\\Module';
                if (\class_exists($className)) {
                    $module = new $className();
                    if ($module instanceof ModuleInterface) {
                        self::$modules[$module->getId()] = $module;
                    }
                }
            }
        }
    }

    /**
     * Register an additional directory to scan for modules during discoverModules(), alongside
     * the bundled src/Modules directory. Each subfolder found is expected to contain a
     * `Module.php` implementing ModuleInterface at `<namespacePrefix><FolderName>\Module`.
     *
     * Lets a host project embedding Zero as a git submodule contribute its own modules from
     * outside this repo. The host is responsible for making that namespace loadable (e.g. its
     * own spl_autoload_register callback) — this registry only tells discoverModules() where
     * to look and which namespace to probe. discoverModules() runs once and caches its result,
     * so this must be called before App::bootstrap().
     *
     * @param string $absoluteDir
     * @param string $namespacePrefix e.g. 'Acme\\Modules\\'
     * @return void
     */
    public static function registerModulePath(string $absoluteDir, string $namespacePrefix): void
    {
        self::$modulePaths[\rtrim($absoluteDir, '/')] = \rtrim($namespacePrefix, '\\') . '\\';
    }

    /**
     * Get every module search path (the bundled src/Modules directory plus any directories
     * contributed via registerModulePath()), keyed by absolute directory with its associated
     * namespace prefix as the value. Shared by discoverModules() and MigrationManager so both
     * see the exact same set of module roots — a host-registered module's migrations are
     * discovered the same way its Module class is.
     *
     * @return array<string, string>
     */
    public static function getModuleSearchPaths(): array
    {
        return \array_merge(
            [APPLICATION_ROOT . '/src/Modules' => 'Zero\\Modules\\'],
            self::$modulePaths
        );
    }

    /**
     * Retrieves the migration classes attribute value.
     *
     * @return mixed Response output.
     */
    public static function getMigrationClasses(): array
    {
        $classes = [];
        foreach (self::getModules() as $module) {
            $mig = $module->getMigrationClass();
            if ($mig) {
                $classes[] = $mig;
            }
        }
        return $classes;
    }

    /**
     * Retrieves the modules attribute value.
     *
     * @return mixed Response output.
     */
    public static function getModules(): array
    {
        self::discoverModules();
        return self::$modules;
    }

}
