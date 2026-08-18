<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Controllers/SecureDownloadController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Admin\Controllers
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Http\Middleware\SecurePathMiddleware;
use Zero\Interfaces\Controller;

/**
 * Class SecureDownloadController
 *
 * Serves a private stored file at /admin/secure-download/{id}, authorising the request and
 * streaming the object through the application so private storage is never publicly addressable.
 */
class SecureDownloadController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($param)
    {
        // 1. Enforce strict administrative authorization
        App::applyAuthMiddleware();
        App::applyRoleMiddleware('editor'); // Editors and Super Admins only!

        $fileId = $param[1] ?? null;
        $siteId = App::getCurrentSiteId();

        // 2. Query file metadata enforcing active tenant boundaries
        $file = DB::query("
            SELECT * FROM media 
            WHERE id = ? AND site_id = ? AND visibility = 'private' AND deleted_at IS NULL
        ", [$fileId, $siteId])->fetch();

        if (!$file) {
            \http_response_code(404);
            echo "File not found or access denied.";
            exit;
        }

        // 2.5 Delegate path traversal, containment, and symlink validation to Middleware
        SecurePathMiddleware::handle($fileId, $file['path'], function(string $physicalPath) use ($file) {
            $driverName = \Zero\Core\Env::get('STORAGE_DRIVER', 'local');
            if ($driverName !== 'local') {
                try {
                    $signedUrl = \Zero\Core\Storage\Storage::getSignedUrl($file['path'], 60);
                    \header("Location: " . $signedUrl);
                    exit;
                } catch (\Exception $e) {
                    \http_response_code(500);
                    echo "Secure redirection failed: " . $e->getMessage();
                    exit;
                }
            }

            if (!\file_exists($physicalPath)) {
                \http_response_code(404);
                echo "Physical file missing from secure disk storage.";
                exit;
            }

            // 3. Securely stream the private file to the browser with protective headers
            $originalName = $file['original_name'] ?? $file['filename'];
            $mimeType = $file['mime'] ?? 'application/octet-stream';
            $fileSize = $file['file_size'] ?? \filesize($physicalPath);

            \header('Content-Description: File Transfer');
            \header('Content-Type: ' . $mimeType);
            \header('Content-Disposition: attachment; filename="' . \basename($originalName) . '"');
            \header('Content-Length: ' . $fileSize);
            \header('Expires: 0');
            \header('Cache-Control: private, must-revalidate');
            \header('Pragma: public');
            
            // Output file in small, non-blocking chunks to prevent PHP memory exhaustion on heavy files
            $fileHandle = \fopen($physicalPath, 'rb');
            while (!\feof($fileHandle)) {
                echo \fread($fileHandle, 8192);
                \ob_flush();
                \flush();
            }
            \fclose($fileHandle);
            exit;
        });
    }
}
