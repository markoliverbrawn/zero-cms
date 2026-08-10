<?php
/**
 * File: src/Core/Storage/StorageDriver.php
 * Architectural Purpose: Core bootstrapping, system environment configuration, and utility class of the framework.
 * Package: Zero\Core\Storage
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Core\Storage;

/**
 * Interface StorageDriver
 *
 * Defines systemic behavioral interface contract mechanisms.
 */
interface StorageDriver
{
    /**
     * Clean all contents inside a directory.
     *
     * @param string $path The directory path.
     * @return bool
     */
    public function cleanDirectory(string $path): bool;

    /**
     * Delete a file.
     *
     * @param string $path The file path.
     * @return bool
     */
    public function delete(string $path): bool;

    /**
     * Check if a file or directory exists.
     *
     * @param string $path The path.
     * @return bool
     */
    public function exists(string $path): bool;

    /**
     * Get the public URL for a given file path.
     *
     * @param string $path The file path.
     * @return string
     */
    public function getUrl(string $path): string;

    /**
     * Get a secure, temporary signed URL for a private file.
     *
     * @param string $path The file path.
     * @param int $expires The expiry time in seconds.
     * @return string
     */
    public function getSignedUrl(string $path, int $expires = 3600): string;

    /**
     * Create a directory.
     *
     * @param string $path The directory path.
     * @return bool
     */
    public function makeDirectory(string $path): bool;

    /**
     * Store an uploaded file.
     *
     * @param string $path The destination path.
     * @param string $tmpFilePath The temporary file path.
     * @return bool
     */
    public function putFile(string $path, string $tmpFilePath): bool;

    /**
     * Rename or move a file or directory.
     *
     * @param string $oldPath The original path.
     * @param string $newPath The target path.
     * @return bool
     */
    public function rename(string $oldPath, string $newPath): bool;

    /**
     * Write raw content to a file.
     *
     * @param string $path The destination path (relative or absolute).
     * @param string $content The file content.
     * @return bool
     */
    public function write(string $path, string $content): bool;
}
