<?php

namespace Zero\Models\Traits;

use Zero\Models\Media;

trait HasFeaturedImage
{
    public $featured_image_path;
    public $featured_image_id;

    /**
     * Resolves the 36-character media UUID stored in featured_image to its physical file path on disk.
     */
    public function resolveFeaturedImage(): void
    {
        if (!empty($this->featured_image_path)) {
            $this->featured_image_id = $this->featured_image;
            $this->featured_image = $this->featured_image_path;
        } elseif (!empty($this->featured_image) && strlen($this->featured_image) === 36) {
            $this->featured_image_id = $this->featured_image;
            $media = Media::find($this->featured_image);
            if ($media) {
                $this->featured_image_path = $media->path;
                $this->featured_image = $media->path;
            }
        }
    }

    /**
     * Retrieves the resolved featured image URL, or falls back to a default visual SVG placeholder if missing.
     */
    public function getFeaturedImageUrl(): string
    {
        return $this->featured_image_path ?? $this->featured_image ?? '/assets/svgs/image.svg';
    }
}
