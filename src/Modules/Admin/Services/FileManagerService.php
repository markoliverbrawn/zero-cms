<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Services/FileManagerService.php
 * Architectural Purpose: Shared media-library business logic (list/upload/move/delete/create-folder)
 * used by both the traditional session-driven file manager (FilesController) and the JSON REST API
 * (FilesApiController). Each caller keeps its own request parsing and response formatting; this
 * service owns only the parts that were previously duplicated byte-for-byte between them.
 * Package: Zero\Modules\Admin\Services
 */

namespace Zero\Modules\Admin\Services;

use Zero\Core\Storage\Storage;
use Zero\Database\DB;
use Zero\Support\ImageProcessor;
use Zero\Support\Logger;
use Zero\Support\Security;
use Zero\Support\VariantCache;

/**
 * Class FileManagerService
 */
class FileManagerService
{
    protected const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'pdf', 'doc', 'docx', 'zip', 'txt', 'mp4'];
    protected const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp', 'application/pdf', 'application/zip', 'text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'video/mp4'];

    /**
     * Clean a user-supplied folder path the same way every file-manager endpoint does.
     */
    public static function sanitizeFolderPath(?string $folder): string
    {
        $folder = \preg_replace('/[^a-zA-Z0-9_\-\/]/', '_', $folder ?? '');
        return \trim($folder, '/');
    }

    /**
     * Fetch a page of files/folders inside a given folder for a site, plus pagination metadata.
     *
     * @return array{files: array, total: int, hasMore: bool, page: int}
     */
    public static function listFiles(string $siteId, string $folder, int $page = 1, int $limit = 20): array
    {
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $limit;

        $stmt = DB::query("SELECT * FROM media WHERE folder = ? AND site_id = ? AND deleted_at IS NULL ORDER BY (mime = 'directory') DESC, created_at DESC LIMIT $limit OFFSET $offset", [$folder, $siteId]);
        $files = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalStmt = DB::query("SELECT COUNT(*) as total FROM media WHERE folder = ? AND site_id = ? AND deleted_at IS NULL", [$folder, $siteId]);
        $totalResult = $totalStmt->fetch(\PDO::FETCH_ASSOC);
        $total = (int)($totalResult['total'] ?? 0);
        $hasMore = ($offset + \count($files)) < $total;

        return ['files' => $files, 'total' => $total, 'hasMore' => $hasMore, 'page' => $page];
    }

    /**
     * Render the shared file-card partial for each file, exactly as both the traditional and API
     * file listing endpoints do for their ajax/infinite-scroll views.
     */
    public static function renderFileCardsHtml(array $files, string $folder): string
    {
        $html = '';
        $csrfToken = Security::csrfToken();
        foreach ($files as $f) {
            \ob_start();
            $isImage = !empty($f['mime']) && \str_starts_with($f['mime'], 'image/');
            $filename = $f['filename'] ?? '';
            $path = $f['path'] ?? '';
            $id = $f['id'] ?? '';
            $createdAt = $f['created_at'] ?? '';
            $mime = $f['mime'] ?? '';
            $ext = \strtolower(\pathinfo($filename, PATHINFO_EXTENSION));
            $csrf = $csrfToken;
            $currentFolder = $folder;
            include APPLICATION_ROOT . '/src/Modules/Admin/Views/files/card.php';
            $html .= \ob_get_clean();
        }
        return $html;
    }

    /**
     * Validate an uploaded file's extension/MIME type against the shared whitelist.
     *
     * @return string|null An error message if invalid, or null if the file passes.
     */
    protected static function validateUpload(array $file): ?string
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return 'File upload failed or no file selected.';
        }

        $ext = \strtolower(\pathinfo(\basename($file['name']), PATHINFO_EXTENSION));
        $detectedMime = \mime_content_type($file['tmp_name']);

        if (!\in_array($ext, self::ALLOWED_EXTENSIONS) || !\in_array($detectedMime, self::ALLOWED_MIME_TYPES)) {
            return 'Forbidden file extension or invalid file type.';
        }

        return null;
    }

    /**
     * Find a collision-free target path inside $dir for $filename, appending "_1", "_2", etc.
     * before the extension as needed.
     *
     * @return array{0: string, 1: string} [$targetPath, $finalFilename]
     */
    protected static function resolveUniqueTargetPath(string $dir, string $filename): array
    {
        $targetPath = $dir . '/' . $filename;
        $info = \pathinfo($filename);
        $counter = 1;
        while (Storage::exists($targetPath)) {
            $filename = $info['filename'] . '_' . $counter . '.' . ($info['extension'] ?? '');
            $targetPath = $dir . '/' . $filename;
            $counter++;
        }
        return [$targetPath, $filename];
    }

    /**
     * Upload a new file into the media library. `$file` is a single element from $_FILES.
     *
     * @return array{success: bool, error?: string, statusHint: int, file?: array}
     */
    public static function uploadFile(string $siteId, array $file, string $folder): array
    {
        $validationError = self::validateUpload($file);
        if ($validationError !== null) {
            $isMissing = $validationError === 'File upload failed or no file selected.';
            return ['success' => false, 'error' => $validationError, 'statusHint' => $isMissing ? 400 : 403];
        }

        $filename = \preg_replace('/[^a-zA-Z0-9._-]/', '_', \basename($file['name']));
        $ext = \strtolower(\pathinfo($filename, PATHINFO_EXTENSION));
        $detectedMime = \mime_content_type($file['tmp_name']);
        $folder = self::sanitizeFolderPath($folder);

        $uploadsDir = Storage::getUploadsRoot() . '/' . $siteId . (!empty($folder) ? '/' . $folder : '');
        if (!Storage::exists($uploadsDir)) {
            Storage::makeDirectory($uploadsDir);
        }

        [$targetPath, $filename] = self::resolveUniqueTargetPath($uploadsDir, $filename);

        if ($detectedMime === 'image/svg+xml' || $ext === 'svg') {
            if (!Security::sanitizeSvg($file['tmp_name'])) {
                return ['success' => false, 'error' => 'Invalid SVG file or sanitization failed.', 'statusHint' => 400];
            }
        }

        if (!Storage::putFile($targetPath, $file['tmp_name'])) {
            return ['success' => false, 'error' => 'Could not save the uploaded file.', 'statusHint' => 500];
        }

        $dbPath = Storage::getUrl($targetPath);
        $dimensions = self::probeDimensions($dbPath);
        $newFileId = Security::uuidv7();
        DB::query(
            "INSERT INTO media (id, site_id, filename, path, mime, folder, width, height, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [$newFileId, $siteId, $filename, $dbPath, $detectedMime, $folder, $dimensions['width'], $dimensions['height']]
        );

        Logger::log($_SESSION['user_id'] ?? null, 'create', 'files', $newFileId, [
            'title' => $filename
        ]);

        return [
            'success' => true,
            'statusHint' => 200,
            'file' => [
                'id' => $newFileId,
                'filename' => $filename,
                'path' => $dbPath,
                'mime' => $detectedMime,
                'folder' => $folder,
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'created_at' => \gmdate('Y-m-d H:i:s')
            ]
        ];
    }

    /**
     * Replace the physical file backing an existing media record (used by the edit form's
     * optional re-upload), keeping the same DB record ID.
     *
     * @return array{success: bool, error?: string, statusHint: int, filename?: string, path?: string, mime?: string}
     */
    public static function replaceFile(string $siteId, array $existingRecord, array $file): array
    {
        $validationError = self::validateUpload($file);
        if ($validationError !== null) {
            $isMissing = $validationError === 'File upload failed or no file selected.';
            return ['success' => false, 'error' => $validationError, 'statusHint' => $isMissing ? 400 : 403];
        }

        // Delete the old physical file if it exists. $existingRecord['path'] is whatever the
        // active storage driver's own getUrl() produced at upload time -- a DB-relative path for
        // the local driver, but a full external URL (https://storage.googleapis.com/... or
        // https://bucket.s3.region.amazonaws.com/...) for the GCS/S3 drivers. Each driver's own
        // resolvePath()/cleanPath() already knows how to turn its own path shape back into a
        // concrete address, so hand it the raw stored value rather than string-concatenating a
        // local filesystem root onto it (which corrupts non-local URLs) or gating on the raw
        // \is_file() (which bypasses the storage abstraction and is never true for a remote URL).
        if (Storage::exists($existingRecord['path'])) {
            Storage::delete($existingRecord['path']);
        }

        $newFilename = \preg_replace('/[^a-zA-Z0-9._-]/', '_', \basename($file['name']));
        $ext = \strtolower(\pathinfo($newFilename, PATHINFO_EXTENSION));
        $detectedMime = \mime_content_type($file['tmp_name']);
        $currentFolder = $existingRecord['folder'] ?? '';

        $uploadsDir = Storage::getUploadsRoot() . '/' . $siteId . (!empty($currentFolder) ? '/' . $currentFolder : '');
        if (!Storage::exists($uploadsDir)) {
            Storage::makeDirectory($uploadsDir);
        }

        [$targetPath, $newFilename] = self::resolveUniqueTargetPath($uploadsDir, $newFilename);

        if ($detectedMime === 'image/svg+xml' || $ext === 'svg') {
            if (!Security::sanitizeSvg($file['tmp_name'])) {
                return ['success' => false, 'error' => 'Invalid SVG file or sanitization failed.', 'statusHint' => 400];
            }
        }

        if (!Storage::putFile($targetPath, $file['tmp_name'])) {
            return ['success' => false, 'error' => 'Could not save the re-uploaded file.', 'statusHint' => 500];
        }

        $dbPath = Storage::getUrl($targetPath);
        $dimensions = self::probeDimensions($dbPath);

        return [
            'success' => true,
            'statusHint' => 200,
            'filename' => $newFilename,
            'path' => $dbPath,
            'mime' => $detectedMime,
            'width' => $dimensions['width'],
            'height' => $dimensions['height']
        ];
    }

    /**
     * Delete cached resized variants for a media item (used after replacing/re-focusing an image).
     *
     * Also sweeps the legacy uploads/{site}/_crops folder, which is where square thumbnails used
     * to be written before the variant cache moved out of the uploads tree. Installations
     * upgrading through this change will still have those files sitting among a user's own media,
     * and nothing writes them any more, so this is their only route to being reclaimed.
     */
    public static function clearVariantCache(string $siteId, string $mediaId): void
    {
        VariantCache::forget($siteId, $mediaId);

        $legacyCropsDir = Storage::getUploadsRoot() . "/{$siteId}/_crops";
        if (!\file_exists($legacyCropsDir)) {
            return;
        }
        $files = \glob("{$legacyCropsDir}/crop_{$mediaId}_*.jpg");
        if (\is_array($files)) {
            foreach ($files as $legacyFile) {
                if (\is_file($legacyFile) && !\unlink($legacyFile)) {
                    \error_log("File manager: could not remove legacy crop cache file {$legacyFile}");
                }
            }
        }
    }

    /**
     * Measure a stored image's intrinsic dimensions.
     *
     * Recorded at upload time so that markup can carry accurate width/height attributes -- which
     * lets a browser reserve the right amount of space before an image arrives, removing the
     * layout shift that otherwise happens as each one loads -- and so that variant sizing can
     * avoid upscaling without inspecting the file on every page render.
     *
     * Deliberately measured after the file has been stored rather than from the upload's
     * temporary copy: Storage::putFile() downscales oversized images on the way in, so the
     * temporary file's dimensions are not necessarily the ones that end up on disk.
     *
     * @param string $storedPath The stored path of the image.
     * @return array{width: int, height: int} Zeroes when the file is not a measurable image.
     */
    protected static function probeDimensions(string $storedPath): array
    {
        try {
            $bytes = Storage::read($storedPath);
        } catch (\Exception $exception) {
            \error_log('File manager: could not read stored file to measure it: ' . $exception->getMessage());

            return ['width' => 0, 'height' => 0];
        }

        if ($bytes === null || $bytes === '') {
            return ['width' => 0, 'height' => 0];
        }

        $probe = ImageProcessor::probe($bytes);

        return $probe === null
            ? ['width' => 0, 'height' => 0]
            : ['width' => $probe['width'], 'height' => $probe['height']];
    }

    /**
     * Create a new folder record (and its backing physical directory) inside a parent folder.
     *
     * @return array{success: bool, error?: string, folderId?: string, currentFolder?: string}
     */
    public static function createFolder(string $siteId, string $currentFolder, string $newFolderName): array
    {
        $currentFolder = self::sanitizeFolderPath($currentFolder);
        $newFolderName = \preg_replace('/[^a-zA-Z0-9_-]/', '_', $newFolderName);

        if (empty($newFolderName)) {
            return ['success' => false, 'error' => 'Folder name cannot be empty or invalid.', 'currentFolder' => $currentFolder];
        }

        $exists = DB::query("SELECT id FROM media WHERE folder = ? AND filename = ? AND mime = 'directory' AND site_id = ? AND deleted_at IS NULL LIMIT 1", [$currentFolder, $newFolderName, $siteId])->fetch();
        if ($exists) {
            return ['success' => false, 'error' => 'Folder already exists.', 'currentFolder' => $currentFolder];
        }

        $newFolderId = Security::uuidv7();
        DB::query(
            "INSERT INTO media (id, site_id, filename, path, mime, folder, created_at) VALUES (?, ?, ?, '', 'directory', ?, NOW())",
            [$newFolderId, $siteId, $newFolderName, $currentFolder]
        );
        Logger::log($_SESSION['user_id'] ?? null, 'create', 'files', $newFolderId, [
            'title' => $newFolderName . ' (Folder)'
        ]);

        $dirPath = Storage::getUploadsRoot() . '/' . $siteId . (!empty($currentFolder) ? '/' . $currentFolder : '') . '/' . $newFolderName;
        if (!Storage::exists($dirPath)) {
            Storage::makeDirectory($dirPath);
        }

        return ['success' => true, 'folderId' => $newFolderId, 'currentFolder' => $currentFolder];
    }

    /**
     * Move one or more files (or a single file out of its folder to "parent") to a destination
     * folder, physically renaming the underlying storage object and updating its DB record.
     *
     * @param array $fileIds
     * @param string|null $targetFolderId A media folder record ID, or the literal string 'parent'.
     * @return array{success: bool, moved: int, errors: array, destinationInvalid: bool}
     */
    public static function moveFiles(string $siteId, array $fileIds, ?string $targetFolderId): array
    {
        $destinationFolder = '';
        if ($targetFolderId !== 'parent') {
            $stmtFolder = DB::query("SELECT * FROM media WHERE id = ? AND mime = 'directory' AND site_id = ? AND deleted_at IS NULL LIMIT 1", [$targetFolderId, $siteId]);
            $folderRecord = $stmtFolder->fetch(\PDO::FETCH_ASSOC);
            if (!$folderRecord) {
                return [
                    'success' => false,
                    'moved' => 0,
                    'errors' => ['Destination folder not found or permission denied.'],
                    'destinationInvalid' => true
                ];
            }
            $parentFolder = $folderRecord['folder'] ?? '';
            $dirName = $folderRecord['filename'];
            $destinationFolder = !empty($parentFolder) ? $parentFolder . '/' . $dirName : $dirName;
        }

        $movedCount = 0;
        $errors = [];

        foreach ($fileIds as $fileId) {
            $fileId = \trim($fileId);
            if (empty($fileId)) {
                continue;
            }

            $stmt = DB::query("SELECT * FROM media WHERE id = ? AND site_id = ? AND deleted_at IS NULL LIMIT 1", [$fileId, $siteId]);
            $fileRecord = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$fileRecord || $fileRecord['mime'] === 'directory') {
                $errors[] = "File {$fileId} not found or is a directory.";
                continue;
            }

            $currentDestinationFolder = $destinationFolder;
            if ($targetFolderId === 'parent') {
                $currentFolder = $fileRecord['folder'] ?? '';
                $parts = \explode('/', $currentFolder);
                \array_pop($parts);
                $currentDestinationFolder = \implode('/', $parts);
            }

            // $fileRecord['path'] is the DB-relative path (e.g. /storage/uploads/{siteId}/file.ext,
            // no leading /public) -- pass it straight into Storage::exists()/rename() and let their
            // own path resolution handle it (see Storage::getRoot()/resolvePath() for why manually
            // prepending the root here would silently break this).
            $oldPath = $fileRecord['path'];
            $newFilename = $fileRecord['filename'];

            $newPhysicalDir = Storage::getUploadsRoot() . '/' . $siteId . (!empty($currentDestinationFolder) ? '/' . $currentDestinationFolder : '');
            if (!Storage::exists($newPhysicalDir)) {
                Storage::makeDirectory($newPhysicalDir);
            }

            [$newPhysicalPath, $newFilename] = self::resolveUniqueTargetPath($newPhysicalDir, $newFilename);

            if (Storage::exists($oldPath)) {
                if (Storage::rename($oldPath, $newPhysicalPath)) {
                    $newDbPath = Storage::getUrl($newPhysicalPath);
                    DB::query(
                        "UPDATE media SET filename = ?, path = ?, folder = ?, updated_at = NOW() WHERE id = ? AND site_id = ?",
                        [$newFilename, $newDbPath, $currentDestinationFolder, $fileId, $siteId]
                    );
                    Logger::log($_SESSION['user_id'] ?? null, 'update', 'files', $fileId, [
                        'title' => 'Moved ' . $fileRecord['filename'] . ' to ' . ($currentDestinationFolder ?: 'Root')
                    ]);
                    $movedCount++;
                } else {
                    $errors[] = "Failed to physically move {$fileRecord['filename']}.";
                }
            } else {
                // Database is out of sync with disk; update the DB record anyway.
                $newDbPath = Storage::getUrl($newPhysicalPath);
                DB::query(
                    "UPDATE media SET filename = ?, path = ?, folder = ?, updated_at = NOW() WHERE id = ? AND site_id = ?",
                    [$newFilename, $newDbPath, $currentDestinationFolder, $fileId, $siteId]
                );
                $movedCount++;
            }
        }

        return ['success' => $movedCount > 0, 'moved' => $movedCount, 'errors' => $errors, 'destinationInvalid' => false];
    }

    /**
     * Soft-delete one or more files or folders (recursively, for folders) belonging to a site.
     *
     * @return array{deleted: int}
     */
    public static function deleteFiles(string $siteId, array $fileIds): array
    {
        $deletedCount = 0;

        foreach ($fileIds as $id) {
            $id = \trim($id);
            if (empty($id)) {
                continue;
            }

            $stmt = DB::query("SELECT * FROM media WHERE id = ? AND site_id = ? AND deleted_at IS NULL LIMIT 1", [$id, $siteId]);
            $fileRecord = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$fileRecord) {
                continue;
            }

            if ($fileRecord['mime'] === 'directory') {
                // Soft delete subdirectories and subfiles inside this folder recursively
                $parentFolder = $fileRecord['folder'];
                $dirName = $fileRecord['filename'];
                $dirPath = !empty($parentFolder) ? $parentFolder . '/' . $dirName : $dirName;

                DB::query("UPDATE media SET deleted_at = NOW(), updated_at = NOW() WHERE (folder = ? OR folder LIKE ?) AND site_id = ? AND deleted_at IS NULL", [$dirPath, $dirPath . '/%', $siteId]);

                Logger::log($_SESSION['user_id'] ?? null, 'delete', 'files', $id, [
                    'title' => $dirName . ' (Folder and recursive contents)'
                ]);
            } else {
                // For soft deletes, we do not unlink physical files!
                Logger::log($_SESSION['user_id'] ?? null, 'delete', 'files', $id, [
                    'title' => $fileRecord['filename']
                ]);
            }

            DB::query("UPDATE media SET deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND site_id = ?", [$id, $siteId]);
            $deletedCount++;
        }

        return ['deleted' => $deletedCount];
    }
}
