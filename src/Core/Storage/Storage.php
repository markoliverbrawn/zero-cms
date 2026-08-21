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
    protected static $envRoot = null;

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
     * Get the configured storage root.
     *
     * Resolution order: an explicitly registered root, then a STORAGE_ROOT environment variable,
     * then APPLICATION_ROOT. The environment variable is the non-programmatic equivalent of
     * setRoot(), for contexts that cannot call it before the storage layer is first touched --
     * a CLI process such as bin/seed, whose behaviour includes wiping the uploads tree, and which
     * therefore has to be pointable at a throwaway root when it is being exercised by a test
     * rather than run for real.
     *
     * @return string
     */
    public static function getRoot(): string
    {
        if (self::$root !== null) {
            return self::$root;
        }

        if (self::$envRoot === null) {
            $configured = (string)Env::get('STORAGE_ROOT', '');
            self::$envRoot = $configured !== '' ? \rtrim($configured, '/') : APPLICATION_ROOT;
        }

        return self::$envRoot;
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
     * Get the absolute directory where derived image variants are cached on local disk.
     *
     * Deliberately a sibling of storage/uploads rather than a folder inside it: variants are a
     * disposable, regenerable cache, so keeping them out of the uploads tree means the media
     * library never lists them, Media::forceDelete() never trips over them, and the whole cache
     * can be discarded with a single recursive delete without endangering a user's originals.
     *
     * @return string
     */
    public static function getVariantsRoot(): string
    {
        return self::getRoot() . '/public/storage/variants';
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
     * Determine whether the active driver stores objects on this machine's own filesystem.
     *
     * Callers use this to decide whether a locally written cache file is durable (local driver)
     * or merely a per-instance hot cache that also needs pushing to the remote bucket.
     *
     * @return bool
     */
    public static function isLocalDriver(): bool
    {
        return self::getDriver() instanceof LocalStorageDriver;
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
     * Reads the raw bytes of a stored file through the active storage driver.
     *
     * @param string $path Argument descriptor.
     * @return string|null Response output, null when the object does not exist.
     */
    public static function read(string $path): ?string
    {
        return self::getDriver()->read($path);
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

    /**
     * Write raw bytes through the active driver *without* running the image optimizer.
     *
     * write() transparently re-encodes any image it is handed, which is the right default for
     * user uploads but actively harmful for content that is already an optimized derivative --
     * it would decode and re-compress a freshly generated variant, paying a second full GD pass
     * and a second generation of lossy quality loss for no benefit. Cache writers use this.
     *
     * @param string $path Argument descriptor.
     * @param string $content Argument descriptor.
     * @return bool Response output.
     */
    public static function writeRaw(string $path, string $content): bool
    {
        return self::getDriver()->write($path, $content);
    }
}
