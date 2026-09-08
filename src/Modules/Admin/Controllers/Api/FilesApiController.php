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
        $search = \trim((string)($_GET['q'] ?? ''));

        // Every caller (the media library grid's infinite scroll, and the block editor's media
        // picker modal) must scope its request to a folder/page, so a growing library never comes
        // back as one unbounded row set.
        if (!isset($_GET['page'])) {
            $this->respond(['success' => false, 'error' => 'Missing required "page" parameter.'], 400);
        }

        $listing = FileManagerService::listFiles($siteId, $folder, (int)$_GET['page'], 20, $search);

        // The media picker modal wants raw file records to build its own selectable grid;
        // the main media library grid wants pre-rendered card HTML for its infinite scroll.
        if (($_GET['format'] ?? '') === 'json') {
            $this->respond([
                'success' => true,
                'files' => $listing['files'],
                'has_more' => $listing['hasMore'],
                'current_page' => $listing['page'],
                'total' => $listing['total']
            ]);
        }

        $this->respond([
            'success' => true,
            'html' => FileManagerService::renderFileCardsHtml($listing['files'], $folder),
            'has_more' => $listing['hasMore'],
            'current_page' => $listing['page'],
            'total' => $listing['total']
        ]);
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
