<?php

namespace Zero\Core\Storage;

class LocalStorageDriver implements StorageDriver
{
    public function cleanDirectory(string $path): bool
    {
        $resolved = $this->resolvePath($path);
        if (is_dir($resolved)) {
            $this->deleteDirectoryRecursive($resolved, false); // Clear contents but keep root folder
            return true;
        }
        return false;
    }

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

    public function exists(string $path): bool
    {
        return file_exists($this->resolvePath($path));
    }

    public function getUrl(string $path): string
    {
        $siteId = class_exists('\\Zero\\Core\\App') ? \Zero\Core\App::getCurrentSiteId() : null;
        $prefix = !empty($siteId) ? '/' . $siteId : '';

        // If it's already a relative web path (starts with /storage/uploads)
        if (strpos($path, '/storage/uploads') === 0) {
            $subPathRest = substr($path, strlen('/storage/uploads'));
            $subPathRest = ltrim($subPathRest, '/');
            if (!empty($siteId) && strpos($subPathRest, $siteId) !== 0) {
                return '/storage/uploads' . $prefix . '/' . $subPathRest;
            }
            return $path;
        }
        
        // Strip APPLICATION_ROOT to make it relative to web root
        if (strpos($path, APPLICATION_ROOT) === 0) {
            $subPath = substr($path, strlen(APPLICATION_ROOT));
            // Strip leading /public if present (since /public is the web document root)
            if (strpos($subPath, '/public') === 0) {
                $subPath = substr($subPath, 7);
            }
            if (strpos($subPath, '/storage/uploads') === 0) {
                $subPathRest = substr($subPath, strlen('/storage/uploads'));
                $subPathRest = ltrim($subPathRest, '/');
                if (!empty($siteId) && strpos($subPathRest, $siteId) !== 0) {
                    return '/storage/uploads' . $prefix . '/' . $subPathRest;
                }
                return $subPath;
            }
            return $subPath;
        }

        $trimmed = ltrim($path, '/');
        if (!empty($siteId) && strpos($trimmed, $siteId) !== 0) {
            return '/storage/uploads' . $prefix . '/' . $trimmed;
        }

        return '/storage/uploads/' . $trimmed;
    }


    public function getSignedUrl(string $path, int $expires = 3600): string
    {
        return $this->getUrl($path);
    }

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
        $prefix = !empty($siteId) ? '/' . $siteId : '';

        // If the path starts with APPLICATION_ROOT, check if it already contains the site_id prefix.
        if (strpos($path, APPLICATION_ROOT) === 0) {
            $subPath = substr($path, strlen(APPLICATION_ROOT));
            // Strip leading public/ if present (since /public is the web document root)
            $subPathClean = ltrim($subPath, '/');
            if (strpos($subPathClean, 'public/') === 0) {
                $subPathClean = substr($subPathClean, 7);
            }
            $subPathClean = '/' . ltrim($subPathClean, '/');

            if (strpos($subPathClean, '/storage/uploads') === 0) {
                $subPathRest = substr($subPathClean, strlen('/storage/uploads'));
                $subPathRest = ltrim($subPathRest, '/');
                
                // If it already starts with a UUIDv7, bypass dynamic site prefixing (e.g. during permanent cross-tenant purges)
                $isAlreadyTenantScoped = preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', $subPathRest);
                if (!$isAlreadyTenantScoped && !empty($siteId) && !empty($subPathRest) && strpos($subPathRest, $siteId) !== 0) {
                    return APPLICATION_ROOT . '/public/storage/uploads' . $prefix . '/' . $subPathRest;
                }
            }
            return $path;
        }

        // If the path starts with /storage/uploads, map it relative to APPLICATION_ROOT
        if (strpos($path, '/storage/uploads') === 0) {
            $subPathRest = substr($path, strlen('/storage/uploads'));
            $subPathRest = ltrim($subPathRest, '/');
            
            // If it already starts with a UUIDv7, bypass dynamic site prefixing (e.g. during permanent cross-tenant purges)
            $isAlreadyTenantScoped = preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', $subPathRest);
            if (!$isAlreadyTenantScoped && !empty($siteId) && !empty($subPathRest) && strpos($subPathRest, $siteId) !== 0) {
                return APPLICATION_ROOT . '/public/storage/uploads' . $prefix . '/' . $subPathRest;
            }
            return APPLICATION_ROOT . '/public' . $path;
        }
        
        // Handle generic relative storage/uploads paths
        $trimmed = ltrim($path, '/');
        if (strpos($trimmed, 'storage/uploads') === 0) {
            $subPathRest = substr($trimmed, strlen('storage/uploads'));
            $subPathRest = ltrim($subPathRest, '/');
            
            // If it already starts with a UUIDv7, bypass dynamic site prefixing (e.g. during permanent cross-tenant purges)
            $isAlreadyTenantScoped = preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', $subPathRest);
            if (!$isAlreadyTenantScoped && !empty($siteId) && !empty($subPathRest) && strpos($subPathRest, $siteId) !== 0) {
                return APPLICATION_ROOT . '/public/storage/uploads' . $prefix . '/' . $subPathRest;
            }
            return APPLICATION_ROOT . '/public/' . $trimmed;
        }

        // Default fallback
        $isAlreadyTenantScoped = preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/i', $trimmed);
        if ($isAlreadyTenantScoped) {
            return APPLICATION_ROOT . '/public/storage/uploads/' . ltrim($trimmed, '/');
        }

        return APPLICATION_ROOT . '/public/storage/uploads' . $prefix . '/' . ltrim($trimmed, '/');
    }

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
