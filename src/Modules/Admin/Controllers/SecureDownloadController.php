<?php

namespace Zero\Modules\Admin\Controllers;

use Zero\Core\App;
use Zero\Database\DB;
use Zero\Http\Middleware\SecurePathMiddleware;
use Zero\Interfaces\Controller;

class SecureDownloadController implements Controller
{
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
            http_response_code(404);
            echo "File not found or access denied.";
            exit;
        }

        // 2.5 Delegate path traversal, containment, and symlink validation to Middleware
        SecurePathMiddleware::handle($fileId, $file['path'], function(string $physicalPath) use ($file) {
            if (!file_exists($physicalPath)) {
                http_response_code(404);
                echo "Physical file missing from secure disk storage.";
                exit;
            }

            // 3. Securely stream the private file to the browser with protective headers
            $originalName = $file['original_name'] ?? $file['filename'];
            $mimeType = $file['mime'] ?? 'application/octet-stream';
            $fileSize = $file['file_size'] ?? filesize($physicalPath);

            header('Content-Description: File Transfer');
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . basename($originalName) . '"');
            header('Content-Length: ' . $fileSize);
            header('Expires: 0');
            header('Cache-Control: private, must-revalidate');
            header('Pragma: public');
            
            // Output file in small, non-blocking chunks to prevent PHP memory exhaustion on heavy files
            $fileHandle = fopen($physicalPath, 'rb');
            while (!feof($fileHandle)) {
                echo fread($fileHandle, 8192);
                ob_flush();
                flush();
            }
            fclose($fileHandle);
            exit;
        });
    }
}
