<?php

declare(strict_types=1);

/**
 * File: src/Core/Env.php
 * Architectural Purpose: Core bootstrapping, system environment configuration, and utility class of the framework.
 * Package: Zero\Core
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Core;

/**
 * Class Env
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class Env
{
    protected static $data = null;

    /**
     * Retrieves the  attribute value.
     *
     * @param mixed $key Argument descriptor.
     * @param mixed $default Argument descriptor.
     * @return mixed Response output.
     */
    public static function get($key, $default = null)
    {
        if (self::$data === null) self::load(\getcwd());
        
        // Inspect native getenv environment variables first, then fallback to loaded .env file
        $val = \getenv($key);
        if ($val !== false) {
            return $val;
        }
        
        return isset(self::$data[$key]) ? self::$data[$key] : $default;
    }

    /**
     * Load processing implementation helper.
     *
     * @param mixed $path Argument descriptor.
     * @return mixed Response output.
     */
    public static function load($path)
    {
        if (self::$data !== null) return self::$data;
        $file = $path . '/.env';
        $data = [];
        if (!\file_exists($file)) return $data;
        $lines = \file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = \trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (\strpos($line, '=') === false) continue;
            list($k, $v) = \explode('=', $line, 2);
            $k = \trim($k);
            $v = \trim($v);
            $v = \trim($v, "\"'");
            $data[$k] = $v;
        }
        self::$data = $data;
        return $data;
    }

    }
