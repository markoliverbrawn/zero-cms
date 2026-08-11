<?php

declare(strict_types=1);

/**
 * File: src/Core/Autoloader.php
 * Architectural Purpose: PSR-4-style namespace-to-directory autoloading, shared by every entry point.
 * Package: Zero\Core
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Core;

/**
 * Class Autoloader
 *
 * This file cannot be autoloaded itself — it must be require()'d directly by whichever entry
 * point bootstraps the application (public/index.php, src/Support/TestBootstrap.php, a seeder script, or a
 * host project's own front controller) before any Zero\ class is referenced.
 */
class Autoloader
{
    protected static $initialized = false;

    /**
     * Register the bundled Zero\ -> APPLICATION_ROOT/src namespace mapping. Safe to call more
     * than once; only registers the mapping the first time.
     *
     * @return void
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        self::registerNamespace('Zero\\', APPLICATION_ROOT . '/src');
    }

    /**
     * Register an additional PSR-4-style namespace-to-directory mapping. Lets a host project
     * embedding Zero as a git submodule autoload its own classes (e.g. modules contributed via
     * App::registerModulePath()) with one call instead of hand-rolling its own
     * spl_autoload_register callback.
     *
     * @param string $namespacePrefix e.g. 'Acme\\Modules\\'
     * @param string $baseDir Absolute directory the namespace prefix maps to.
     * @return void
     */
    public static function registerNamespace(string $namespacePrefix, string $baseDir): void
    {
        $prefix = \rtrim($namespacePrefix, '\\') . '\\';
        $baseDir = \rtrim($baseDir, '/');

        \spl_autoload_register(function ($class) use ($prefix, $baseDir) {
            $len = \strlen($prefix);
            if (\strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relativeClass = \substr($class, $len);
            $file = $baseDir . '/' . \str_replace('\\', '/', $relativeClass) . '.php';

            if (\file_exists($file)) {
                require_once $file;
            }
        });
    }
}
