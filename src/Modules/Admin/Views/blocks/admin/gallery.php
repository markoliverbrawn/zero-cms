<?php
// src/Modules/Admin/Views/blocks/admin/gallery.php

use Zero\Models\Media;
use Zero\Support\Str;

$mediaIds = $block['media_ids'] ?? [];
?>
<div class="field-group">
    <label>Block Title</label>
    <input type="text" class="block-title-input" value="<?php echo Str::escape($blockTitle); ?>" placeholder="Enter gallery title...">
</div>
<div class="field-group">
    <label>Gallery Images List</label>
    <div class="gallery-images-list">
        <?php foreach ($mediaIds as $imgId): 
            $media = Media::find($imgId);
            $filename = $media ? $media->filename : 'Unknown File';
            $previewUrl = $media ? $media->path : '';
            ?>
            <div class="gallery-image-row">
                <input type="hidden" class="gallery-media_id-input" value="<?php echo Str::escape($imgId); ?>">
                <?php if (!empty($previewUrl)): ?>
                    <div class="gallery-image-preview-wrapper">
                        <img class="gallery-image-preview" src="<?php echo Str::escape($previewUrl); ?>">
                    </div>
                <?php endif; ?>
                <div class="gallery-image-filename"><?php echo Str::escape($filename); ?></div>
                <button type="button" class="btn-delete-gallery-image">Remove</button>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn-add-gallery-image">+ Add Gallery Image</button>
</div>
