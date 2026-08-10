<?php
/**
 * File: src/Modules/Admin/Controllers/FilesController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Admin\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Support\Logger;
use Zero\Core\Storage\Storage;
use Zero\Support\Security;
use Zero\Interfaces\Controller;

/**
 * Class FilesController
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class FilesController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($param)
    {
        // Route multi-action files manager requests based on URI
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if ($uri === '/admin/files/json') {
            $this->handleJson();
        } elseif ($uri === '/admin/files/upload') {
            $this->handleUpload();
        } elseif ($uri === '/admin/files/move') {
            $this->handleMove();
        } elseif ($uri === '/admin/files/delete') {
            $this->handleDelete();
        } elseif ($uri === '/admin/list/files') {
            $this->handleList();
        } elseif (preg_match('#^/admin/list/files/edit/([a-zA-Z0-9\-]+)$#', $uri, $matches)) {
            $this->handleEdit($matches[1]);
        }
    }

    /**
     * Handle delete processing implementation helper.
     *
     * @return mixed Response output.
     */
    public function handleDelete()
    {
        App::applyAuthMiddleware();
        App::applyCsrfMiddleware();
        $siteId = App::getCurrentSiteId();

        $idsInput = $_POST['id'] ?? null;
        if ($idsInput) {
            $ids = explode(',', $idsInput);
            foreach ($ids as $id) {
                $id = trim($id);
                if (empty($id)) continue;

                // Guard deletion strictly to files belonging to the active site/domain and not deleted!
                $stmt = DB::query("SELECT * FROM media WHERE id = ? AND site_id = ? AND deleted_at IS NULL LIMIT 1", [$id, $siteId]);
                $fileRecord = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($fileRecord) {
                    if ($fileRecord['mime'] === 'directory') {
                        // Soft delete subdirectories and subfiles inside this folder recursively
                        $parentFolder = $fileRecord['folder'];
                        $dirName = $fileRecord['filename'];
                        $dirPath = !empty($parentFolder) ? $parentFolder . '/' . $dirName : $dirName;
                        
                        // Soft delete database records recursively for this site
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

                    // Soft delete DB record
                    DB::query("UPDATE media SET deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND site_id = ?", [$id, $siteId]);
                }
            }
            echo "OK";
            exit;
        }

        http_response_code(400);
        echo "Invalid file ID or permission denied";
        exit;
    }

    /**
     * Handle json processing implementation helper.
     *
     * @return mixed Response output.
     */
    public function handleJson()
    {
        App::applyAuthMiddleware();
        header('Content-Type: application/json');
        $siteId = App::getCurrentSiteId();

        // Return files and directories strictly filtered by active site_id and NOT deleted!
        $stmt = DB::query("SELECT * FROM media WHERE site_id = ? AND deleted_at IS NULL ORDER BY (mime = 'directory') DESC, created_at DESC", [$siteId]);
        $files = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode($files);
        exit;
    }

    /**
     * Handle list processing implementation helper.
     *
     * @return mixed Response output.
     */
    public function handleList()
    {
        App::applyAuthMiddleware();
        $siteId = App::getCurrentSiteId();
        
        $method = $_SERVER['REQUEST_METHOD'];
        $folder = $_GET['folder'] ?? '';
        
        // Sanitize folder path
        $folder = preg_replace('/[^a-zA-Z0-9_\-\/]/', '_', $folder);
        $folder = trim($folder, '/');

        if ($method === 'POST') {
            App::applyCsrfMiddleware();

            $action = $_POST['action'] ?? '';
            if ($action === 'create_folder') {
                $newFolderName = $_POST['folder_name'] ?? '';
                // Clean new folder name
                $newFolderName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $newFolderName);
                $currentFolder = $_POST['folder'] ?? '';
                $currentFolder = preg_replace('/[^a-zA-Z0-9_\-\/]/', '_', $currentFolder);
                $currentFolder = trim($currentFolder, '/');

                if (empty($newFolderName)) {
                    $_SESSION['error'] = 'Folder name cannot be empty or invalid.';
                } else {
                    // Check if folder already exists in this parent folder for the active site
                    $exists = DB::query("SELECT id FROM media WHERE folder = ? AND filename = ? AND mime = 'directory' AND site_id = ? AND deleted_at IS NULL LIMIT 1", [$currentFolder, $newFolderName, $siteId])->fetch();
                    if ($exists) {
                        $_SESSION['error'] = 'Folder already exists.';
                    } else {
                        $newFolderId = Security::uuidv7();
                        DB::query(
                            "INSERT INTO media (id, site_id, filename, path, mime, folder, created_at) VALUES (?, ?, ?, '', 'directory', ?, NOW())",
                            [$newFolderId, $siteId, $newFolderName, $currentFolder]
                        );
                        Logger::log($_SESSION['user_id'] ?? null, 'create', 'files', $newFolderId, [
                            'title' => $newFolderName . ' (Folder)'
                        ]);
                        
                        // Physically create physical directory as well to keep filesystem organized
                        $dirPath = APPLICATION_ROOT . '/public/storage/uploads/' . $siteId . (!empty($currentFolder) ? '/' . $currentFolder : '') . '/' . $newFolderName;
                        if (!Storage::exists($dirPath)) {
                            Storage::makeDirectory($dirPath);
                        }
                    }
                }
                header('Location: /admin/list/files' . (!empty($currentFolder) ? '?folder=' . urlencode($currentFolder) : ''));
                exit;
            }

            // Normal file upload
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['error'] = 'File upload failed or no file selected.';
                header('Location: /admin/list/files' . (!empty($folder) ? '?folder=' . urlencode($folder) : ''));
                exit;
            }

            $file = $_FILES['file'];
            $filename = basename($file['name']);
            
            // Clean filename and ensure uploads folder exists
            $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

            // SECURITY REMEDIATION: Strict Extension and MIME Type Whitelists
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'pdf', 'doc', 'docx', 'zip', 'txt', 'mp4'];
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp', 'application/pdf', 'application/zip', 'text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'video/mp4'];

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $detectedMime = mime_content_type($file['tmp_name']);

            if (!in_array($ext, $allowedExtensions) || !in_array($detectedMime, $allowedMimeTypes)) {
                $_SESSION['error'] = 'Forbidden file extension or invalid file type.';
                header('Location: /admin/list/files' . (!empty($folder) ? '?folder=' . urlencode($folder) : ''));
                exit;
            }
            
            $currentFolder = $_POST['folder'] ?? '';
            $currentFolder = preg_replace('/[^a-zA-Z0-9_\-\/]/', '_', $currentFolder);
            $currentFolder = trim($currentFolder, '/');

            $uploadsDir = APPLICATION_ROOT . '/public/storage/uploads/' . $siteId . (!empty($currentFolder) ? '/' . $currentFolder : '');
            if (!Storage::exists($uploadsDir)) {
                Storage::makeDirectory($uploadsDir);
            }

            // Ensure unique filename to prevent overwriting
            $targetPath = $uploadsDir . '/' . $filename;
            $info = pathinfo($filename);
            $counter = 1;
            while (Storage::exists($targetPath)) {
                $filename = $info['filename'] . '_' . $counter . '.' . ($info['extension'] ?? '');
                $targetPath = $uploadsDir . '/' . $filename;
                $counter++;
            }

            $mime = mime_content_type($file['tmp_name']);
            if ($mime === 'image/svg+xml' || $ext === 'svg') {
                if (!Security::sanitizeSvg($file['tmp_name'])) {
                    $_SESSION['error'] = 'Invalid SVG file or sanitization failed.';
                    header('Location: /admin/list/files' . (!empty($currentFolder) ? '?folder=' . urlencode($currentFolder) : ''));
                    exit;
                }
            }
            if (Storage::putFile($targetPath, $file['tmp_name'])) {
                $dbPath = Storage::getUrl($targetPath);

                $newFileId = Security::uuidv7();
                DB::query(
                    "INSERT INTO media (id, site_id, filename, path, mime, folder, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                    [$newFileId, $siteId, $filename, $dbPath, $mime, $currentFolder]
                );

                Logger::log($_SESSION['user_id'] ?? null, 'create', 'files', $newFileId, [
                    'title' => $filename
                ]);
            } else {
                $_SESSION['error'] = 'Could not save the uploaded file.';
            }

            header('Location: /admin/list/files' . (!empty($currentFolder) ? '?folder=' . urlencode($currentFolder) : ''));
            exit;
        }

        // GET request: Fetch files and directories inside current folder strictly filtered by active site_id!
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) {
            $page = 1;
        }
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // Query with LIMIT and OFFSET and deleted_at IS NULL
        $stmt = DB::query("SELECT * FROM media WHERE folder = ? AND site_id = ? AND deleted_at IS NULL ORDER BY (mime = 'directory') DESC, created_at DESC LIMIT $limit OFFSET $offset", [$folder, $siteId]);
        $files = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Get total count to determine if there are more and deleted_at IS NULL
        $totalStmt = DB::query("SELECT COUNT(*) as total FROM media WHERE folder = ? AND site_id = ? AND deleted_at IS NULL", [$folder, $siteId]);
        $totalResult = $totalStmt->fetch(\PDO::FETCH_ASSOC);
        $total = (int)($totalResult['total'] ?? 0);
        $hasMore = ($offset + count($files)) < $total;

        if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
            header('Content-Type: application/json');
            $html = '';
            $csrfToken = Security::csrfToken();
            foreach ($files as $f) {
                ob_start();
                $isImage = !empty($f['mime']) && str_starts_with($f['mime'], 'image/');
                $filename = $f['filename'] ?? '';
                $path = $f['path'] ?? '';
                $id = $f['id'] ?? '';
                $createdAt = $f['created_at'] ?? '';
                $mime = $f['mime'] ?? '';
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $csrf = $csrfToken;
                $currentFolder = $folder;
                include APPLICATION_ROOT . '/src/Modules/Admin/Views/files/card.php';
                $html .= ob_get_clean();
            }
            echo json_encode([
                'html' => $html,
                'has_more' => $hasMore,
                'current_page' => $page,
                'total' => $total
            ]);
            exit;
        }

        App::render('admin/files/list', [
            'modelName' => 'files',
            'files' => $files,
            'folder' => $folder,
            'hasMore' => $hasMore,
            'currentPage' => $page,
        ]);
        exit;
    }

    /**
     * Handle move processing implementation helper.
     *
     * @return mixed Response output.
     */
    public function handleMove()
    {
        App::applyAuthMiddleware();
        App::applyCsrfMiddleware();
        $siteId = App::getCurrentSiteId();
        
        header('Content-Type: application/json');

        $fileIdInput = $_POST['file_id'] ?? null;
        $targetFolderId = $_POST['target_folder_id'] ?? null;
        
        if (!$fileIdInput) {
            echo json_encode(['success' => false, 'error' => 'Missing file ID(s).']);
            exit;
        }

        $fileIds = explode(',', $fileIdInput);

        // Get the destination folder path
        $destinationFolder = '';
        if ($targetFolderId === 'parent') {
            // We'll calculate the parent folder for each file inside the loop since they might come from different folders!
        } else {
            // Guard: Get the destination folder record strictly belonging to this site and not deleted!
            $stmtFolder = DB::query("SELECT * FROM media WHERE id = ? AND mime = 'directory' AND site_id = ? AND deleted_at IS NULL LIMIT 1", [$targetFolderId, $siteId]);
            $folderRecord = $stmtFolder->fetch(\PDO::FETCH_ASSOC);
            if (!$folderRecord) {
                echo json_encode(['success' => false, 'error' => 'Destination folder not found or permission denied.']);
                exit;
            }
            $parentFolder = $folderRecord['folder'] ?? '';
            $dirName = $folderRecord['filename'];
            $destinationFolder = !empty($parentFolder) ? $parentFolder . '/' . $dirName : $dirName;
        }

        $movedCount = 0;
        $errors = [];

        foreach ($fileIds as $fileId) {
            $fileId = trim($fileId);
            if (empty($fileId)) continue;

            // Guard: Get the file record strictly belonging to this site and not deleted
            $stmt = DB::query("SELECT * FROM media WHERE id = ? AND site_id = ? AND deleted_at IS NULL LIMIT 1", [$fileId, $siteId]);
            $fileRecord = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$fileRecord || $fileRecord['mime'] === 'directory') {
                $errors[] = "File {$fileId} not found or is a directory.";
                continue;
            }

            $currentDestinationFolder = $destinationFolder;
            if ($targetFolderId === 'parent') {
                // Get current folder of the file, and strip the last directory part safely
                $currentFolder = $fileRecord['folder'] ?? '';
                $parts = explode('/', $currentFolder);
                array_pop($parts);
                $currentDestinationFolder = implode('/', $parts);
            }

            // Physically move the file on disk!
            $oldPhysicalPath = APPLICATION_ROOT . $fileRecord['path'];
            $newFilename = $fileRecord['filename'];
            
            $newPhysicalDir = APPLICATION_ROOT . '/public/storage/uploads/' . $siteId . (!empty($currentDestinationFolder) ? '/' . $currentDestinationFolder : '');
            if (!Storage::exists($newPhysicalDir)) {
                Storage::makeDirectory($newPhysicalDir);
            }

            $newPhysicalPath = $newPhysicalDir . '/' . $newFilename;
            
            // Handle name collisions in the new folder
            $info = pathinfo($newFilename);
            $counter = 1;
            while (Storage::exists($newPhysicalPath)) {
                $newFilename = $info['filename'] . '_' . $counter . '.' . ($info['extension'] ?? '');
                $newPhysicalPath = $newPhysicalDir . '/' . $newFilename;
                $counter++;
            }

            if (Storage::exists($oldPhysicalPath)) {
                if (Storage::rename($oldPhysicalPath, $newPhysicalPath)) {
                    $newDbPath = Storage::getUrl($newPhysicalPath);
                    
                    // Update database record strictly for this file and site
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
                // Database is out of sync, let's update database anyway strictly matching site_id
                $newDbPath = Storage::getUrl($newPhysicalPath);
                DB::query(
                    "UPDATE media SET filename = ?, path = ?, folder = ?, updated_at = NOW() WHERE id = ? AND site_id = ?",
                    [$newFilename, $newDbPath, $currentDestinationFolder, $fileId, $siteId]
                );
                $movedCount++;
            }
        }

        if ($movedCount > 0) {
            echo json_encode([
                'success' => true, 
                'moved' => $movedCount,
                'errors' => $errors
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'error' => implode(' ', $errors) ?: 'No files were moved.'
            ]);
        }
        exit;
    }

    /**
     * Handle upload processing implementation helper.
     *
     * @return mixed Response output.
     */
    public function handleUpload()
    {
        App::applyAuthMiddleware();
        App::applyCsrfMiddleware();
        $siteId = App::getCurrentSiteId();
        
        header('Content-Type: application/json');

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'File upload failed or no file selected.']);
            exit;
        }

        $file = $_FILES['file'];
        $filename = basename($file['name']);
        
        // Clean filename
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // SECURITY LOCKDOWN: Strict Extension and MIME Type whitelists on async uploads!
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'pdf', 'doc', 'docx', 'zip', 'txt', 'mp4'];
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp', 'application/pdf', 'application/zip', 'text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'video/mp4'];

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $detectedMime = mime_content_type($file['tmp_name']);

        if (!in_array($ext, $allowedExtensions) || !in_array($detectedMime, $allowedMimeTypes)) {
            echo json_encode(['success' => false, 'error' => 'Forbidden file extension or invalid file type.']);
            exit;
        }
        
        $currentFolder = $_POST['folder'] ?? '';
        $currentFolder = preg_replace('/[^a-zA-Z0-9_\-\/]/', '_', $currentFolder);
        $currentFolder = trim($currentFolder, '/');

        $uploadsDir = APPLICATION_ROOT . '/public/storage/uploads/' . $siteId . (!empty($currentFolder) ? '/' . $currentFolder : '');
        if (!Storage::exists($uploadsDir)) {
            Storage::makeDirectory($uploadsDir);
        }

        // Ensure unique filename to prevent overwriting
        $targetPath = $uploadsDir . '/' . $filename;
        $info = pathinfo($filename);
        $counter = 1;
        while (Storage::exists($targetPath)) {
            $filename = $info['filename'] . '_' . $counter . '.' . ($info['extension'] ?? '');
            $targetPath = $uploadsDir . '/' . $filename;
            $counter++;
        }

        $mime = mime_content_type($file['tmp_name']);
        if ($mime === 'image/svg+xml' || $ext === 'svg') {
            if (!Security::sanitizeSvg($file['tmp_name'])) {
                echo json_encode(['success' => false, 'error' => 'Invalid SVG file or sanitization failed.']);
                exit;
            }
        }
        if (Storage::putFile($targetPath, $file['tmp_name'])) {
            $dbPath = '/storage/uploads' . (!empty($currentFolder) ? '/' . $currentFolder : '') . '/' . $filename;

            $newFileId = Security::uuidv7();
            DB::query(
                "INSERT INTO media (id, site_id, filename, path, mime, folder, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [$newFileId, $siteId, $filename, $dbPath, $mime, $currentFolder]
            );

            Logger::log($_SESSION['user_id'] ?? null, 'create', 'files', $newFileId, [
                'title' => $filename
            ]);

            echo json_encode([
                'success' => true,
                'file' => [
                    'id' => $newFileId,
                    'filename' => $filename,
                    'path' => $dbPath,
                    'mime' => $mime,
                    'folder' => $currentFolder,
                    'created_at' => gmdate('Y-m-d H:i:s')
                ]
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'Could not save the uploaded file.']);
            exit;
        }
        }

        /**
         * Handle edit processing implementation helper.
         *
         * @param mixed $fileId Argument descriptor.
         * @return mixed Response output.
         */
        public function handleEdit($fileId)
        {
        App::applyAuthMiddleware();
        $siteId = App::getCurrentSiteId();

        // Retrieve file record strictly belonging to active site
        $stmt = DB::query("SELECT * FROM media WHERE id = ? AND site_id = ? AND deleted_at IS NULL LIMIT 1", [$fileId, $siteId]);
        $fileRecord = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$fileRecord) {
            header('Location: /admin/list/files?error=file_not_found');
            exit;
        }

        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            App::applyCsrfMiddleware();
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

            $title = trim($_POST['title'] ?? '');

            // Start with existing DB values
            $filename = $fileRecord['filename'];
            $dbPath = $fileRecord['path'];
            $mime = $fileRecord['mime'];

            // Handle optional file re-upload (overwriting physical asset while keeping DB ID)
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['file'];
                $newFilename = basename($file['name']);
                $newFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $newFilename);

                // Whitelists validation
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'pdf', 'doc', 'docx', 'zip', 'txt', 'mp4'];
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp', 'application/pdf', 'application/zip', 'text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'video/mp4'];

                $ext = strtolower(pathinfo($newFilename, PATHINFO_EXTENSION));
                $detectedMime = mime_content_type($file['tmp_name']);

                if (!in_array($ext, $allowedExtensions) || !in_array($detectedMime, $allowedMimeTypes)) {
                    $errorMsg = 'Forbidden file extension or invalid file type.';
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => $errorMsg]);
                        exit;
                    }
                    $_SESSION['error'] = $errorMsg;
                    header("Location: /admin/list/files/edit/{$fileId}");
                    exit;
                }

                // Delete the old physical file if it exists
                $oldPhysicalPath = APPLICATION_ROOT . $fileRecord['path'];
                if (Storage::exists($oldPhysicalPath) && is_file($oldPhysicalPath)) {
                    Storage::delete($oldPhysicalPath);
                }

                $currentFolder = $fileRecord['folder'] ?? '';
                $uploadsDir = APPLICATION_ROOT . '/public/storage/uploads/' . $siteId . (!empty($currentFolder) ? '/' . $currentFolder : '');
                if (!Storage::exists($uploadsDir)) {
                    Storage::makeDirectory($uploadsDir);
                }

                // Ensure unique name inside the target folder to prevent overwrite collusions
                $targetPath = $uploadsDir . '/' . $newFilename;
                $info = pathinfo($newFilename);
                $counter = 1;
                while (Storage::exists($targetPath)) {
                    $newFilename = $info['filename'] . '_' . $counter . '.' . ($info['extension'] ?? '');
                    $targetPath = $uploadsDir . '/' . $newFilename;
                    $counter++;
                }

                if ($detectedMime === 'image/svg+xml' || $ext === 'svg') {
                    if (!Security::sanitizeSvg($file['tmp_name'])) {
                        $errorMsg = 'Invalid SVG file or sanitization failed.';
                        if ($isAjax) {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => false, 'error' => $errorMsg]);
                            exit;
                        }
                        $_SESSION['error'] = $errorMsg;
                        header("Location: /admin/list/files/edit/{$fileId}");
                        exit;
                    }
                }
                if (Storage::putFile($targetPath, $file['tmp_name'])) {
                    $filename = $newFilename;
                    $dbPath = Storage::getUrl($targetPath);
                    $mime = $detectedMime;
                } else {
                    $errorMsg = 'Could not save the re-uploaded file.';
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => $errorMsg]);
                        exit;
                    }
                    $_SESSION['error'] = $errorMsg;
                    header("Location: /admin/list/files/edit/{$fileId}");
                    exit;
                }
            }

            $focusX = max(0, min(100, intval($_POST['focus_x'] ?? 50)));
            $focusY = max(0, min(100, intval($_POST['focus_y'] ?? 50)));

            // Update record in database
            DB::query(
                "UPDATE media SET title = ?, filename = ?, path = ?, mime = ?, focus_x = ?, focus_y = ?, updated_at = NOW() WHERE id = ? AND site_id = ?",
                [$title, $filename, $dbPath, $mime, $focusX, $focusY, $fileId, $siteId]
            );

            // Reset cached cropped images for this media item
            $cropsDir = APPLICATION_ROOT . "/public/storage/uploads/{$siteId}/_crops";
            if (file_exists($cropsDir)) {
                $pattern = "{$cropsDir}/crop_{$fileId}_*.jpg";
                $files = glob($pattern);
                if (is_array($files)) {
                    foreach ($files as $f) {
                        @unlink($f);
                    }
                }
            }

            Logger::log($_SESSION['user_id'] ?? null, 'update', 'files', $fileId, [
                'title' => $title ?: $filename
            ]);

            $submitAction = $_POST['submit_action'] ?? 'save_return';

            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'id' => $fileId,
                    'folder' => $fileRecord['folder'] ?? '',
                    'new_path' => $dbPath,
                    'redirect' => '/admin/list/files' . (!empty($fileRecord['folder']) ? '?folder=' . urlencode($fileRecord['folder']) : '')
                ]);
                exit;
            }

            $_SESSION['success'] = 'Media file updated successfully.';
            if ($submitAction === 'save_continue') {
                header("Location: /admin/list/files/edit/{$fileId}");
            } else {
                header('Location: /admin/list/files' . (!empty($fileRecord['folder']) ? '?folder=' . urlencode($fileRecord['folder']) : ''));
            }
            exit;
        }

        // GET request: Render the beautiful edit template view
        App::render('admin/files/edit', [
            'file' => $fileRecord,
            'csrf' => Security::csrfToken()
        ]);
        exit;
        }
        }
