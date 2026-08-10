<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Controllers/Api/AdminApiController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Admin\Controllers\Api
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Admin\Controllers\Api;

use Zero\Core\App;
use Zero\Core\Storage\Storage;
use Zero\Core\Template;
use Zero\Database\DB;
use Zero\Http\Controllers\ApiController;
use Zero\Models\Media;
use Zero\Models\Traits\IsOrderable;
use Zero\Models\User;
use Zero\Services\AiService;
use Zero\Support\I18n;
use Zero\Support\Logger;
use Zero\Support\Security;

/**
 * Class AdminApiController
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class AdminApiController extends ApiController
{
    /**
     * Override authenticate() to enforce session-based and CSRF protection for back-office AJAX calls.
     */
    protected function authenticate(): array
    {
        if (\session_status() === PHP_SESSION_NONE) {
            \session_start();
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            $this->respond([
                'success' => false,
                'error' => 'Unauthorized: Session expired or invalid'
            ], 401);
        }

        $user = DB::query("SELECT * FROM users WHERE id = ? LIMIT 1", [$userId])->fetch();
        if (!$user) {
            $this->respond([
                'success' => false,
                'error' => 'Unauthorized: User not found'
            ], 401);
        }

        // Apply CSRF validation for state-modifying requests (POST, PUT, PATCH, DELETE)
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (\in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            // Check HTTP headers first, then fallback to JSON body or POST variables
            $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '';
            
            if (empty($csrfToken)) {
                $rawBody = \file_get_contents('php://input');
                $jsonData = \json_decode($rawBody, true);
                $csrfToken = $jsonData['csrf'] ?? '';
            }

            if (empty($csrfToken) || !Security::csrfVerify($csrfToken)) {
                $this->respond([
                    'success' => false,
                    'error' => 'Forbidden: Invalid CSRF Token'
                ], 403);
            }
        }

        return $user;
    }

    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $matches Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($matches)
    {
        // 1. Authenticate using session and CSRF checks
        $user = $this->authenticate();
        $siteId = App::getCurrentSiteId();
        
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = \parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

        // Parse JSON or standard post body
        $raw = \file_get_contents('php://input');
        $body = \json_decode($raw, true) ?? $_POST;

        // Route: /api/v1/admin/files
        if (\preg_match('#^/api/v1/admin/files/?$#', $uri)) {
            if ($method === 'GET') {
                $this->handleGetFiles($siteId);
            } elseif ($method === 'POST') {
                $this->handleUploadFile($siteId);
            } elseif ($method === 'PATCH') {
                $this->handleMoveFiles($siteId, $body);
            } elseif ($method === 'DELETE') {
                $this->handleDeleteFiles($siteId, $body);
            }
        }

        // Route: /api/v1/admin/models/([a-zA-Z0-9_-]+) (POST/create)
        if (\preg_match('#^/api/v1/admin/models/([a-zA-Z0-9_-]+)/?$#', $uri, $routeMatches)) {
            $modelName = $routeMatches[1];
            if ($method === 'POST') {
                $this->handleSaveModel($modelName, null, $body);
            }
        }

        // Route: /api/v1/admin/models/([a-zA-Z0-9_-]+)/reorder (POST)
        if (\preg_match('#^/api/v1/admin/models/([a-zA-Z0-9_-]+)/reorder/?$#', $uri, $routeMatches)) {
            $modelName = $routeMatches[1];
            if ($method === 'POST') {
                $this->handleReorderModel($modelName, $body);
            }
        }

        // Route: /api/v1/admin/models/([a-zA-Z0-9_-]+)/([a-zA-Z0-9\-]+)/cascade-check (GET)
        if (\preg_match('#^/api/v1/admin/models/([a-zA-Z0-9_-]+)/([a-zA-Z0-9\-]+)/cascade-check$#', $uri, $routeMatches)) {
            $modelName = $routeMatches[1];
            $id = $routeMatches[2];
            if ($method === 'GET') {
                $this->handleCascadeCheck($modelName, $id);
            }
        }

        // Route: /api/v1/admin/models/([a-zA-Z0-9_-]+)/([a-zA-Z0-9\-]+) (PATCH/edit or DELETE/delete)
        if (\preg_match('#^/api/v1/admin/models/([a-zA-Z0-9_-]+)/([a-zA-Z0-9\-]+)$#', $uri, $routeMatches)) {
            $modelName = $routeMatches[1];
            $id = $routeMatches[2];
            
            if ($method === 'DELETE') {
                $this->handleDeleteModel($modelName, $id);
            } elseif ($method === 'PATCH' || $method === 'POST') {
                $this->handleSaveModel($modelName, $id, $body);
            }
        }

        // Route: /api/v1/admin/audit-logs/purge
        if (\preg_match('#^/api/v1/admin/audit-logs/purge/?$#', $uri)) {
            if ($method === 'POST') {
                $this->handlePurgeAuditLogs($siteId, $user, $body);
            }
        }

        // Route: /api/v1/admin/preferences
        if (\preg_match('#^/api/v1/admin/preferences/?$#', $uri)) {
            if ($method === 'PATCH' || $method === 'POST') {
                $this->handleSavePreferences($user['id'], $body);
            }
        }

        // Route: /api/v1/admin/block-preview
        if (\preg_match('#^/api/v1/admin/block-preview/?$#', $uri)) {
            if ($method === 'POST') {
                $this->handleBlockPreview($body);
            }
        }

        // Route: /api/v1/admin/ai/generate-summary
        if (\preg_match('#^/api/v1/admin/ai/generate-summary/?$#', $uri)) {
            if ($method === 'POST') {
                $this->handleAiGenerateSummary($body);
            }
        }

        $this->respond(['success' => false, 'error' => 'Endpoint not found or method not allowed'], 404);
    }

    /**
     * Handle ai generate summary processing implementation helper.
     *
     * @param mixed $body Argument descriptor.
     * @return mixed Response output.
     */
    protected function handleAiGenerateSummary($body)
    {
        $content = $body['content'] ?? '';
        if (empty($content)) {
            $this->respond([
                'success' => false,
                'error' => 'No block content provided to generate summary from.'
            ], 400);
        }

        $prompt = "You are an expert copywriter. Generate a concise, engaging, single-paragraph summary (under 250 characters) summarizing the following content of a web page/blog post. Do not include any HTML tags, emojis, markdown, introductory phrases, or conversational filler. Output ONLY the raw paragraph text:\n\n" . $content;

        try {
            if (!AiService::isAvailable()) {
                throw new \Exception("AI Provider is not configured or available.");
            }
            $summary = AiService::generate($prompt);
            $this->respond([
                'success' => true,
                'summary' => \trim($summary)
            ]);
        } catch (\Exception $e) {
            $this->respond([
                'success' => false,
                'error' => 'AI Generation Failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle block preview processing implementation helper.
     *
     * @param mixed $body Argument descriptor.
     * @return mixed Response output.
     */
    protected function handleBlockPreview($body)
    {
        $block = $body['block'] ?? [];
        $type = $block['type'] ?? 'text';
        $site = App::getCurrentSite();
        $theme = $site ? ($site->theme ?? 'default') : 'default';

        // Dynamic Cascading Block View Resolution:
        // 1. Check if the active theme overrides this block: src/Views/themes/{theme}/blocks/{type}.php
        // 2. Check if the block has a registered, module-owned 'frontend_view' path.
        // 3. Graceful legacy fallback.
        $blockPath = APPLICATION_ROOT . '/src/Views/themes/' . $theme . '/blocks/' . $type . '.php';
        if (!\file_exists($blockPath)) {
            $registeredBlock = App::getRegisteredBlocks()[$type] ?? [];
            if (!empty($registeredBlock['frontend_view']) && \file_exists($registeredBlock['frontend_view'])) {
                $blockPath = $registeredBlock['frontend_view'];
            } else {
                $blockPath = APPLICATION_ROOT . '/src/Views/themes/default/blocks/' . $type . '.php';
            }
        }

        // Custom mock resolver helper
        $resolveMedia = function($idOrPath) {
            if (empty($idOrPath)) return '';
            if (\strpos($idOrPath, '/') === 0) return Storage::getUrl($idOrPath);
            $media = Media::find($idOrPath);
            return $media ? Storage::getUrl($media->path) : '';
        };

        if (\file_exists($blockPath)) {
            \ob_start();
            echo Template::renderFile($blockPath, [
                'block' => $block,
                'resolveMedia' => $resolveMedia
            ]);
            $html = \ob_get_clean();
            
            // Clean/Sanitise HTML output for XSS (bypass dynamically based on the block's registered configuration option)
            $registeredBlock = App::getRegisteredBlocks()[$type] ?? [];
            $bypassSanitizer = $registeredBlock['bypass_preview_sanitizer'] ?? false;
            
            if (!$bypassSanitizer) {
                $html = Security::sanitizeHtml($html);
            }

            // Construct block-level title preview based on the active theme so header settings affect the block preview
            $titleHtml = '';
            $hideTitle = $block['hide_title'] ?? '0';
            $title = $block['title'] ?? '';

            if ($hideTitle !== '1' && !empty($title) && $type !== 'baseline') {
                if ($theme === 'kitchensink') {
                    $tag = $hideTitle === '2' ? 'h1' : 'h3';
                    $colorVar = \in_array($type, ['text_image', 'testimonials', 'gallery']) ? '--neon-pink' : '--neon-cyan';
                    $titleHtml = '<' . $tag . ' style="color: var(' . $colorVar . '); margin-bottom: 1.25rem;">' . Security::sanitizeHtml($title) . '</' . $tag . '>';
                } else {
                    $tag = $hideTitle === '2' ? 'h1' : 'h2';
                    $titleHtml = '<' . $tag . ' class="block-section-title">' . Security::sanitizeHtml($title) . '</' . $tag . '>';
                }
            }

            $html = $titleHtml . $html;

            // Determine appropriate theme stylesheets dynamically using App theme registry
            $themeStylesheets = [];
            $themeStylesheets[] = '/assets/css/blocks/baseline.css'; // Always load dynamic public block baseline styles!
            
            // Dynamically load block-specific styles if they exist on disk (e.g. blocks/text_image.css)
            $blockCss = '/assets/css/blocks/' . $type . '.css';
            if (\file_exists(APPLICATION_ROOT . '/public' . $blockCss)) {
                $themeStylesheets[] = $blockCss;
            }

            // Resolve theme main stylesheet dynamically
            $themeStylesheet = App::getThemeStylesheet($theme);
            if (!empty($themeStylesheet)) {
                $themeStylesheets[] = $themeStylesheet;
            } else {
                // Fallback to convention-based path
                $fallbackPath = '/assets/css/themes/' . $theme . '/' . $theme . '.css';
                if (\file_exists(APPLICATION_ROOT . '/public' . $fallbackPath)) {
                    $themeStylesheets[] = $fallbackPath;
                }
            }

            $this->respond([
                'success' => true,
                'html' => $html,
                'theme' => $theme,
                'stylesheets' => $themeStylesheets
            ]);
        } else {
            $this->respond([
                'success' => false,
                'error' => 'Block template not found'
            ], 404);
        }
    }

    /**
     * Eager on-demand calculation of cascading deletes impact details for a target record ID.
     */
    protected function handleCascadeCheck($modelName, $id)
    {
        $model = App::getModelClass($modelName);
        if (!$model) {
            $this->respond(['success' => false, 'error' => 'Invalid model Name'], 400);
        }

        $record = $model::find($id);
        if (!$record && \method_exists($model, 'findTrashed')) {
            $record = $model::findTrashed($id);
        }
        if (!$record) {
            $this->respond(['success' => false, 'error' => 'Record not found'], 404);
        }

        // Check if model uses CascadesDeletes trait or has getCascadeDeletes method
        $cascadeModels = [];
        if (\method_exists($record, 'getCascadeDeletes')) {
            $cascadeModels = $record->getCascadeDeletes();
        } elseif (\property_exists($model, 'cascadeDeletes')) {
            try {
                $reflector = new \ReflectionClass($model);
                if ($reflector->hasProperty('cascadeDeletes')) {
                    $prop = $reflector->getProperty('cascadeDeletes');
                    $prop->setAccessible(true);
                    $cascadeModels = $prop->getValue();
                }
            } catch (\Exception $e) {
                // Fail-safe fallback
            }
        }

        $labels = [];
        if (!empty($cascadeModels)) {
            foreach ($cascadeModels as $childClass => $foreignKey) {
                if (\class_exists($childClass)) {
                    try {
                        $reflector = new \ReflectionClass($childClass);
                        $prop = $reflector->getProperty('tableName');
                        $prop->setAccessible(true);
                        $childTable = $prop->getValue();

                        // Count matching child records that are not soft-deleted
                        $count = (int)\Zero\Database\DB::query("
                            SELECT COUNT(*) FROM {$childTable} 
                            WHERE {$foreignKey} = ? AND deleted_at IS NULL
                        ", [$id])->fetchColumn();

                        if ($count > 0) {
                            $shortName = $reflector->getShortName();
                            $labels[] = "• " . $count . " " . ($count === 1 ? $shortName : $shortName . "s");
                        }
                    } catch (\Exception $e) {
                        // Safe fallback
                    }
                }
            }
        }

        $details = '';
        if (!empty($labels)) {
            $details = "Soft deleting this record will automatically cascade into:\n" . \implode("\n", $labels);
        }

        $this->respond([
            'success' => true,
            'details' => $details,
            'has_cascade' => !empty($labels)
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
        $deletedCount = 0;

        foreach ($ids as $id) {
            $id = \trim($id);
            if (empty($id)) continue;

            $stmt = DB::query("SELECT * FROM media WHERE id = ? AND site_id = ? AND deleted_at IS NULL LIMIT 1", [$id, $siteId]);
            $fileRecord = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($fileRecord) {
                if ($fileRecord['mime'] === 'directory') {
                    $parentFolder = $fileRecord['folder'];
                    $dirName = $fileRecord['filename'];
                    $dirPath = !empty($parentFolder) ? $parentFolder . '/' . $dirName : $dirName;
                    
                    DB::query("UPDATE media SET deleted_at = NOW(), updated_at = NOW() WHERE (folder = ? OR folder LIKE ?) AND site_id = ? AND deleted_at IS NULL", [$dirPath, $dirPath . '/%', $siteId]);
                    
                    Logger::log($_SESSION['user_id'] ?? null, 'delete', 'files', $id, [
                        'title' => $dirName . ' (Folder and recursive contents)'
                    ]);
                } else {
                    Logger::log($_SESSION['user_id'] ?? null, 'delete', 'files', $id, [
                        'title' => $fileRecord['filename']
                    ]);
                }

                DB::query("UPDATE media SET deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND site_id = ?", [$id, $siteId]);
                $deletedCount++;
            }
        }

        $this->respond([
            'success' => $deletedCount > 0,
            'deleted' => $deletedCount
        ]);
    }

    /**
     * Handle delete model processing implementation helper.
     *
     * @param mixed $modelName Argument descriptor.
     * @param mixed $id Argument descriptor.
     * @return mixed Response output.
     */
    protected function handleDeleteModel($modelName, $id)
    {
        $model = App::getModelClass($modelName);

        if (!$model) {
            $this->respond(['success' => false, 'error' => 'Invalid model name'], 400);
        }

        // Apply Super Admin middleware protection for highly sensitive tables or force-deletes
        $isForce = isset($_GET['force']) && $_GET['force'] === 'true';
        if ($modelName === 'users' || $modelName === 'sites' || $isForce) {
            App::applyRoleMiddleware('super_admin');
        }

        $record = $isForce ? $model::findTrashed($id) : $model::find($id);
        if ($record) {
            try {
                if ($isForce) {
                    $record->forceDelete();
                    Logger::log($_SESSION['user_id'] ?? null, 'force_delete', $modelName, $id, [
                        'title' => $record->title ?? ($record->filename ?? ($record->username ?? ''))
                    ]);
                } else {
                    $record->delete();
                    Logger::log($_SESSION['user_id'] ?? null, 'delete', $modelName, $id, [
                        'title' => $record->title ?? ($record->filename ?? ($record->username ?? ''))
                    ]);
                }
                $this->respond(['success' => true]);
            } catch (\Exception $e) {
                $this->respond(['success' => false, 'error' => $e->getMessage()], 500);
            }
        }

        $this->respond(['success' => false, 'error' => 'Record not found or already deleted'], 404);
    }

    /**
     * Handle get files processing implementation helper.
     *
     * @param mixed $siteId Argument descriptor.
     * @return mixed Response output.
     */
    protected function handleGetFiles($siteId)
    {
        $folder = $_GET['folder'] ?? '';
        $folder = \preg_replace('/[^a-zA-Z0-9_\-\/]/', '_', $folder);
        $folder = \trim($folder, '/');

        // Check if pagination/infinite scroll is requested via query param page
        if (isset($_GET['page'])) {
            $page = (int)$_GET['page'];
            if ($page < 1) $page = 1;
            $limit = 20;
            $offset = ($page - 1) * $limit;

            // Fetch and count
            $stmt = DB::query("SELECT * FROM media WHERE folder = ? AND site_id = ? AND deleted_at IS NULL ORDER BY (mime = 'directory') DESC, created_at DESC LIMIT $limit OFFSET $offset", [$folder, $siteId]);
            $files = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $totalStmt = DB::query("SELECT COUNT(*) as total FROM media WHERE folder = ? AND site_id = ? AND deleted_at IS NULL", [$folder, $siteId]);
            $totalResult = $totalStmt->fetch(\PDO::FETCH_ASSOC);
            $total = (int)($totalResult['total'] ?? 0);
            $hasMore = ($offset + \count($files)) < $total;

            $html = '';
            $csrfToken = $_SESSION['csrf'] ?? '';
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

            $this->respond([
                'success' => true,
                'html' => $html,
                'has_more' => $hasMore,
                'current_page' => $page,
                'total' => $total
            ]);
        }

        // Return full JSON list (replaces old /admin/files/json)
        $stmt = DB::query("SELECT * FROM media WHERE site_id = ? AND deleted_at IS NULL ORDER BY (mime = 'directory') DESC, created_at DESC", [$siteId]);
        $files = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->respond($files);
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

        // Get the destination folder path
        $destinationFolder = '';
        if ($targetFolderId !== 'parent') {
            $stmtFolder = DB::query("SELECT * FROM media WHERE id = ? AND mime = 'directory' AND site_id = ? AND deleted_at IS NULL LIMIT 1", [$targetFolderId, $siteId]);
            $folderRecord = $stmtFolder->fetch(\PDO::FETCH_ASSOC);
            if (!$folderRecord) {
                $this->respond(['success' => false, 'error' => 'Destination folder not found or permission denied'], 404);
            }
            $parentFolder = $folderRecord['folder'] ?? '';
            $dirName = $folderRecord['filename'];
            $destinationFolder = !empty($parentFolder) ? $parentFolder . '/' . $dirName : $dirName;
        }

        $movedCount = 0;
        $errors = [];

        foreach ($fileIds as $fileId) {
            $fileId = \trim($fileId);
            if (empty($fileId)) continue;

            $stmt = DB::query("SELECT * FROM media WHERE id = ? AND site_id = ? AND deleted_at IS NULL LIMIT 1", [$fileId, $siteId]);
            $fileRecord = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$fileRecord || $fileRecord['mime'] === 'directory') {
                $errors[] = "File {$fileId} not found.";
                continue;
            }

            $currentDestinationFolder = $destinationFolder;
            if ($targetFolderId === 'parent') {
                $currentFolder = $fileRecord['folder'] ?? '';
                $parts = \explode('/', $currentFolder);
                \array_pop($parts);
                $currentDestinationFolder = \implode('/', $parts);
            }

            $oldPhysicalPath = APPLICATION_ROOT . $fileRecord['path'];
            $newFilename = $fileRecord['filename'];
            $newPhysicalDir = APPLICATION_ROOT . '/public/storage/uploads' . (!empty($currentDestinationFolder) ? '/' . $currentDestinationFolder : '');
            
            if (!Storage::exists($newPhysicalDir)) {
                Storage::makeDirectory($newPhysicalDir);
            }

            $newPhysicalPath = $newPhysicalDir . '/' . $newFilename;
            $info = \pathinfo($newFilename);
            $counter = 1;
            while (Storage::exists($newPhysicalPath)) {
                $newFilename = $info['filename'] . '_' . $counter . '.' . ($info['extension'] ?? '');
                $newPhysicalPath = $newPhysicalDir . '/' . $newFilename;
                $counter++;
            }

            if (Storage::exists($oldPhysicalPath)) {
                if (Storage::rename($oldPhysicalPath, $newPhysicalPath)) {
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
                $newDbPath = Storage::getUrl($newPhysicalPath);
                DB::query(
                    "UPDATE media SET filename = ?, path = ?, folder = ?, updated_at = NOW() WHERE id = ? AND site_id = ?",
                    [$newFilename, $newDbPath, $currentDestinationFolder, $fileId, $siteId]
                );
                $movedCount++;
            }
        }

        $this->respond([
            'success' => $movedCount > 0,
            'moved' => $movedCount,
            'errors' => $errors
        ]);
    }

    /**
     * Handle purge audit logs processing implementation helper.
     *
     * @param string $siteId Argument descriptor.
     * @param array $user Argument descriptor.
     * @param array $body Argument descriptor.
     * @return mixed Response output.
     */
    protected function handlePurgeAuditLogs(string $siteId, array $user, array $body)
    {
        $purgeAll = !empty($body['purge_all_sites']);

        if ($purgeAll) {
            // Enforce that only Super Admins can purge all sites globally!
            if (($user['role'] ?? '') !== 'super_admin') {
                $this->respond([
                    'success' => false,
                    'error' => 'Forbidden: Only super administrators can purge logs globally across all sites'
                ], 403);
            }

            // Execute global delete statement on audit_logs table!
            DB::query("DELETE FROM audit_logs");

            // Also log this global purge action itself in the logs (since it was just emptied, this starts fresh)
            Logger::log('purge_all_audit_logs', 'audit_logs', '*', [
                'user' => $user['username'],
                'scope' => 'global'
            ]);

            $this->respond([
                'success' => true,
                'message' => 'Successfully purged all audit logs globally across all sites'
            ]);
        } else {
            // Purge only the active site's logs!
            DB::query("DELETE FROM audit_logs WHERE site_id = ?", [$siteId]);

            // Log the purge action itself for the site
            Logger::log('purge_audit_logs', 'audit_logs', '*', [
                'user' => $user['username'],
                'scope' => 'site'
            ]);

            $this->respond([
                'success' => true,
                'message' => 'Successfully purged all audit logs for this site'
            ]);
        }
    }

    /**
     * Handle reorder model processing implementation helper.
     *
     * @param mixed $modelName Argument descriptor.
     * @param mixed $body Argument descriptor.
     * @return mixed Response output.
     */
    protected function handleReorderModel($modelName, $body)
    {
        $model = App::getModelClass($modelName);

        if (!$model) {
            $this->respond(['success' => false, 'error' => 'Invalid model name'], 400);
        }

        // Apply Super Admin middleware protection for highly sensitive tables
        if ($modelName === 'users' || $modelName === 'sites') {
            App::applyRoleMiddleware('super_admin');
        }

        // Check if model has IsOrderable trait or supports reordering
        $traits = \class_uses($model);
        $isOrderable = isset($traits[IsOrderable::/**
 * Class 
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class]) || (\method_exists($model, 'isOrderable') && $model::isOrderable());

        if (!$isOrderable) {
            $this->respond(['success' => false, 'error' => 'Model is not orderable'], 400);
        }

        $ids = $body['ids'] ?? [];
        if (!\is_array($ids)) {
            $this->respond(['success' => false, 'error' => 'Invalid or missing ids array'], 400);
        }

        // Perform reordering!
        $model::reorder($ids);

        Logger::log($_SESSION['user_id'] ?? null, 'update', $modelName, null, [
            'title' => 'Reordered list of ' . $modelName
        ]);

        $this->respond(['success' => true]);
    }

    /**
     * Handle save model processing implementation helper.
     *
     * @param mixed $modelName Argument descriptor.
     * @param mixed $id Argument descriptor.
     * @param mixed $body Argument descriptor.
     * @return mixed Response output.
     */
    protected function handleSaveModel($modelName, $id, $body)
    {
        $model = App::getModelClass($modelName);

        if (!$model) {
            $this->respond(['success' => false, 'error' => 'Invalid model name'], 400);
        }

        // Apply Super Admin middleware protection for highly sensitive tables
        if ($modelName === 'users' || $modelName === 'sites') {
            App::applyRoleMiddleware('super_admin');
        }

        $config = $model::getConfig();
        $data = [];
        foreach ($config as $field => $fieldConfig) {
            if ($fieldConfig['editable'] ?? false) {
                $val = $body[$field] ?? '';
                // Automatically json_encode array values (such as enabled_modules checkbox arrays!)
                if (\is_array($val)) {
                    $val = \json_encode($val);
                }
                $data[$field] = $val;
            }
        }

        // Auto-generate slug if the model has a slug property and title is set (bypassed for pages which compiles slugs hierarchically)
        if ($modelName !== 'pages' && \property_exists($model, 'slug') && isset($data['title'])) {
            $inputSlug = $data['slug'] ?? '';
            if (empty($inputSlug)) {
                $data['slug'] = App::slugify($data['title']);
            } else {
                $data['slug'] = App::slugifyPath($inputSlug);
            }
        }

        if ($id && $id !== 'new') {
            // Edit existing record
            $record = $model::find($id);
            if (!$record) {
                $this->respond(['success' => false, 'error' => 'Record not found'], 404);
            }
            foreach ($data as $key => $value) {
                $record->$key = $value;
            }
            $record->save();
            Logger::log($_SESSION['user_id'] ?? null, 'update', $modelName, $id, [
                'title' => $data['title'] ?? ($data['filename'] ?? ($data['username'] ?? ''))
            ]);
            
            $this->respond([
                'success' => true,
                'id' => $id
            ]);
        } else {
            // Create new record
            $record = new $model($data);
            $newId = $record->save();
            
            Logger::log($_SESSION['user_id'] ?? null, 'create', $modelName, $newId, [
                'title' => $data['title'] ?? ($data['filename'] ?? ($data['username'] ?? ''))
            ]);

            $this->respond([
                'success' => true,
                'id' => $newId
            ]);
        }
    }

    /**
     * Handle save preferences processing implementation helper.
     *
     * @param mixed $userId Argument descriptor.
     * @param mixed $body Argument descriptor.
     * @return mixed Response output.
     */
    protected function handleSavePreferences($userId, $body)
    {
        $action = $_GET['action'] ?? $body['action'] ?? '';

        if ($action === 'save_layout') {
            $layout = $body['layout'] ?? [];
            if (!\is_array($layout)) {
                $this->respond(['success' => false, 'error' => 'Invalid layout data'], 400);
            }

            // Fetch current preferences, update dashboard layout and save
            $prefs = User::getPreferencesForUser($userId);
            $prefs['dashboard_layout'] = $layout;
            
            DB::query("UPDATE users SET preferences = ? WHERE id = ?", [\json_encode($prefs), $userId]);
            $this->respond(['success' => true]);
        }

        // Generic Preferences Save (ThemeSwitcher, Layout toggles etc.)
        $theme = $body['theme'] ?? 'light';
        $themePreset = $body['theme_preset'] ?? 'default';
        $widgets = $body['widgets'] ?? [];
        $language = $body['language'] ?? 'en';
        $timezone = $body['timezone'] ?? 'UTC';
        $perPage = \intval($body['per_page'] ?? 20);

        // Basic validation
        if (!\in_array($theme, ['light', 'dark'])) {
            $theme = 'light';
        }
        if (!\in_array($themePreset, ['default', 'vintage-greenscreen'])) {
            $themePreset = 'default';
        }
        if (!\in_array($language, ['en', 'es', 'mi', 'hr'])) {
            $language = 'en';
        }
        if (!\in_array($perPage, [10, 20, 50, 100])) {
            $perPage = 20;
        }

        $prefs = User::getPreferencesForUser($userId);
        $prefs['theme'] = $theme;
        $prefs['theme_preset'] = $themePreset;
        $prefs['dashboard_layout'] = $widgets; // Save layout properly under dashboard_layout!
        $prefs['language'] = $language;
        $prefs['timezone'] = $timezone;
        $prefs['per_page'] = $perPage;

        // Reset translation cache to apply language changes on the current page load instantly
        I18n::reset();

        DB::query("UPDATE users SET preferences = ? WHERE id = ?", [\json_encode($prefs), $userId]);
        $this->respond(['success' => true]);
    }

    /**
     * Handle upload file processing implementation helper.
     *
     * @param mixed $siteId Argument descriptor.
     * @return mixed Response output.
     */
    protected function handleUploadFile($siteId)
    {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->respond(['success' => false, 'error' => 'File upload failed or no file selected.'], 400);
        }

        $file = $_FILES['file'];
        $filename = \basename($file['name']);
        $filename = \preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'pdf', 'doc', 'docx', 'zip', 'txt', 'mp4'];
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp', 'application/pdf', 'application/zip', 'text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'video/mp4'];

        $ext = \strtolower(\pathinfo($filename, PATHINFO_EXTENSION));
        $detectedMime = \mime_content_type($file['tmp_name']);

        if (!\in_array($ext, $allowedExtensions) || !\in_array($detectedMime, $allowedMimeTypes)) {
            $this->respond(['success' => false, 'error' => 'Forbidden file extension or invalid file type.'], 403);
        }

        $currentFolder = $_POST['folder'] ?? '';
        $currentFolder = \preg_replace('/[^a-zA-Z0-9_\-\/]/', '_', $currentFolder);
        $currentFolder = \trim($currentFolder, '/');

        $uploadsDir = APPLICATION_ROOT . '/public/storage/uploads/' . $siteId . (!empty($currentFolder) ? '/' . $currentFolder : '');
        if (!Storage::exists($uploadsDir)) {
            Storage::makeDirectory($uploadsDir);
        }

        $targetPath = $uploadsDir . '/' . $filename;
        $info = \pathinfo($filename);
        $counter = 1;
        while (Storage::exists($targetPath)) {
            $filename = $info['filename'] . '_' . $counter . '.' . ($info['extension'] ?? '');
            $targetPath = $uploadsDir . '/' . $filename;
            $counter++;
        }

        $mime = \mime_content_type($file['tmp_name']);
        if ($mime === 'image/svg+xml' || $ext === 'svg') {
            if (!Security::sanitizeSvg($file['tmp_name'])) {
                $this->respond(['success' => false, 'error' => 'Invalid SVG file or sanitization failed.'], 400);
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

            $this->respond([
                'success' => true,
                'file' => [
                    'id' => $newFileId,
                    'filename' => $filename,
                    'path' => $dbPath,
                    'mime' => $mime,
                    'folder' => $currentFolder,
                    'created_at' => \gmdate('Y-m-d H:i:s')
                ]
            ]);
        } else {
            $this->respond(['success' => false, 'error' => 'Could not save the uploaded file.'], 500);
        }
    }
}
