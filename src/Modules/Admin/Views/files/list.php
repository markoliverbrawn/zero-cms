<?php
// src/Modules/Admin/Views/files/list.php

use Zero\Core\App;

$errorMessage = $_SESSION['error'] ?? null;
unset($_SESSION['error']);
$currentFolder = $folder ?? '';
?>
<div class="listrecords files">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
    <h2>Media Library</h2>
    <div style="display: flex; gap: 10px; flex: 1; max-width: 500px; min-width: 250px;">
      <input type="text" id="files-search-input" placeholder="Search files by name..." style="padding: 8px 12px; width: 100%; border: 1px solid color-mix(in srgb, var(--bg-color-inverse) 20%, var(--bg-color) 80%);">
      <button type="button" id="create-folder-btn" style="white-space: nowrap; font-weight: bold; width: auto; padding: 8px 16px;">+ Create Folder</button>
    </div>
  </div>

  <!-- Breadcrumbs navigation -->
  <div class="breadcrumbs" style="font-size: 0.95rem; margin-bottom: 25px; padding-bottom: 10px; border-bottom: 1px solid color-mix(in srgb, var(--bg-color-inverse) 10%, var(--bg-color) 90%); display: flex; align-items: center; flex-wrap: wrap;">
    <span style="font-weight: bold; margin-right: 8px; color: color-mix(in srgb, var(--text-color) 50%, transparent);">Location:</span>
    <a href="?folder=" style="text-decoration: none; font-weight: bold; color: var(--text-color);">Root</a>
    <?php if (!empty($currentFolder)): ?>
      <?php
        $parts = explode('/', $currentFolder);
        $accumulated = '';
        foreach ($parts as $part):
          $accumulated = !empty($accumulated) ? $accumulated . '/' . $part : $part;
      ?>
        <span style="margin: 0 8px; color: color-mix(in srgb, var(--text-color) 30%, transparent);">/</span>
        <a href="?folder=<?php echo htmlspecialchars($accumulated, ENT_QUOTES, 'UTF-8'); ?>" style="text-decoration: none; color: var(--text-color);"><?php echo htmlspecialchars($part, ENT_QUOTES, 'UTF-8'); ?></a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php if ($errorMessage): ?>
    <div class="error-banner" style="background-color: #f8d7da; color: #721c24; padding: 12px; border: 1px solid #f5c6cb; margin-bottom: 20px;">
      <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
    </div>
  <?php endif; ?>

  <!-- Hidden folder creation form -->
  <form method="post" id="create-folder-form" style="display: none;">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, "UTF-8"); ?>">
    <input type="hidden" name="action" value="create_folder">
    <input type="hidden" name="folder" value="<?php echo htmlspecialchars($currentFolder, ENT_QUOTES, "UTF-8"); ?>">
    <input type="hidden" name="folder_name" id="folder-name-input">
  </form>

  <!-- Drag and drop modern upload area -->
  <form method="post" enctype="multipart/form-data" id="media-upload-form" style="margin-bottom: 30px; display: block;">
    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, "UTF-8"); ?>">
    <input type="hidden" name="folder" value="<?php echo htmlspecialchars($currentFolder, ENT_QUOTES, "UTF-8"); ?>">
    
    <div id="media-drag-drop-zone" class="media-upload-zone">
      <span class="icon-svg media-upload-icon">
        <?php echo App::svg('upload'); ?>
      </span>
      <strong style="display: block; margin-bottom: 5px;">Click to select or drag files here to upload</strong>
      <span style="font-size: 0.85rem; color: color-mix(in srgb, var(--text-color) 60%, transparent);">Uploading into active folder: <strong><?php echo !empty($currentFolder) ? htmlspecialchars($currentFolder, ENT_QUOTES, 'UTF-8') : 'Root'; ?></strong></span>
      <input type="file" name="file" id="media-file-input" required style="display: none;">
    </div>
    <div id="media-upload-actions" style="display: none; margin-top: 15px; align-items: center; gap: 15px;">
      <span id="selected-file-name" style="font-weight: bold; font-size: 0.9rem;"></span>
      <button type="submit" style="padding: 8px 16px; font-weight: bold; cursor: pointer;">Upload Selected File</button>
      <button type="button" id="cancel-upload-btn" style="background: none; border: 1px solid var(--bg-color-inverse); color: var(--text-color); padding: 8px 16px; cursor: pointer;">Cancel</button>
    </div>
  </form>

  <?php if (empty($files) && empty($currentFolder)): ?>
    <p id="no-files-message">No files or folders found.</p>
    <ul id="files-grid" style="display: none;" data-has-more="<?php echo ($hasMore ?? false) ? 'true' : 'false'; ?>" data-current-page="<?php echo $currentPage ?? 1; ?>" data-folder="<?php echo htmlspecialchars($currentFolder, ENT_QUOTES, 'UTF-8'); ?>"></ul>
  <?php else: ?>
    <ul id="files-grid" data-has-more="<?php echo ($hasMore ?? false) ? 'true' : 'false'; ?>" data-current-page="<?php echo $currentPage ?? 1; ?>" data-folder="<?php echo htmlspecialchars($currentFolder, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Render go-up folder tile if we are in a subfolder -->
    <?php if (!empty($currentFolder)): ?>
      <?php
        $parts = explode('/', $currentFolder);
        array_pop($parts);
        $parentFolder = implode('/', $parts);
      ?>
      <li class="file-card folder-card go-up-card" style="cursor: pointer;" data-folder="<?php echo htmlspecialchars($parentFolder, ENT_QUOTES, 'UTF-8'); ?>" data-fid="parent">
        <div class="file-preview-container">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: color-mix(in srgb, var(--text-color) 60%, transparent);">
            <path d="M19 12H5M12 19l-7-7 7-7"></path>
          </svg>
        </div>
        <div class="file-details" style="text-align: center;">
          <h3 style="margin: 0; font-size: 0.9rem; font-weight: bold; color: color-mix(in srgb, var(--text-color) 70%, transparent);">.. (parent folder)</h3>
        </div>
      </li>
    <?php endif; ?>

    <!-- Loop and include each media item inside decoupled dynamic card grid -->
    <?php foreach ($files as $f): ?>
      <?php
        $isImage = !empty($f['mime']) && str_starts_with($f['mime'], 'image/');
        $filename = $f['filename'] ?? '';
        $path = $f['path'] ?? '';
        $id = $f['id'] ?? '';
        $createdAt = $f['created_at'] ?? '';
        $mime = $f['mime'] ?? '';
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        include APPLICATION_ROOT . '/src/Modules/Admin/Views/files/card.php';
      ?>
    <?php endforeach; ?>
    </ul>
    
    <div id="infinite-scroll-loading" style="display: none; text-align: center; padding: 20px 0; font-weight: bold; color: var(--text-muted);">
      <span style="display: inline-flex; align-items: center; gap: 8px;">
        <svg class="animate-spin" style="width: 20px; height: 20px; animation: spin 1s linear infinite;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
          <path d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z" fill="currentColor"></path>
        </svg>
        Loading more files...
      </span>
    </div>

    <style>
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    </style>
    
    <p id="no-filtered-files-message" style="display: none; text-align: center; padding: 40px; font-weight: bold;">No files match your search.</p>
  <?php endif; ?>

  <!-- Floating batch selection actions bar -->
  <div id="batch-actions-bar" style="display: none; position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background-color: var(--bg-color-inverse); color: var(--text-color-inverse); padding: 12px 24px; border-radius: 30px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); z-index: 1000; align-items: center; gap: 15px;">
    <span id="batch-selected-count" style="font-weight: bold; font-size: 0.95rem;">0 items selected</span>
    <button id="batch-delete-btn" style="background-color: #ef4444; color: white; border: none; padding: 6px 16px; border-radius: 20px; font-weight: bold; cursor: pointer; font-size: 0.85rem;">Delete Selected</button>
    <button id="batch-clear-btn" style="background: none; border: 1px solid var(--text-color-inverse); color: var(--text-color-inverse); padding: 6px 16px; border-radius: 20px; font-weight: bold; cursor: pointer; font-size: 0.85rem;">Cancel</button>
  </div>
</div>

<script src="/assets/js/admin/files.js"></script>
