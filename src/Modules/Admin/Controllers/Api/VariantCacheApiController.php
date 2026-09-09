<?php

declare(strict_types=1);

/**
 * File: src/Modules/Admin/Controllers/Api/VariantCacheApiController.php
 * Architectural Purpose: JSON endpoint backing the Media Library's "Manage Cache" toolbar action --
 * reports how much space the active tenant's rendered image variants are holding, and clears them
 * on demand.
 * Package: Zero\Modules\Admin\Controllers\Api
 */

namespace Zero\Modules\Admin\Controllers\Api;

use Zero\Core\App;
use Zero\Core\Storage\Storage;
use Zero\Support\Logger;
use Zero\Support\VariantCache;

/**
 * Class VariantCacheApiController
 */
class VariantCacheApiController extends AdminApiControllerBase
{
    /**
     * Format a raw byte count for display, e.g. "4.2 MB".
     *
     * @param int $bytes Raw byte count.
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;
        $size = (float)$bytes;

        while ($size >= 1024 && $index < \count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return \round($size, $index === 0 ? 0 : 1) . ' ' . $units[$index];
    }

    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $matches Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($matches)
    {
        $this->authenticate();
        App::requirePermission('media.maintenance');

        $siteId = App::getCurrentSiteId();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'GET') {
            $this->respond(['success' => true] + $this->measureVariantCache($siteId));
        }

        if ($method === 'DELETE') {
            $deleted = VariantCache::clear($siteId);

            Logger::log($_SESSION['user_id'] ?? null, 'clear', 'variant_cache', $siteId, [
                'deleted' => $deleted
            ]);

            $this->respond(['success' => true, 'deleted' => $deleted]);
        }

        $this->respond(['success' => false, 'error' => 'Endpoint not found or method not allowed'], 404);
    }

    /**
     * Walk a tenant's variant cache directory to report how much space it is holding, mirroring
     * the accounting bin/assets stats performs from the CLI.
     *
     * @param string $siteId Tenant whose cache usage should be measured.
     * @return array{count: int, bytes: int, bytesFormatted: string}
     */
    private function measureVariantCache(string $siteId): array
    {
        $root = Storage::getVariantsRoot() . '/' . $siteId;
        $count = 0;
        $bytes = 0;

        if (\is_dir($root)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $count++;
                    $bytes += $file->getSize();
                }
            }
        }

        return ['count' => $count, 'bytes' => $bytes, 'bytesFormatted' => $this->formatBytes($bytes)];
    }
}
