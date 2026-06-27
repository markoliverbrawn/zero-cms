<?php

namespace Zero\Core\Storage;

use Zero\Core\Env;
use Exception;

class Storage
{
    protected static $driverInstance = null;

    

    public static function cleanDirectory(string $path): bool
    {
        return self::getDriver()->cleanDirectory($path);
    }


    public static function delete(string $path): bool
    {
        return self::getDriver()->delete($path);
    }

    

    public static function exists(string $path): bool
    {
        return self::getDriver()->exists($path);
    }

    /**
     * Get the active storage driver instance.
     */
    public static function getDriver(): StorageDriver
    {
        if (self::$driverInstance === null) {
            $driverName = Env::get('STORAGE_DRIVER', 'local');
            if ($driverName === 'local') {
                require_once __DIR__ . '/LocalStorageDriver.php';
                self::$driverInstance = new LocalStorageDriver();
            } elseif ($driverName === 'gcs') {
                require_once __DIR__ . '/GoogleCloudStorageDriver.php';
                self::$driverInstance = new GoogleCloudStorageDriver();
            } elseif ($driverName === 's3') {
                require_once __DIR__ . '/AwsS3StorageDriver.php';
                self::$driverInstance = new AwsS3StorageDriver();
            } else {
                throw new Exception("Unsupported storage driver configured: {$driverName}");
            }
        }
        return self::$driverInstance;
    }

    

    public static function getUrl(string $path): string
    {
        return self::getDriver()->getUrl($path);
    }

    

    public static function makeDirectory(string $path): bool
    {
        return self::getDriver()->makeDirectory($path);
    }

    

    public static function putFile(string $path, string $tmpFilePath): bool
    {
        return self::getDriver()->putFile($path, $tmpFilePath);
    }

    

    public static function rename(string $oldPath, string $newPath): bool
    {
        return self::getDriver()->rename($oldPath, $newPath);
    }

    // Direct proxy static methods to the active driver for extreme ease of use!
    public static function write(string $path, string $content): bool
    {
        return self::getDriver()->write($path, $content);
    }

    }
