<?php

declare(strict_types=1);

/**
 * File: src/Core/Storage/Storage.php
 * Architectural Purpose: Core bootstrapping, system environment configuration, and utility class of the framework.
 * Package: Zero\Core\Storage
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Core\Storage;

use Exception;
use Zero\Core\Env;

/**
 * Class Storage
 *
 * Static facade over whichever StorageDriver is configured. Lazily resolves a single driver
 * instance and forwards every filesystem operation to it, so calling code never depends on which
 * backend is active.
 */
class Storage
{
    protected static $driverInstance = null;
    protected static $root = null;

    /**
     * Register the absolute root directory under which this app's own local storage lives:
     * `<root>/public/storage/uploads` for public tenant uploads, `<root>/storage/private` for
     * non-web-accessible private storage. Defaults to APPLICATION_ROOT.
     *
     * Lets a host project that installs Zero CMS Core via Composer keep every uploaded file
     * entirely outside the vendor package (e.g. registered as its own project root) instead of
     * writing runtime user data into a git-tracked vendor directory.
     *
     * @param string $absoluteDir
     * @return void
     */
    public static function setRoot(string $absoluteDir): void
    {
        self::$root = \rtrim($absoluteDir, '/');
    }

    /**
     * Get the configured storage root, defaulting to APPLICATION_ROOT when none is registered.
     *
     * @return string
     */
    public static function getRoot(): string
    {
        return self::$root ?? APPLICATION_ROOT;
    }

    /**
     * Get the absolute directory where public tenant uploads live on local disk.
     *
     * @return string
     */
    public static function getUploadsRoot(): string
    {
        return self::getRoot() . '/public/storage/uploads';
    }

    /**
     * Get the absolute directory where non-web-accessible private files live on local disk.
     *
     * @return string
     */
    public static function getPrivateStorageRoot(): string
    {
        return self::getRoot() . '/storage/private';
    }

    /**
     * Clears all contents of the target directory recursively via the active driver.
     *
     * @param string $path Argument descriptor.
     * @return bool Response output.
     */
    public static function cleanDirectory(string $path): bool
    {
        return self::getDriver()->cleanDirectory($path);
    }

    /**
     * Deletes a specific file from storage using the active driver.
     *
     * @param string $path Argument descriptor.
     * @return bool Response output.
     */
    public static function delete(string $path): bool
    {
        return self::getDriver()->delete($path);
    }

    /**
     * Checks if a file exists using the active driver.
     *
     * @param string $path Argument descriptor.
     * @return bool Response output.
     */
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

    /**
     * Resolves the public URL pathway for a stored file using the active driver.
     *
     * @param string $path Argument descriptor.
     * @return string Response output.
     */
    public static function getUrl(string $path): string
    {
        return self::getDriver()->getUrl($path);
    }

    /**
     * Generates a secure, temporary signed URL path for file retrieval.
     *
     * @param string $path Argument descriptor.
     * @param int $expires Argument descriptor.
     * @return string Response output.
     */
    public static function getSignedUrl(string $path, int $expires = 3600): string
    {
        return self::getDriver()->getSignedUrl($path, $expires);
    }

    /**
     * Creates a directory pathway recursively using the active driver.
     *
     * @param string $path Argument descriptor.
     * @return bool Response output.
     */
    public static function makeDirectory(string $path): bool
    {
        return self::getDriver()->makeDirectory($path);
    }

    /**
     * Optimizes an image file on disk: resizes it to no larger than 1200px along its widest side,
     * and compresses it to a web-optimized filesize matching the target extension format.
     */
    protected static function optimizeImageFile(string $filePath, string $destPath = ''): bool
    {
        if (!\file_exists($filePath) || \is_dir($filePath)) {
            return false;
        }

        $info = @\getimagesize($filePath);
        if (!$info) {
            return false;
        }

        $mime = $info['mime'] ?? '';
        $srcImage = null;

        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $srcImage = @\imagecreatefromjpeg($filePath);
                break;
            case 'image/png':
                $srcImage = @\imagecreatefrompng($filePath);
                break;
            case 'image/webp':
                $srcImage = @\imagecreatefromwebp($filePath);
                break;
            case 'image/gif':
                $srcImage = @\imagecreatefromgif($filePath);
                break;
        }

        if (!$srcImage) {
            return false;
        }

        $width = \imagesx($srcImage);
        $height = \imagesy($srcImage);
        $maxDimension = 1200;

        $dstImage = $srcImage;
        $resized = false;

        if ($width > $maxDimension || $height > $maxDimension) {
            if ($width > $height) {
                $newWidth = $maxDimension;
                $newHeight = (int)\round(($height / $width) * $maxDimension);
            } else {
                $newHeight = $maxDimension;
                $newWidth = (int)\round(($width / $height) * $maxDimension);
            }

            $dstImage = \imagecreatetruecolor($newWidth, $newHeight);

            if ($mime === 'image/png' || $mime === 'image/webp') {
                \imagealphablending($dstImage, false);
                \imagesavealpha($dstImage, true);
                $transparent = \imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
                \imagefill($dstImage, 0, 0, $transparent);
            }

            \imagecopyresampled(
                $dstImage,
                $srcImage,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $width, $height
            );
            $resized = true;
        }

        // Determine target output extension format from destPath
        $ext = !empty($destPath) ? \strtolower(\pathinfo($destPath, PATHINFO_EXTENSION)) : '';
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if (!\in_array($ext, ['jpg', 'png', 'webp', 'gif'])) {
            $ext = ($mime === 'image/png') ? 'png' : (($mime === 'image/webp') ? 'webp' : (($mime === 'image/gif') ? 'gif' : 'jpg'));
        }

        $success = false;
        switch ($ext) {
            case 'jpg':
                $success = @\imagejpeg($dstImage, $filePath, 80);
                break;
            case 'png':
                $success = @\imagepng($dstImage, $filePath, 7);
                break;
            case 'webp':
                $success = @\imagewebp($dstImage, $filePath, 80);
                break;
            case 'gif':
                $success = @\imagegif($dstImage, $filePath);
                break;
        }

        if ($resized) {
            \imagedestroy($dstImage);
        }
        \imagedestroy($srcImage);

        return $success;
    }

    /**
     * Persists an uploaded file payload through the active storage driver.
     *
     * @param string $path Argument descriptor.
     * @param string $tmpFilePath Argument descriptor.
     * @return bool Response output.
     */
    public static function putFile(string $path, string $tmpFilePath): bool
    {
        self::optimizeImageFile($tmpFilePath, $path);
        return self::getDriver()->putFile($path, $tmpFilePath);
    }

    /**
     * Renames or moves a file path using the active storage driver.
     *
     * @param string $oldPath Argument descriptor.
     * @param string $newPath Argument descriptor.
     * @return bool Response output.
     */
    public static function rename(string $oldPath, string $newPath): bool
    {
        return self::getDriver()->rename($oldPath, $newPath);
    }

    /**
     * Writes raw string content into a file using the active storage driver.
     *
     * @param string $path Argument descriptor.
     * @param string $content Argument descriptor.
     * @return bool Response output.
     */
    public static function write(string $path, string $content): bool
    {
        $ext = \strtolower(\pathinfo($path, PATHINFO_EXTENSION));
        if (\in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $tmp = \tempnam(\sys_get_temp_dir(), 'img_opt_');
            if ($tmp) {
                \file_put_contents($tmp, $content);
                if (self::optimizeImageFile($tmp, $path)) {
                    $content = \file_get_contents($tmp);
                }
                @\unlink($tmp);
            }
        }
        return self::getDriver()->write($path, $content);
    }
}
