<?php

declare(strict_types=1);

/**
 * File: src/Core/Concerns/ManagesThemes.php
 * Architectural Purpose: The theme-path/theme-stylesheet registry and resolution chain (requested
 * theme -> 'default' -> registered fallbacks), letting a host project that installs Zero CMS Core
 * via Composer register its own themes from outside this repo. Extracted out of App.php.
 * Package: Zero\Core\Concerns
 */

namespace Zero\Core\Concerns;

/**
 * Trait ManagesThemes
 */
trait ManagesThemes
{
    protected static $themeFallbacks = [];
    protected static $themePaths = [];
    protected static $themeStylesheetFiles = [];
    protected static $themeStylesheets = [];
    protected static $moduleStylesheets = [];

    /**
     * Register an absolute filesystem path to a module's own frontend stylesheet, to be appended
     * to CssBundleController's compiled bundle whenever $moduleId is enabled for the requesting
     * site. Lets a module (e.g. Shop) contribute CSS the same dynamic way it already contributes
     * blocks/routes/views/theme-fallbacks, instead of CssBundleController hardcoding a per-module
     * conditional and file path for every module that needs frontend styling.
     *
     * @param string $moduleId
     * @param string $absoluteFilePath
     * @return void
     */
    public static function registerModuleStylesheet(string $moduleId, string $absoluteFilePath): void
    {
        self::$moduleStylesheets[] = ['module' => $moduleId, 'path' => $absoluteFilePath];
    }

    /**
     * Get every registered module stylesheet, e.g. for CssBundleController to filter by
     * $site->isModuleEnabled() and append to its compiled bundle.
     *
     * @return array<int, array{module: string, path: string}>
     */
    public static function getRegisteredModuleStylesheets(): array
    {
        return self::$moduleStylesheets;
    }

    /**
     * Get the registered stylesheet path for a theme.
     *
     * @param string $themeName
     * @return string|null
     */
    public static function getThemeStylesheet(string $themeName): ?string
    {
        if (isset(self::$themeStylesheets[$themeName])) {
            return self::$themeStylesheets[$themeName];
        }

        $path = "/assets/css/themes/{$themeName}/{$themeName}.css";
        if (\file_exists(APPLICATION_ROOT . '/public' . $path)) {
            return $path;
        }
        return null;
    }

    /**
     * Register the absolute filesystem path to a theme's source stylesheet, taking precedence
     * over the bundled convention path (public/assets/css/themes/<theme>/<theme>.css) when
     * CssBundleController compiles the theme's CSS bundle. Complements registerThemePath(): that
     * registry covers a theme's PHP view files, this one covers its source CSS file — letting a
     * host project keep both entirely outside the Zero vendor package.
     *
     * This is distinct from registerThemeStylesheet(), which registers a public URL for the
     * admin preview link rather than a filesystem path the CSS compiler reads from.
     *
     * @param string $themeName
     * @param string $absoluteFilePath
     * @return void
     */
    public static function registerThemeStylesheetFile(string $themeName, string $absoluteFilePath): void
    {
        self::$themeStylesheetFiles[$themeName] = $absoluteFilePath;
    }

    /**
     * Resolve the absolute filesystem path to a theme's source stylesheet, preferring a path
     * registered via registerThemeStylesheetFile() over the bundled convention path.
     *
     * @param string $themeName
     * @return string|null
     */
    public static function resolveThemeStylesheetFile(string $themeName): ?string
    {
        if (isset(self::$themeStylesheetFiles[$themeName])) {
            return self::$themeStylesheetFiles[$themeName];
        }

        $bundled = APPLICATION_ROOT . "/public/assets/css/themes/{$themeName}/{$themeName}.css";
        return \file_exists($bundled) ? $bundled : null;
    }

    /**
     * Registers the theme fallback component definition dynamically.
     *
     * @param string $themeName Argument descriptor.
     * @return mixed Response output.
     */
    public static function registerThemeFallback(string $themeName)
    {
        self::$themeFallbacks[] = $themeName;
    }

    /**
     * Register an absolute filesystem directory as the source for a theme name, taking
     * precedence over the bundled theme of the same name shipped inside this repo. Lets a
     * host project that installs Zero CMS Core via Composer keep its own themes entirely in its
     * own tree (e.g. registered from the host's own bootstrap, before App::bootstrap() runs)
     * instead of committing them inside the vendor package.
     *
     * @param string $themeName
     * @param string $absoluteDir
     * @return void
     */
    public static function registerThemePath(string $themeName, string $absoluteDir): void
    {
        self::$themePaths[$themeName] = \rtrim($absoluteDir, '/');
    }

    /**
     * Get the names of all themes registered via registerThemePath(), e.g. for building an
     * admin theme picker that also lists themes contributed from outside this repo.
     *
     * @return array<string>
     */
    public static function getRegisteredThemeNames(): array
    {
        return \array_keys(self::$themePaths);
    }

    /**
     * Resolve the absolute directory for a theme name, preferring a directory registered via
     * registerThemePath() over the bundled theme of the same name.
     *
     * @param string $themeName
     * @return string|null Absolute directory path, or null if the theme is not found anywhere.
     */
    public static function resolveThemeDir(string $themeName): ?string
    {
        if (isset(self::$themePaths[$themeName])) {
            return self::$themePaths[$themeName];
        }

        $bundled = APPLICATION_ROOT . '/src/Views/themes/' . $themeName;
        return \is_dir($bundled) ? $bundled : null;
    }

    /**
     * Resolve a theme-relative file (e.g. 'layout.php' or 'post.php') to an absolute path,
     * walking the standard theme fallback chain: the requested theme, then 'default', then any
     * dynamically registered module theme fallbacks (registerThemeFallback()). Each theme name
     * in the chain is resolved via resolveThemeDir(), so registered custom theme paths are
     * checked before bundled ones at every step.
     *
     * @param string $themeName
     * @param string $relativePath
     * @return string|null Absolute file path, or null if not found in any theme in the chain.
     */
    public static function resolveThemeFile(string $themeName, string $relativePath): ?string
    {
        $candidates = \array_unique(\array_merge([$themeName, 'default'], self::$themeFallbacks));
        $relativePath = \ltrim($relativePath, '/');

        foreach ($candidates as $candidate) {
            $dir = self::resolveThemeDir($candidate);
            if ($dir === null) {
                continue;
            }
            $file = $dir . '/' . $relativePath;
            if (\file_exists($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Register a stylesheet path dynamically for a theme.
     *
     * @param string $themeName
     * @param string $stylesheetPath
     * @return void
     */
    public static function registerThemeStylesheet(string $themeName, string $stylesheetPath): void
    {
        self::$themeStylesheets[$themeName] = $stylesheetPath;
    }

}
