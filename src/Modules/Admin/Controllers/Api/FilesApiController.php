<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Controllers/Api/FilesApiController.php
 * Architectural Purpose: REST API endpoint for the media library (list/upload/move/delete),
 * delegating shared business logic to FileManagerService (also used by the traditional
 * session-driven FilesController).
 * Package: Zero\Modules\Admin\Controllers\Api
 */

namespace Zero\Modules\Admin\Controllers\Api;

use Zero\Core\App;
use Zero\Modules\Admin\Services\FileManagerService;

/**
 * Class FilesApiController
 */
class FilesApiController extends AdminApiControllerBase
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $matches Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($matches)
    {
        $this->authenticate();
        $siteId = App::getCurrentSiteId();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $body = $this->parseBody();

        if ($method === 'GET') {
            $this->handleGetFiles($siteId);
        } elseif ($method === 'POST') {
            $this->handleUploadFile($siteId);
        } elseif ($method === 'PATCH') {
            $this->handleMoveFiles($siteId, $body);
        } elseif ($method === 'DELETE') {
            $this->handleDeleteFiles($siteId, $body);
        }

        $this->respond(['success' => false, 'error' => 'Endpoint not found or method not allowed'], 404);
    }

    /**
     * Handle get files processing implementation helper.
     *
     * @param mixed $siteId Argument descriptor.
     * @return mixed Response output.
     */
    protected function handleGetFiles($siteId)
    {
        $folder = FileManagerService::sanitizeFolderPath($_GET['folder'] ?? '');

        // Check if pagination/infinite scroll is requested via query param page
        if (isset($_GET['page'])) {
            $listing = FileManagerService::listFiles($siteId, $folder, (int)$_GET['page']);
            $this->respond([
                'success' => true,
                'html' => FileManagerService::renderFileCardsHtml($listing['files'], $folder),
                'has_more' => $listing['hasMore'],
                'current_page' => $listing['page'],
                'total' => $listing['total']
            ]);
        }

        // Return full JSON list (replaces old /admin/files/json)
        $stmt = \Zero\Database\DB::query("SELECT * FROM media WHERE site_id = ? AND deleted_at IS NULL ORDER BY (mime = 'directory') DESC, created_at DESC", [$siteId]);
        $this->respond($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Handle upload file processing implementation helper.
     *
     * @param mixed $siteId Argument descriptor.
     * @return mixed Response output.
     */
    protected function handleUploadFile($siteId)
    {
        if (!isset($_FILES['file'])) {
            $this->respond(['success' => false, 'error' => 'File upload failed or no file selected.'], 400);
        }

        $folder = FileManagerService::sanitizeFolderPath($_POST['folder'] ?? '');
        $result = FileManagerService::uploadFile($siteId, $_FILES['file'], $folder);

        if (!$result['success']) {
            $this->respond(['success' => false, 'error' => $result['error']], $result['statusHint']);
        }

        $this->respond(['success' => true, 'file' => $result['file']]);
    }

    /**
     * Handle move files processing implementation helper.
     *
     * @param mixed $siteId Argument descriptor.
     * @param mixed $body Argument descriptor.
     * @return mixed Response output.
     */
    protected function handleMoveFiles($siteId, $body)
    {
        $fileIdInput = $body['file_id'] ?? null;
        $targetFolderId = $body['target_folder_id'] ?? null;

        if (!$fileIdInput) {
            $this->respond(['success' => false, 'error' => 'Missing file ID(s)'], 400);
        }

        $fileIds = \is_array($fileIdInput) ? $fileIdInput : \explode(',', $fileIdInput);
        $result = FileManagerService::moveFiles($siteId, $fileIds, $targetFolderId);

        if ($result['destinationInvalid']) {
            $this->respond(['success' => false, 'error' => $result['errors'][0]], 404);
        }

        $this->respond([
            'success' => $result['moved'] > 0,
            'moved' => $result['moved'],
            'errors' => $result['errors']
        ]);
    }

    /**
     * Handle delete files processing implementation helper.
     *
     * @param mixed $siteId Argument descriptor.
     * @param mixed $body Argument descriptor.
     * @return mixed Response output.
     */
    protected function handleDeleteFiles($siteId, $body)
    {
        $idsInput = $body['id'] ?? null;
        if (!$idsInput) {
            $this->respond(['success' => false, 'error' => 'Missing ID(s)'], 400);
        }

        $ids = \is_array($idsInput) ? $idsInput : \explode(',', $idsInput);
        $result = FileManagerService::deleteFiles($siteId, $ids);

        $this->respond([
            'success' => $result['deleted'] > 0,
            'deleted' => $result['deleted']
        ]);
    }
}
