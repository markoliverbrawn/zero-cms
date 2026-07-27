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

    public static function getSignedUrl(string $path, int $expires = 3600): string
    {
        return self::getDriver()->getSignedUrl($path, $expires);
    }

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
        if (!file_exists($filePath) || is_dir($filePath)) {
            return false;
        }

        $info = @getimagesize($filePath);
        if (!$info) {
            return false;
        }

        $mime = $info['mime'] ?? '';
        $srcImage = null;

        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $srcImage = @imagecreatefromjpeg($filePath);
                break;
            case 'image/png':
                $srcImage = @imagecreatefrompng($filePath);
                break;
            case 'image/webp':
                $srcImage = @imagecreatefromwebp($filePath);
                break;
            case 'image/gif':
                $srcImage = @imagecreatefromgif($filePath);
                break;
        }

        if (!$srcImage) {
            return false;
        }

        $width = imagesx($srcImage);
        $height = imagesy($srcImage);
        $maxDimension = 1200;

        $dstImage = $srcImage;
        $resized = false;

        if ($width > $maxDimension || $height > $maxDimension) {
            if ($width > $height) {
                $newWidth = $maxDimension;
                $newHeight = (int)round(($height / $width) * $maxDimension);
            } else {
                $newHeight = $maxDimension;
                $newWidth = (int)round(($width / $height) * $maxDimension);
            }

            $dstImage = imagecreatetruecolor($newWidth, $newHeight);

            if ($mime === 'image/png' || $mime === 'image/webp') {
                imagealphablending($dstImage, false);
                imagesavealpha($dstImage, true);
                $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
                imagefill($dstImage, 0, 0, $transparent);
            }

            imagecopyresampled(
                $dstImage,
                $srcImage,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $width, $height
            );
            $resized = true;
        }

        // Determine target output extension format from destPath
        $ext = !empty($destPath) ? strtolower(pathinfo($destPath, PATHINFO_EXTENSION)) : '';
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if (!in_array($ext, ['jpg', 'png', 'webp', 'gif'])) {
            $ext = ($mime === 'image/png') ? 'png' : (($mime === 'image/webp') ? 'webp' : (($mime === 'image/gif') ? 'gif' : 'jpg'));
        }

        $success = false;
        switch ($ext) {
            case 'jpg':
                $success = @imagejpeg($dstImage, $filePath, 80);
                break;
            case 'png':
                $success = @imagepng($dstImage, $filePath, 7);
                break;
            case 'webp':
                $success = @imagewebp($dstImage, $filePath, 80);
                break;
            case 'gif':
                $success = @imagegif($dstImage, $filePath);
                break;
        }

        if ($resized) {
            imagedestroy($dstImage);
        }
        imagedestroy($srcImage);

        return $success;
    }

    public static function putFile(string $path, string $tmpFilePath): bool
    {
        self::optimizeImageFile($tmpFilePath, $path);
        return self::getDriver()->putFile($path, $tmpFilePath);
    }

    public static function rename(string $oldPath, string $newPath): bool
    {
        return self::getDriver()->rename($oldPath, $newPath);
    }

    public static function write(string $path, string $content): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $tmp = tempnam(sys_get_temp_dir(), 'img_opt_');
            if ($tmp) {
                file_put_contents($tmp, $content);
                if (self::optimizeImageFile($tmp, $path)) {
                    $content = file_get_contents($tmp);
                }
                @unlink($tmp);
            }
        }
        return self::getDriver()->write($path, $content);
    }
}
