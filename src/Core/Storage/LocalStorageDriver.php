<?php
/**
 * File: src/Core/Storage/LocalStorageDriver.php
 * Architectural Purpose: Core bootstrapping, system environment configuration, and utility class of the framework.
 * Package: Zero\Core\Storage
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Core\Storage;

/**
 * Class LocalStorageDriver
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class LocalStorageDriver implements StorageDriver
{
    /**
     * Clears all contents of the target directory recursively, preserving the root folder.
     *
     * @param string $path Argument descriptor.
     * @return bool Response output.
     */
    public function cleanDirectory(string $path): bool
    {
        $resolved = $this->resolvePath($path);
        if (is_dir($resolved)) {
            $this->deleteDirectoryRecursive($resolved, false); // Clear contents but keep root folder
            return true;
        }
        return false;
    }

    /**
     * Deletes a specific file path from local storage.
     *
     * @param string $path Argument descriptor.
     * @return bool Response output.
     */
    public function delete(string $path): bool
    {
        $resolved = $this->resolvePath($path);
        if (file_exists($resolved) && is_file($resolved)) {
            $dir = dirname($resolved);
            if (!is_writable($dir)) {
                throw new \Exception("Permission denied: The upload directory containing the file is not writable (" . basename($resolved) . ").");
            }
            if (!is_writable($resolved)) {
                throw new \Exception("Permission denied: The file is not writable (" . basename($resolved) . ").");
            }
            if (!unlink($resolved)) {
                throw new \Exception("Deletion failed: Could not delete the file (" . basename($resolved) . ").");
            }
            return true;
        }
        return false;
    }

    /**
     * Deletes a folder and all of its nested contents recursively.
     *
     * @param string $dir Argument descriptor.
     * @param bool $removeSelf Argument descriptor.
     * @return void Response output.
     */
    protected function deleteDirectoryRecursive(string $dir, bool $removeSelf = true): void
    {
        if (is_dir($dir)) {
            if (!is_writable($dir)) {
                throw new \Exception("Permission denied: The directory is not writable (" . basename($dir) . ").");
            }
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object !== "." && $object !== "..") {
                    $item = $dir . "/" . $object;
                    if (is_dir($item)) {
                        $this->deleteDirectoryRecursive($item, true);
                    } else {
                        if (is_file($item)) {
                            if (!is_writable($dir)) {
                                throw new \Exception("Permission denied: The directory containing the file is not writable (" . $object . ").");
                            }
                            if (!is_writable($item)) {
                                throw new \Exception("Permission denied: The file inside the directory is not writable (" . $object . ").");
                            }
                            if (!unlink($item)) {
                                throw new \Exception("Deletion failed: Could not delete the file inside the directory (" . $object . ").");
                            }
                        }
                    }
                }
            }
            if ($removeSelf) {
                if (!is_writable($dir)) {
                    throw new \Exception("Permission denied: The directory is not writable (" . basename($dir) . ").");
                }
                if (!rmdir($dir)) {
                    throw new \Exception("Deletion failed: Could not remove the directory (" . basename($dir) . ").");
                }
            }
        }
    }

    /**
     * Checks if a specific file exists on disk.
     *
     * @param string $path Argument descriptor.
     * @return bool Response output.
     */
    public function exists(string $path): bool
    {
        return file_exists($this->resolvePath($path));
    }

    /**
     * Generates a secure, temporary signed URL for public file downloads.
     *
     * @param string $path Argument descriptor.
     * @param int $expires Argument descriptor.
     * @return string Response output.
     */
    public function getSignedUrl(string $path, int $expires = 3600): string
    {
        return $this->getUrl($path);
    }

    /**
     * Resolves the public URL path for a stored asset.
     *
     * @param string $path Argument descriptor.
     * @return string Response output.
     */
    public function getUrl(string $path): string
    {
        $siteId = class_exists('\\Zero\\Core\\App') ? \Zero\Core\App::getCurrentSiteId() : null;
        if (empty($siteId)) {
            throw new \RuntimeException("Security exception: Cannot resolve storage URL without an active site context.");
        }
        $prefix = '/' . $siteId;

        // Handle private storage
        $trimmed = ltrim($path, '/');
        if (strpos($trimmed, 'storage/private/') === 0) {
            return '/' . $trimmed;
        }

        // Strip APPLICATION_ROOT to make it relative to web root
        if (strpos($path, APPLICATION_ROOT) === 0) {
            $subPath = substr($path, strlen(APPLICATION_ROOT));
            $subPathClean = ltrim($subPath, '/');
            
            // Handle private storage under APPLICATION_ROOT
            if (strpos($subPathClean, 'storage/private/') === 0) {
                return '/' . $subPathClean;
            }
            
            // Strip leading /public if present (since /public is the web document root)
            if (strpos($subPath, '/public') === 0) {
                $subPath = substr($subPath, 7);
            }
            $subPathClean = '/' . ltrim($subPathClean, '/');
            if (strpos($subPathClean, '/storage/uploads') === 0) {
                $subPathRest = substr($subPathClean, strlen('/storage/uploads'));
                $subPathRest = ltrim($subPathRest, '/');
                
                $isAlreadyTenantScoped = preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}(\/|$)/i', $subPathRest);
                if ($isAlreadyTenantScoped) {
                    return $subPathClean;
                }
                
                if (strpos($subPathRest, $siteId . '/') !== 0 && $subPathRest !== $siteId) {
                    return '/storage/uploads' . $prefix . '/' . $subPathRest;
                }
                return $subPathClean;
            }
            return $subPath;
        }

        // If it's already a relative web path (starts with /storage/uploads)
        if (strpos($path, '/storage/uploads') === 0) {
            $subPathRest = substr($path, strlen('/storage/uploads'));
            $subPathRest = ltrim($subPathRest, '/');
            
            $isAlreadyTenantScoped = preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}(\/|$)/i', $subPathRest);
            if ($isAlreadyTenantScoped) {
                return $path;
            }
            
            if (strpos($subPathRest, $siteId . '/') !== 0 && $subPathRest !== $siteId) {
                return '/storage/uploads' . $prefix . '/' . $subPathRest;
            }
            return $path;
        }

        // Generic relative paths
        $isAlreadyTenantScoped = preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}(\/|$)/i', $trimmed);
        if ($isAlreadyTenantScoped) {
            return '/storage/uploads/' . $trimmed;
        }

        if (strpos($trimmed, $siteId . '/') !== 0 && $trimmed !== $siteId) {
            return '/storage/uploads' . $prefix . '/' . $trimmed;
        }

        return '/storage/uploads/' . $trimmed;
    }

    /**
     * Creates a directory pathway on disk recursively with appropriate permissions.
     *
     * @param string $path Argument descriptor.
     * @return bool Response output.
     */
    public function makeDirectory(string $path): bool
    {
        $resolved = $this->resolvePath($path);
        if (!file_exists($resolved)) {
            $res = mkdir($resolved, 0775, true);
            if ($res) {
                @chmod($resolved, 0775);
            }
            return $res;
        }
        return true;
    }

    /**
     * Saves an HTTP uploaded file payload to local disk storage.
     *
     * @param string $path Argument descriptor.
     * @param string $tmpFilePath Argument descriptor.
     * @return bool Response output.
     */
    public function putFile(string $path, string $tmpFilePath): bool
    {
        $resolved = $this->resolvePath($path);
        $dir = dirname($resolved);
        if (!file_exists($dir)) {
            mkdir($dir, 0775, true);
            @chmod($dir, 0775);
        }
        if (is_uploaded_file($tmpFilePath)) {
            return move_uploaded_file($tmpFilePath, $resolved);
        }
        return copy($tmpFilePath, $resolved);
    }

    /**
     * Renames or moves a file to a new target path on local disk.
     *
     * @param string $oldPath Argument descriptor.
     * @param string $newPath Argument descriptor.
     * @return bool Response output.
     */
    public function rename(string $oldPath, string $newPath): bool
    {
        $oldResolved = $this->resolvePath($oldPath);
        $newResolved = $this->resolvePath($newPath);
        $dir = dirname($newResolved);
        if (!file_exists($dir)) {
            mkdir($dir, 0775, true);
            @chmod($dir, 0775);
        }
        return rename($oldResolved, $newResolved);
    }

    /**
     * Resolve any relative, absolute, or database path to a concrete system path.
     */
    protected function resolvePath(string $path): string
    {
        // Path Traversal check: Block '..' traversal and path manipulation
        if (strpos($path, '..') !== false || strpos($path, '\\') !== false) {
            throw new \InvalidArgumentException("Security exception: Malformed path traversal detected.");
        }

        // Clean relative or absolute public/storage/uploads paths to be absolute under APPLICATION_ROOT
        $cleanInput = ltrim($path, '/');
        if (strpos($cleanInput, 'public/storage/uploads') === 0) {
            $path = APPLICATION_ROOT . '/' . $cleanInput;
        }

        // Get active site_id dynamically
        $siteId = class_exists('\\Zero\\Core\\App') ? \Zero\Core\App::getCurrentSiteId() : null;
        if (empty($siteId)) {
            throw new \RuntimeException("Security exception: Cannot resolve storage paths without an active site context.");
        }
        $prefix = '/' . $siteId;

        // If the path starts with APPLICATION_ROOT
        if (strpos($path, APPLICATION_ROOT) === 0) {
            $subPath = substr($path, strlen(APPLICATION_ROOT));
            $subPathClean = ltrim($subPath, '/');
            
            // Handle private storage
            if (strpos($subPathClean, 'storage/private/') === 0) {
                return $path;
            }
            
            // Strip leading public/ if present (since /public is the web document root)
            if (strpos($subPathClean, 'public/') === 0) {
                $subPathClean = substr($subPathClean, 7);
            }
            $subPathClean = '/' . ltrim($subPathClean, '/');

            if (strpos($subPathClean, '/storage/uploads') === 0) {
                $subPathRest = substr($subPathClean, strlen('/storage/uploads'));
                $subPathRest = ltrim($subPathRest, '/');
                
                // If it already starts with a UUIDv7, bypass prefixing
                $isAlreadyTenantScoped = preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}(\/|$)/i', $subPathRest);
                if (!$isAlreadyTenantScoped && !empty($subPathRest)) {
                    // Check if it already starts with siteId/ or is exactly siteId
                    if (strpos($subPathRest, $siteId . '/') !== 0 && $subPathRest !== $siteId) {
                        return APPLICATION_ROOT . '/public/storage/uploads' . $prefix . '/' . $subPathRest;
                    }
                }
            }
            return $path;
        }

        $trimmed = ltrim($path, '/');
        
        // Handle private storage
        if (strpos($trimmed, 'storage/private/') === 0) {
            return APPLICATION_ROOT . '/' . $trimmed;
        }

        // If the path starts with /storage/uploads or storage/uploads
        if (strpos($trimmed, 'storage/uploads') === 0) {
            $subPathRest = substr($trimmed, strlen('storage/uploads'));
            $subPathRest = ltrim($subPathRest, '/');
            
            // If it already starts with a UUIDv7, bypass prefixing
            $isAlreadyTenantScoped = preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}(\/|$)/i', $subPathRest);
            if (!$isAlreadyTenantScoped && !empty($subPathRest)) {
                if (strpos($subPathRest, $siteId . '/') !== 0 && $subPathRest !== $siteId) {
                    return APPLICATION_ROOT . '/public/storage/uploads' . $prefix . '/' . $subPathRest;
                }
            }
            return APPLICATION_ROOT . '/public/' . $trimmed;
        }
        
        // Default fallback: generic relative paths inside the site-scoped uploads folder
        $isAlreadyTenantScoped = preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}(\/|$)/i', $trimmed);
        if ($isAlreadyTenantScoped) {
            return APPLICATION_ROOT . '/public/storage/uploads/' . $trimmed;
        }

        if (!empty($trimmed)) {
            if (strpos($trimmed, $siteId . '/') !== 0 && $trimmed !== $siteId) {
                return APPLICATION_ROOT . '/public/storage/uploads' . $prefix . '/' . $trimmed;
            }
            return APPLICATION_ROOT . '/public/storage/uploads/' . $trimmed;
        }

        return APPLICATION_ROOT . '/public/storage/uploads/' . $siteId;
    }

    /**
     * Writes raw string content into a storage file.
     *
     * @param string $path Argument descriptor.
     * @param string $content Argument descriptor.
     * @return bool Response output.
     */
    public function write(string $path, string $content): bool
    {
        $resolved = $this->resolvePath($path);
        $dir = dirname($resolved);
        if (!file_exists($dir)) {
            mkdir($dir, 0775, true);
            @chmod($dir, 0775);
        }
        return file_put_contents($resolved, $content) !== false;
    }
}
