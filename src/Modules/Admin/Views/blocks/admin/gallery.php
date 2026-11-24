<?php
// src/Modules/Admin/Views/blocks/admin/gallery.php

use Zero\Core\App;
use Zero\Models\Media;
use Zero\Support\Str;

$mediaIds = $block['media_ids'] ?? [];
?>
<div class="field-group">
    <label>Block Title (Rich Text)</label>
    <div class="editor">
        <div class="toolbar">
            <button type="button" data-cmd="bold"><strong>B</strong></button>
            <button type="button" data-cmd="italic"><em>I</em></button>
            <button type="button" data-cmd="insertSmall">Small</button>
            <button type="button" data-cmd="removeFormat">Clear</button>
        </div>
        <div class="editor-area block-editor-area block-title-rich-editor block-title-input" contenteditable="true"><?php echo $blockTitle; ?></div><?php // security-scan-ignore: unescaped-output -- block_builder.php passes $blockTitle through Security::sanitizeTitleHtml() ?>
    </div>
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
                <button type="button" class="btn-move-gallery-image-up" title="Move Up" aria-label="Move image up"><span class="icon-svg icon-svg-14"><?php echo App::svg('arrow-up'); ?></span></button>
                <button type="button" class="btn-move-gallery-image-down" title="Move Down" aria-label="Move image down"><span class="icon-svg icon-svg-14"><?php echo App::svg('arrow-down'); ?></span></button>
                <button type="button" class="btn-delete-gallery-image">Remove</button>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn-add-gallery-image">+ Add Gallery Image</button>
</div>
