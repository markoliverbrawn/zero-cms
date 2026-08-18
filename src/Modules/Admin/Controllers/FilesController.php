<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Controllers/FilesController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Admin\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Interfaces\Controller;
use Zero\Modules\Admin\Services\FileManagerService;
use Zero\Support\Logger;
use Zero\Support\Security;

/**
 * Class FilesController
 *
 * Back-office media manager behind the /admin/files and /admin/list/files routes, covering browse,
 * upload, edit, move, and delete for stored assets.
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
        $uri = \parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
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
        } elseif (\preg_match('#^/admin/list/files/edit/([a-zA-Z0-9\-]+)$#', $uri, $matches)) {
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
            $ids = \explode(',', $idsInput);
            FileManagerService::deleteFiles($siteId, $ids);
            echo "OK";
            exit;
        }

        \http_response_code(400);
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
        \header('Content-Type: application/json');
        $siteId = App::getCurrentSiteId();

        // Return files and directories strictly filtered by active site_id and NOT deleted!
        $stmt = DB::query("SELECT * FROM media WHERE site_id = ? AND deleted_at IS NULL ORDER BY (mime = 'directory') DESC, created_at DESC", [$siteId]);
        $files = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo \json_encode($files);
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
        $folder = FileManagerService::sanitizeFolderPath($_GET['folder'] ?? '');

        if ($method === 'POST') {
            App::applyCsrfMiddleware();

            $action = $_POST['action'] ?? '';
            if ($action === 'create_folder') {
                $currentFolder = FileManagerService::sanitizeFolderPath($_POST['folder'] ?? '');
                $result = FileManagerService::createFolder($siteId, $currentFolder, $_POST['folder_name'] ?? '');
                if (!$result['success']) {
                    $_SESSION['error'] = $result['error'];
                }
                \header('Location: /admin/list/files' . (!empty($currentFolder) ? '?folder=' . \urlencode($currentFolder) : ''));
                exit;
            }

            // Normal file upload
            if (!isset($_FILES['file'])) {
                $_SESSION['error'] = 'File upload failed or no file selected.';
                \header('Location: /admin/list/files' . (!empty($folder) ? '?folder=' . \urlencode($folder) : ''));
                exit;
            }

            $currentFolder = FileManagerService::sanitizeFolderPath($_POST['folder'] ?? '');
            $result = FileManagerService::uploadFile($siteId, $_FILES['file'], $currentFolder);
            if (!$result['success']) {
                $_SESSION['error'] = $result['error'];
            }

            \header('Location: /admin/list/files' . (!empty($currentFolder) ? '?folder=' . \urlencode($currentFolder) : ''));
            exit;
        }

        // GET request: Fetch files and directories inside current folder strictly filtered by active site_id!
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $listing = FileManagerService::listFiles($siteId, $folder, $page);
        $files = $listing['files'];
        $hasMore = $listing['hasMore'];

        if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
            \header('Content-Type: application/json');
            echo \json_encode([
                'html' => FileManagerService::renderFileCardsHtml($files, $folder),
                'has_more' => $hasMore,
                'current_page' => $listing['page'],
                'total' => $listing['total']
            ]);
            exit;
        }

        App::render('admin/files/list', [
            'modelName' => 'files',
            'files' => $files,
            'folder' => $folder,
            'hasMore' => $hasMore,
            'currentPage' => $listing['page'],
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

        \header('Content-Type: application/json');

        $fileIdInput = $_POST['file_id'] ?? null;
        $targetFolderId = $_POST['target_folder_id'] ?? null;

        if (!$fileIdInput) {
            echo \json_encode(['success' => false, 'error' => 'Missing file ID(s).']);
            exit;
        }

        $result = FileManagerService::moveFiles($siteId, \explode(',', $fileIdInput), $targetFolderId);

        if ($result['destinationInvalid']) {
            echo \json_encode(['success' => false, 'error' => $result['errors'][0]]);
            exit;
        }

        if ($result['moved'] > 0) {
            echo \json_encode([
                'success' => true,
                'moved' => $result['moved'],
                'errors' => $result['errors']
            ]);
        } else {
            echo \json_encode([
                'success' => false,
                'error' => \implode(' ', $result['errors']) ?: 'No files were moved.'
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

        \header('Content-Type: application/json');

        if (!isset($_FILES['file'])) {
            echo \json_encode(['success' => false, 'error' => 'File upload failed or no file selected.']);
            exit;
        }

        $currentFolder = FileManagerService::sanitizeFolderPath($_POST['folder'] ?? '');
        $result = FileManagerService::uploadFile($siteId, $_FILES['file'], $currentFolder);

        if ($result['success']) {
            echo \json_encode(['success' => true, 'file' => $result['file']]);
        } else {
            echo \json_encode(['success' => false, 'error' => $result['error']]);
        }
        exit;
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
            \header('Location: /admin/list/files?error=file_not_found');
            exit;
        }

        $method = $_SERVER['REQUEST_METHOD'];
        if ($method === 'POST') {
            App::applyCsrfMiddleware();
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && \strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

            $title = \trim($_POST['title'] ?? '');

            // Start with existing DB values
            $filename = $fileRecord['filename'];
            $dbPath = $fileRecord['path'];
            $mime = $fileRecord['mime'];

            // Handle optional file re-upload (overwriting physical asset while keeping DB ID)
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $result = FileManagerService::replaceFile($siteId, $fileRecord, $_FILES['file']);
                if (!$result['success']) {
                    if ($isAjax) {
                        \header('Content-Type: application/json');
                        echo \json_encode(['success' => false, 'error' => $result['error']]);
                        exit;
                    }
                    $_SESSION['error'] = $result['error'];
                    \header("Location: /admin/list/files/edit/{$fileId}");
                    exit;
                }
                $filename = $result['filename'];
                $dbPath = $result['path'];
                $mime = $result['mime'];
            }

            $focusX = \max(0, \min(100, \intval($_POST['focus_x'] ?? 50)));
            $focusY = \max(0, \min(100, \intval($_POST['focus_y'] ?? 50)));

            // Update record in database
            DB::query(
                "UPDATE media SET title = ?, filename = ?, path = ?, mime = ?, focus_x = ?, focus_y = ?, updated_at = NOW() WHERE id = ? AND site_id = ?",
                [$title, $filename, $dbPath, $mime, $focusX, $focusY, $fileId, $siteId]
            );

            // Reset cached cropped images for this media item
            FileManagerService::clearCropCache($siteId, $fileId);

            Logger::log($_SESSION['user_id'] ?? null, 'update', 'files', $fileId, [
                'title' => $title ?: $filename
            ]);

            $submitAction = $_POST['submit_action'] ?? 'save_return';

            if ($isAjax) {
                \header('Content-Type: application/json');
                echo \json_encode([
                    'success' => true,
                    'id' => $fileId,
                    'folder' => $fileRecord['folder'] ?? '',
                    'new_path' => $dbPath,
                    'redirect' => '/admin/list/files' . (!empty($fileRecord['folder']) ? '?folder=' . \urlencode($fileRecord['folder']) : '')
                ]);
                exit;
            }

            $_SESSION['success'] = 'Media file updated successfully.';
            if ($submitAction === 'save_continue') {
                \header("Location: /admin/list/files/edit/{$fileId}");
            } else {
                \header('Location: /admin/list/files' . (!empty($fileRecord['folder']) ? '?folder=' . \urlencode($fileRecord['folder']) : ''));
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
