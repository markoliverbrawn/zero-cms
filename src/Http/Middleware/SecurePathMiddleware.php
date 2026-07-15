<?php

namespace Zero\Http\Middleware;

use Zero\Support\Logger;

class SecurePathMiddleware
{
    /**
     * Intercept and validate file path safety, containment boundaries, and symlink protections.
     *
     * @param string $fileId The requested file's UUID identifier.
     * @param string $targetPath The relative/absolute file path retrieved from the database.
     * @param callable $next The callback to execute with the resolved physical path on success.
     */
    public static function handle(string $fileId, string $targetPath, callable $next)
    {
        // 1. Path Traversal Protection: Rejects unexpected '..' traversal sequences or backslashes
        if (strpos($targetPath, '..') !== false || strpos($targetPath, '\\') !== false) {
            Logger::log(null, 'suspicious_file_traversal', 'security', null, [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'requested_file_id' => $fileId,
                'path' => $targetPath
            ]);
            http_response_code(400);
            echo "Access denied: Malformed path traversal detected.";
            exit;
        }

        $physicalPath = APPLICATION_ROOT . $targetPath;

        // 2. Path Containment Boundary Protection: Resolves and ensures the absolute path resides strictly inside /storage root
        $storageRoot = realpath(APPLICATION_ROOT . '/storage');
        $realPhysicalPath = realpath($physicalPath);

        if ($realPhysicalPath === false || !self::isPathWithinStorageRoot($realPhysicalPath, $storageRoot)) {
            Logger::log(null, 'unauthorized_path_escape', 'security', null, [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'requested_file_id' => $fileId,
                'path' => $targetPath,
                'resolved_path' => $realPhysicalPath
            ]);
            http_response_code(403);
            echo "Access denied: Path escapes secure storage root boundary.";
            exit;
        }

        // 3. Symlink Access Protection: Explicitly blocks access through symbolic links
        if (is_link($physicalPath)) {
            Logger::log(null, 'symlink_access_attempt', 'security', null, [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'requested_file_id' => $fileId,
                'path' => $targetPath
            ]);
            http_response_code(400);
            echo "Access denied: Symbolic links are not permitted.";
            exit;
        }

        return $next($physicalPath);
    }

    public static function isPathWithinStorageRoot(string $candidatePath, ?string $storageRoot): bool
    {
        if (empty($candidatePath) || empty($storageRoot)) {
            return false;
        }

        $normalizedRoot = rtrim($storageRoot, DIRECTORY_SEPARATOR);
        $normalizedCandidate = rtrim($candidatePath, DIRECTORY_SEPARATOR);

        if ($normalizedRoot === '') {
            return false;
        }

        return $normalizedCandidate === $normalizedRoot
            || strpos($normalizedCandidate, $normalizedRoot . DIRECTORY_SEPARATOR) === 0;
    }
}
