<?php

declare(strict_types=1);

/**
 * File: src/Models/Media.php
 * Architectural Purpose: Active Record data model or behavioral trait wrapping database schema representation with tenant-scoping.
 * Package: Zero\Models
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Models;

use Zero\Core\Storage\Storage;
use Zero\Interfaces\Model;
use Zero\Models\Traits\HasSlug;
use Zero\Models\Traits\IsModel;

/**
 * Class Media
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class Media implements Model
{
    use IsModel, HasSlug {
        IsModel::forceDelete as traitForceDelete;
    }

    protected static $tableName = 'media';
    protected static $fillable = [
        'filename', 
        'path', 
        'mime', 
        'title', 
        'focus_x', 
        'focus_y', 
        'visibility', 
        'submission_id', 
        'original_name', 
        'file_size'
    ];
    protected static $modelType = 'Media';

    public $filename;
    public $path;
    public $mime;
    public $title;
    public $focus_x = 50;
    public $focus_y = 50;
    public $site_id;
    public $visibility = 'public';
    public $submission_id;
    public $original_name;
    public $file_size = 0;
    public $created_at;
    public $updated_at;
    public $deleted_at;

    /**
     * Delete the physical file from disk storage when this media active record is permanently deleted.
     */
    public function forceDelete()
    {
        if (!empty($this->path)) {
            $relativePath = \ltrim($this->path, '/');
            if (\strpos($relativePath, 'storage/uploads/') === 0) {
                $relativePath = \substr($relativePath, \strlen('storage/uploads/'));
            }

            try {
                Storage::delete($relativePath);
            } catch (\Exception $e) {
                // Silently fallback if storage operation fails during permanent purges
            }
        }

        return $this->traitForceDelete();
    }

    /**
     * Retrieves the config attribute value.
     *
     * @return mixed Response output.
     */
    public static function getConfig(): array
    {
        return [
            'id' => ['type' => 'int', 'label' => 'ID', 'editable' => false, 'listDisplay' => false],
            'title' => ['type' => 'text', 'label' => 'Title', 'editable' => true, 'listDisplay' => true, 'searchable' => true],
            'filename' => ['type' => 'text', 'label' => 'Filename', 'editable' => true, 'required' => true, 'listDisplay' => true, 'searchable' => true],
            'path' => ['type' => 'text', 'label' => 'Path', 'editable' => false, 'listDisplay' => true],
            'mime' => ['type' => 'text', 'label' => 'Mime', 'editable' => false, 'listDisplay' => false],
            'focus_x' => ['type' => 'int', 'label' => 'Focus X', 'editable' => true, 'listDisplay' => false],
            'focus_y' => ['type' => 'int', 'label' => 'Focus Y', 'editable' => true, 'listDisplay' => false],
            'created_at' => ['type' => 'datetime', 'label' => 'Created At', 'editable' => false, 'listDisplay' => true],
        ];
    }

    /**
     * Dynamically generates a square cropped image based on focal point settings,
     * implements file-properties hashing caching, and returns the public URL to the cropped image.
     * Keeps method sorting alphabetically correct (getConfig -> getSquareCropUrl).
     */
    public function getSquareCropUrl(int $size = 300): string
    {
        $mime = $this->mime;
        if (empty($mime) || \strpos($mime, 'image/') !== 0) {
            return $this->path; // Non-images fall back to their default path
        }

        $physicalPath = APPLICATION_ROOT . '/public' . $this->path;
        if (!\file_exists($physicalPath)) {
            return $this->path; // Return original path if physical file doesn't exist
        }

        // Generate cache/hash based on media ID, focus points, file mtime and target thumbnail resolution
        $mtime = \filemtime($physicalPath);
        $focusX = $this->focus_x ?? 50;
        $focusY = $this->focus_y ?? 50;
        $hash = \md5("{$this->id}_{$focusX}_{$focusY}_{$mtime}_{$size}");
        
        $siteId = $this->site_id ?? 'default';
        $cropsDir = APPLICATION_ROOT . "/public/storage/uploads/{$siteId}/_crops";
        
        $cachedFilename = "crop_{$this->id}_{$hash}.jpg";
        $cachedPhysicalPath = "{$cropsDir}/{$cachedFilename}";
        $cachedPublicUrl = "/storage/uploads/{$siteId}/_crops/{$cachedFilename}";

        // Return cached URL immediately if crop already exists on disk
        if (\file_exists($cachedPhysicalPath)) {
            return $cachedPublicUrl;
        }

        // Create the directory if it is missing, applying defensive chmod
        if (!\file_exists($cropsDir)) {
            @\mkdir($cropsDir, 0775, true);
            @\chmod($cropsDir, 0775);
        }

        // Load the source image safely using PHP GD extensions
        $srcImage = null;
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $srcImage = @\imagecreatefromjpeg($physicalPath);
                break;
            case 'image/png':
                $srcImage = @\imagecreatefrompng($physicalPath);
                break;
            case 'image/webp':
                $srcImage = @\imagecreatefromwebp($physicalPath);
                break;
            case 'image/gif':
                $srcImage = @\imagecreatefromgif($physicalPath);
                break;
        }

        if (!$srcImage) {
            return $this->path; // Fallback to original image if load failed
        }

        $origWidth = \imagesx($srcImage);
        $origHeight = \imagesy($srcImage);

        // Calculate aspect-ratio aligned crop boundaries
        $cropSize = 0;
        $srcX = 0;
        $srcY = 0;

        if ($origHeight > $origWidth) {
            // Portrait: Square is as wide as the image width, constrained vertically
            $cropSize = $origWidth;
            $srcX = 0;
            
            $centerY = ($focusY / 100) * $origHeight;
            $srcY = \max(0, \min($origHeight - $cropSize, $centerY - ($cropSize / 2)));
        } else {
            // Landscape/Square: Square is as tall as the image height, constrained horizontally
            $cropSize = $origHeight;
            $srcY = 0;

            $centerX = ($focusX / 100) * $origWidth;
            $srcX = \max(0, \min($origWidth - $cropSize, $centerX - ($cropSize / 2)));
        }

        // Initialize target cropped resampled canvas
        $dstImage = \imagecreatetruecolor($size, $size);

        // Fill background with clean white (e.g. for transparent PNG fallback)
        $white = \imagecolorallocate($dstImage, 255, 255, 255);
        \imagefill($dstImage, 0, 0, $white);

        // Perform high-quality pixel resampling
        \imagecopyresampled(
            $dstImage,
            $srcImage,
            0, 0,
            (int)$srcX, (int)$srcY,
            $size, $size,
            (int)$cropSize, (int)$cropSize
        );

        // Save at 90% JPEG quality to optimize compression sizes and listing loading times
        \imagejpeg($dstImage, $cachedPhysicalPath, 90);
        @\chmod($cachedPhysicalPath, 0664); // Defensively ensure secure read/write access to cropped files

        // Garbage collection of image resources
        \imagedestroy($srcImage);
        \imagedestroy($dstImage);

        return $cachedPublicUrl;
    }

    /**
     * Get the public or secure gated URL for this media file.
     *
     * @return string
     */
    public function getUrl(): string
    {
        if ($this->visibility === 'private') {
            return "/admin/secure-download/{$this->id}";
        }
        return $this->path;
    }
}
