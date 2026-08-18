<?php

declare(strict_types=1);

/**
 * File: src/Support/I18n.php
 * Architectural Purpose: Global diagnostic tools, cryptographic security handlers, SMTP email transmitters, and text helpers.
 * Package: Zero\Support
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Support;

use Zero\Core\App;
use Zero\Models\User;

/**
 * Class I18n
 *
 * Translation and localisation registry. Merges the core language dictionary with the per-module
 * dictionaries registered at bootstrap, resolves a key through t()/translate(), and converts
 * stored UTC timestamps into the viewing user's timezone for display.
 */
class I18n
{
    protected static $translations = [];
    protected static $customTranslations = [];
    protected static $currentLang = 'en';
    protected static $allowedLanguages = ['en', 'es', 'mi', 'hr'];
    protected static $langFiles = [];

    /**
     * Retrieve the active language code.
     */
    public static function getLang(): string
    {
        return self::$currentLang;
    }

    /**
     * Initialize the translation engine. Loads the dictionary corresponding to user preference.
     */
    public static function init()
    {
        $lang = 'en';
        if (!empty($_SESSION['user_id'])) {
            $prefs = User::getPreferencesForUser($_SESSION['user_id']);
            $lang = $prefs['language'] ?? 'en';
        }
        
        self::$currentLang = \in_array($lang, self::$allowedLanguages, true) ? $lang : 'en';

        // 1. Load Core Translations (a path registered via registerLangFile() takes precedence
        // over the bundled src/Lang/<lang>.php file, so a host project can ship its own
        // translation file for a language from outside this repo)
        $path = self::$langFiles[self::$currentLang] ?? (APPLICATION_ROOT . '/src/Lang/' . self::$currentLang . '.php');
        if (\file_exists($path)) {
            self::$translations = require $path;
        } else {
            self::$translations = [];
        }

        // 2. Dynamically discover and merge all module-scoped Lang translations, across the
        // bundled src/Modules directory and any directories contributed via
        // App::registerModulePath()
        foreach (App::getModuleSearchPaths() as $modulesDir => $namespacePrefix) {
            if (!\is_dir($modulesDir)) {
                continue;
            }
            $folders = \scandir($modulesDir);
            foreach ($folders as $folder) {
                if ($folder === '.' || $folder === '..') {
                    continue;
                }
                $moduleLangFile = $modulesDir . '/' . $folder . '/Lang/' . self::$currentLang . '.php';
                if (\file_exists($moduleLangFile)) {
                    $moduleTranslations = require $moduleLangFile;
                    if (\is_array($moduleTranslations)) {
                        self::$customTranslations[self::$currentLang] = \array_merge(
                            self::$customTranslations[self::$currentLang] ?? [],
                            $moduleTranslations
                        );
                    }
                }
            }
        }
    }

    /**
     * Register an additional language code as valid, alongside the bundled 'en', 'es', 'mi',
     * 'hr'. Without this, init() silently falls back to 'en' for any language code it doesn't
     * recognize — so a host project adding a new language (e.g. 'fr') must call this before
     * init() runs, or a user's language preference for that code is ignored.
     *
     * @param string $code
     * @return void
     */
    public static function registerLanguage(string $code): void
    {
        if (!\in_array($code, self::$allowedLanguages, true)) {
            self::$allowedLanguages[] = $code;
        }
    }

    /**
     * Register the absolute filesystem path to a core-level translation file for a language,
     * taking precedence over the bundled convention path (src/Lang/<code>.php). Lets a host
     * project ship its own translation file for a language entirely from outside this repo
     * (typically paired with registerLanguage() for a language Zero doesn't already ship).
     *
     * @param string $code
     * @param string $absoluteFilePath
     * @return void
     */
    public static function registerLangFile(string $code, string $absoluteFilePath): void
    {
        self::$langFiles[$code] = $absoluteFilePath;
    }

    /**
     * Localize a UTC date time string to the logged-in user's timezone preference.
     */
    public static function localizeDateTime(?string $utcDateTimeString, string $format = 'Y-m-d H:i:s'): string
    {
        if (empty($utcDateTimeString)) {
            return '';
        }

        $timezone = 'UTC';
        if (!empty($_SESSION['user_id'])) {
            $prefs = User::getPreferencesForUser($_SESSION['user_id']);
            $timezone = $prefs['timezone'] ?? 'UTC';
        }

        if ($timezone === 'UTC' || empty($timezone)) {
            $site = App::getCurrentSite();
            if ($site && !empty($site->timezone)) {
                $timezone = $site->timezone;
            }
        }

        try {
            $dt = new \DateTime($utcDateTimeString, new \DateTimeZone('UTC'));
            $dt->setTimezone(new \DateTimeZone($timezone));
            return $dt->format($format);
        } catch (\Exception $e) {
            return $utcDateTimeString;
        }
    }

    /**
     * Register dynamic custom translations at runtime (perfect for modular separation).
     */
    public static function register(string $lang, array $translations)
    {
        if (!isset(self::$customTranslations[$lang])) {
            self::$customTranslations[$lang] = [];
        }
        self::$customTranslations[$lang] = \array_merge(self::$customTranslations[$lang], $translations);
    }

    /**
     * Force re-initialization of language file (useful on save of settings).
     */
    public static function reset()
    {
        self::$translations = [];
        self::$customTranslations = [];
        self::init();
    }

    /**
     * Shortcut alias for translate.
     */
    public static function t(string $key, array $replacements = []): string
    {
        return self::translate($key, $replacements);
    }
/**
     * Translate a given key, falling back to the key itself if missing.
     */
    public static function translate(string $key, array $replacements = []): string
    {
        if (empty(self::$translations)) {
            self::init();
        }

        // Resolves key from local file, custom registered cache, or defaults to the key string
        $translated = self::$translations[$key] ?? self::$customTranslations[self::$currentLang][$key] ?? $key;

        foreach ($replacements as $placeholder => $value) {
            $translated = \str_replace('{' . $placeholder . '}', $value, $translated);
        }

        return $translated;
    }

    }
