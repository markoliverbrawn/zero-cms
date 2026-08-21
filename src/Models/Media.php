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
use Zero\Support\Assets;

/**
 * Class Media
 *
 * Active Record model for uploaded media. Resolves public and resized-variant URLs for a stored
 * file, and removes the underlying stored object alongside the row on a force delete.
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
        'file_size',
        'width',
        'height'
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
    public $width = 0;
    public $height = 0;
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
     * Resolve the URL of a square, focal-point-cropped thumbnail of this image.
     *
     * Retained as the established name for the admin file-manager thumbnail, but now a thin
     * delegation: the rendition is produced on first request by MediaVariantController rather
     * than synchronously here. That matters because this used to be called inside the file
     * listing's render loop, so browsing a folder of fresh uploads paid a full GD resize per
     * card before the page could be sent.
     *
     * @param int $size Edge length of the square thumbnail in pixels.
     * @return string The variant URL, or the original path when no variant applies.
     */
    public function getSquareCropUrl(int $size = 300): string
    {
        return $this->getVariantUrl($size, $size);
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

    /**
     * Resolve the URL of a resized, focal-point-aware variant of this image.
     *
     * Primes the variant registry from this record's own attributes before minting the URL, so a
     * caller that already holds a model does not trigger a lookup to re-read what it is holding.
     * Anything that cannot be resized -- a non-image, an animated GIF, an SVG, a private file --
     * comes back as this record's ordinary URL, which for access-gated media is its secure
     * download route rather than a directly fetchable path.
     *
     * @param int|null $width Requested width in pixels, or null to derive it from the height.
     * @param int|null $height Requested height in pixels, or null to derive it from the width.
     * @param string $fit Assets::FIT_COVER (crop to fill) or Assets::FIT_CONTAIN (scale to fit).
     * @param int|null $quality Encoder quality override.
     * @return string
     */
    public function getVariantUrl(
        ?int $width = null,
        ?int $height = null,
        string $fit = Assets::FIT_COVER,
        ?int $quality = null
    ): string {
        Assets::prime([[
            'id' => $this->id,
            'site_id' => $this->site_id,
            'path' => $this->path,
            'mime' => $this->mime,
            'title' => $this->title,
            'filename' => $this->filename,
            'focus_x' => $this->focus_x,
            'focus_y' => $this->focus_y,
            'width' => $this->width,
            'height' => $this->height,
            'visibility' => $this->visibility,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ]]);

        // Minting from the stored path rather than the id matters for the pass-through case:
        // Assets::url() returns whatever it was handed when a source cannot be resized, and a
        // bare record id is not a URL a browser could ever fetch.
        $source = (string)($this->path !== '' && $this->path !== null ? $this->path : $this->id);
        $url = Assets::url($source, $width, $height, $fit, $quality);

        return $url === $source ? $this->getUrl() : $url;
    }
}
