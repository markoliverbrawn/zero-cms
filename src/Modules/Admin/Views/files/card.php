<?php
// src/Modules/Admin/Views/files/card.php

use Zero\Core\App;
use Zero\Models\Media;
use Zero\Support\Str;

if ($mime === 'directory'): ?>
  <!-- Render Folder card -->
  <li id="file-<?php echo Str::escape($id); ?>" class="file-card folder-card" data-filename="<?php echo Str::escape(strtolower($filename)); ?>" data-fid="<?php echo Str::escape($id); ?>">
    <a href="?folder=<?php echo Str::escape(!empty($currentFolder) ? $currentFolder . '/' . $filename : $filename); ?>" style="display: block; text-decoration: none; color: inherit;">
      <div class="file-preview-container">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
        </svg>
      </div>
      <div class="file-details">
        <h3 style="margin: 0 0 2px 0; font-size: 0.9rem; font-weight: bold; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo Str::escape(!empty($f['title']) ? $f['title'] : $filename); ?>">
          <?php echo Str::escape(!empty($f['title']) ? $f['title'] : $filename); ?>
        </h3>
        <div style="font-size: 0.75rem; color: color-mix(in srgb, var(--text-color) 50%, transparent);">
          Folder
        </div>
      </div>
    </a>
    <div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center; border-top: 1px solid color-mix(in srgb, var(--bg-color-inverse) 8%, var(--bg-color) 92%); padding-top: 6px; margin-top: 4px;">
      <form method="post" action="/admin/files/delete" style="display:inline; margin: 0;" class="ajax-delete">
        <input type="hidden" name="csrf" value="<?php echo Str::escape($csrf ?? ''); ?>">
        <input type="hidden" name="id" value="<?php echo Str::escape($id); ?>">
        <button type="submit" title="Delete Folder" class="action-btn" style="background: none; border: none; padding: 0; cursor: pointer;">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="3 6 5 6 21 6"></polyline>
            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
            <line x1="10" y1="11" x2="10" y2="17"></line>
            <line x1="14" y1="11" x2="14" y2="17"></line>
          </svg>
        </button>
      </form>
    </div>
  </li>
<?php else: ?>
  <!-- Render File card -->
  <li id="file-<?php echo Str::escape($id); ?>" class="file-card file-item-card" data-filename="<?php echo Str::escape(strtolower($filename)); ?>" draggable="true">
    <div class="file-preview-container">
      <?php if ($isImage): ?>
        <?php
          $mediaModel = new Media();
          $mediaModel->id = $id;
          $mediaModel->filename = $filename;
          $mediaModel->path = $path;
          $mediaModel->mime = $mime;
          $mediaModel->site_id = $f['site_id'] ?? '';
          $mediaModel->focus_x = $f['focus_x'] ?? 50;
          $mediaModel->focus_y = $f['focus_y'] ?? 50;
          $thumbnailPath = $mediaModel->getSquareCropUrl(300);
        ?>
        <img src="<?php echo Str::escape($thumbnailPath); ?>" alt="<?php echo Str::escape(!empty($f['title']) ? $f['title'] : $filename); ?>" style="width: 100%; height: 100%; object-fit: cover; display: block;" />
      <?php else: ?>
        <div class="file-mime-placeholder" style="width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative;">
          <span class="icon-svg" style="width: 60%; height: 60%; color: var(--text-color);">
            <?php echo App::svg('file'); ?>
          </span>
          <span style="font-size: 0.65rem; font-weight: bold; text-transform: uppercase; background-color: var(--bg-color-inverse); color: var(--text-color-inverse); padding: 1px 4px; border-radius: 2px; position: absolute; bottom: 12px;"><?php echo Str::escape($ext); ?></span>
        </div>
      <?php endif; ?>
    </div>
    <div class="file-details">
      <h3 style="margin: 0 0 2px 0; font-size: 0.9rem; font-weight: bold; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo Str::escape(!empty($f['title']) ? $f['title'] : $filename); ?>">
        <?php echo Str::escape(!empty($f['title']) ? $f['title'] : $filename); ?>
      </h3>
      <div style="font-size: 0.75rem; color: color-mix(in srgb, var(--text-color) 50%, transparent); margin-bottom: 4px;">
        <?php echo Str::escape($createdAt); ?>
      </div>
      <div style="display: flex; gap: 12px; justify-content: flex-end; align-items: center; border-top: 1px solid color-mix(in srgb, var(--bg-color-inverse) 8%, var(--bg-color) 92%); padding-top: 6px; margin-top: 4px;">
        <a href="/admin/list/files/edit/<?php echo Str::escape($id); ?>" title="Edit Media" class="action-btn">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
            <path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
          </svg>
        </a>
        <a href="<?php echo Str::escape($path); ?>" target="_blank" title="View File" class="action-btn">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-8 11-8 8 8 8 8-4 8-11 8-11-8-11-8z"></path>
            <circle cx="12" cy="12" r="3"></circle>
          </svg>
        </a>
        <button type="button" class="action-copy-url action-btn" data-url="<?php echo Str::escape($path); ?>" title="Copy Path" style="background: none; border: none; padding: 0; cursor: pointer;">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
          </svg>
        </button>
        <form method="post" action="/admin/files/delete" style="display:inline; margin: 0;" class="ajax-delete">
          <input type="hidden" name="csrf" value="<?php echo Str::escape($csrf ?? ''); ?>">
          <input type="hidden" name="id" value="<?php echo Str::escape($id); ?>">
          <button type="submit" title="Delete File" class="action-btn" style="background: none; border: none; padding: 0; cursor: pointer;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="3 6 5 6 21 6"></polyline>
              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
              <line x1="10" y1="11" x2="10" y2="17"></line>
              <line x1="14" y1="11" x2="14" y2="17"></line>
            </svg>
          </button>
        </form>
      </div>
    </div>
  </li>
<?php endif; ?>
