<?php

namespace Zero\Support;

use Zero\Models\User;
use Zero\Core\App;

class I18n
{
    protected static $translations = [];
    protected static $customTranslations = [];
    protected static $currentLang = 'en';

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
        
        self::$currentLang = in_array($lang, ['en', 'es', 'mi', 'hr']) ? $lang : 'en';
        
        // 1. Load Core Translations
        $path = APPLICATION_ROOT . '/src/Lang/' . self::$currentLang . '.php';
        if (file_exists($path)) {
            self::$translations = require $path;
        } else {
            self::$translations = [];
        }

        // 2. Dynamically discover and merge all module-scoped Lang translations!
        $modulesDir = APPLICATION_ROOT . '/src/Modules';
        if (is_dir($modulesDir)) {
            $folders = scandir($modulesDir);
            foreach ($folders as $folder) {
                if ($folder === '.' || $folder === '..') {
                    continue;
                }
                $moduleLangFile = $modulesDir . '/' . $folder . '/Lang/' . self::$currentLang . '.php';
                if (file_exists($moduleLangFile)) {
                    $moduleTranslations = require $moduleLangFile;
                    if (is_array($moduleTranslations)) {
                        self::$customTranslations[self::$currentLang] = array_merge(
                            self::$customTranslations[self::$currentLang] ?? [],
                            $moduleTranslations
                        );
                    }
                }
            }
        }
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
        self::$customTranslations[$lang] = array_merge(self::$customTranslations[$lang], $translations);
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
            $translated = str_replace('{' . $placeholder . '}', $value, $translated);
        }

        return $translated;
    }

    }
