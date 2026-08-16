<?php
// src/Modules/Admin/Views/files/edit.php
// Highly polished, modern, and style-separated media item edit template.

use Zero\Core\App;
use Zero\Support\Str;

$titleValue = $file['title'] ?? '';
$filenameValue = $file['filename'] ?? '';
$pathValue = $file['path'] ?? '';
$mimeValue = $file['mime'] ?? '';
$folderValue = $file['folder'] ?? '';
$idValue = $file['id'] ?? '';
$createdAt = $file['created_at'] ?? '';
?>
<div class="edit-media-wrapper">
  <!-- Top Action bar with back button -->
  <div class="top-bar">
    <a href="/admin/list/files<?php echo !empty($folderValue) ? '?folder=' . urlencode($folderValue) : ''; ?>" class="btn-back">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="19" y1="12" x2="5" y2="12"></line>
        <polyline points="12 19 5 12 12 5"></polyline>
      </svg>
      Back to Media Library
    </a>
    <h2>Media Asset Manager</h2>
  </div>

  <?php if (isset($_SESSION['success'])): ?>
    <div class="success-banner">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="banner-icon">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
        <polyline points="22 4 12 14.01 9 11.01"></polyline>
      </svg>
      <span><?php echo Str::escape($_SESSION['success']); unset($_SESSION['success']); ?></span>
    </div>
  <?php endif; ?>
  
  <?php if (isset($_SESSION['error'])): ?>
    <div class="error-banner">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="banner-icon">
        <circle cx="12" cy="12" r="10"></circle>
        <line x1="12" y1="8" x2="12" y2="12"></line>
        <line x1="12" y1="16" x2="12.01" y2="16"></line>
      </svg>
      <span><?php echo Str::escape($_SESSION['error']); unset($_SESSION['error']); ?></span>
    </div>
  <?php endif; ?>

  <div class="edit-media-grid">
    <!-- Left Hand: Interactive Form Controls -->
    <div class="card form-card">
      <div class="card-header">
        <h3>Asset Settings</h3>
      </div>
      <form method="post" enctype="multipart/form-data" class="modern-form">
        <input type="hidden" name="csrf" value="<?php echo Str::escape($csrf); ?>">
        
        <div class="form-group">
          <label for="title-input">Asset Title</label>
          <?php echo App::makeFormField('text', 'title', [
              'value' => $titleValue,
              'required' => true,
              'attributes' => ['id' => 'title-input', 'placeholder' => 'Enter a descriptive title for this file...'],
              'showLabel' => false,
              'guessHelperTextKey' => false,
          ])->render(); ?>
          <span class="help-text">Used for accessibility alternative descriptions and back-office search indexing.</span>
        </div>

        <?php if (str_starts_with($mimeValue, 'image/')): ?>
          <div class="form-group-row">
            <div class="form-group half">
              <label for="focus-x-input">Crop Focus X (%)</label>
              <?php echo App::makeFormField('number', 'focus_x', [
                  'value' => $file['focus_x'] ?? 50,
                  'required' => true,
                  'min' => 0,
                  'max' => 100,
                  'attributes' => ['id' => 'focus-x-input'],
                  'showLabel' => false,
                  'guessHelperTextKey' => false,
              ])->render(); ?>
              <span class="help-text">Side-to-side alignment focusing.</span>
            </div>
            <div class="form-group half">
              <label for="focus-y-input">Crop Focus Y (%)</label>
              <?php echo App::makeFormField('number', 'focus_y', [
                  'value' => $file['focus_y'] ?? 50,
                  'required' => true,
                  'min' => 0,
                  'max' => 100,
                  'attributes' => ['id' => 'focus-y-input'],
                  'showLabel' => false,
                  'guessHelperTextKey' => false,
              ])->render(); ?>
              <span class="help-text">Top-to-bottom alignment focusing.</span>
            </div>
          </div>
        <?php endif; ?>

        <div class="form-group-row">
          <div class="form-group half">
            <label>Filename (Read-Only)</label>
            <div class="readonly-field"><?php echo Str::escape($filenameValue); ?></div>
          </div>
          <div class="form-group half">
            <label>MIME Format (Read-Only)</label>
            <div class="readonly-field"><?php echo Str::escape($mimeValue); ?></div>
          </div>
        </div>

        <div class="form-group">
          <label>Public Web Accessible Link</label>
          <div class="copy-input-wrapper">
            <input type="text" id="public-url-input" class="readonly-field-input" value="<?php echo Str::escape($pathValue); ?>" readonly>
            <button type="button" id="copy-link-btn" class="btn-copy">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px; margin-right: 6px;">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
              </svg>
              Copy Link
            </button>
            <span id="copy-success-notice" class="copy-success-notice">Copied!</span>
          </div>
        </div>

        <!-- Custom Styled File Re-uploader zone -->
        <div class="form-group">
          <label>Replace Physical File</label>
          <div class="modern-file-reupload-wrapper">
            <label for="reupload-file-input" class="reupload-dropzone">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="reupload-icon">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="17 8 12 3 7 8"></polyline>
                <line x1="12" y1="3" x2="12" y2="15"></line>
              </svg>
              <strong>Choose a new file to replace this asset</strong>
              <span>Select another file from your computer (optional)</span>
              <?php echo App::makeFormField('file', 'file', [
                  'attributes' => ['id' => 'reupload-file-input'],
                  'showLabel' => false,
                  'guessHelperTextKey' => false,
              ])->render(); ?>
            </label>
            <div id="selected-file-notice" class="file-notice" style="display: none;"></div>
          </div>
          <span class="help-text">Replacing the physical asset overwrites the file on disk while preserving its current UUID and links.</span>
        </div>

        <div class="form-actions">
          <button type="submit" name="submit_action" value="save_return" class="btn-save">Save & Return</button>
          <button type="submit" name="submit_action" value="save_continue" class="btn-continue">Save & Continue</button>
          <a href="/admin/list/files<?php echo !empty($folderValue) ? '?folder=' . urlencode($folderValue) : ''; ?>" class="btn-cancel">Discard</a>
        </div>
      </form>
    </div>

    <!-- Right Hand: Modern Immersive Asset Preview -->
    <div class="card preview-card">
      <div class="card-header">
        <h3>File Preview</h3>
      </div>
      <div class="card-body">
        <div class="immersive-preview-box">
          <?php if (str_starts_with($mimeValue, 'image/')): ?>
            <div class="checkered-background">
              <div class="focal-container" id="focal-container">
                <img src="<?php echo Str::escape($pathValue); ?>" alt="<?php echo Str::escape($titleValue ?: $filenameValue); ?>" class="preview-media-image" id="preview-image">
                <div class="focal-overlay" id="focal-overlay">
                  <div class="focal-square" id="focal-square">
                    <div class="focal-crosshair"></div>
                  </div>
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="preview-generic-doc">
              <span class="icon-svg-wrapper">
                <?php echo App::svg('file'); ?>
              </span>
              <span class="doc-extension-badge"><?php echo Str::escape(strtoupper(pathinfo($filenameValue, PATHINFO_EXTENSION))); ?></span>
              <span class="doc-mime-label"><?php echo Str::escape($mimeValue); ?></span>
            </div>
          <?php endif; ?>
        </div>
        
        <div class="metadata-details-list">
          <div class="meta-row">
            <span class="meta-label">Unique Record ID (UUID)</span>
            <span class="meta-val monospace-val"><?php echo Str::escape($idValue); ?></span>
          </div>
          <div class="meta-row">
            <span class="meta-label">Uploaded / Created</span>
            <span class="meta-val"><?php echo Str::escape($createdAt); ?></span>
          </div>
          <div class="meta-row">
            <span class="meta-label">Parent Folder Location</span>
            <span class="meta-val">/uploads/<?php echo !empty($folderValue) ? Str::escape($folderValue) : 'Root'; ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if (str_starts_with($mimeValue, 'image/')): ?>
<script nonce="<?php echo \Zero\Core\App::getNonce(); ?>">
document.addEventListener('DOMContentLoaded', function() {
    const focalOverlay = document.getElementById('focal-overlay');
    const focalSquare = document.getElementById('focal-square');
    const focusXInput = document.getElementById('focus-x-input');
    const focusYInput = document.getElementById('focus-y-input');
    const img = document.getElementById('preview-image');

    let isDragging = false;

    // Dynamically toggle inputs' states based on portrait vs landscape image orientation
    function updateInputStates() {
        const naturalWidth = img.naturalWidth;
        const naturalHeight = img.naturalHeight;

        if (!naturalWidth || !naturalHeight) return;

        if (naturalHeight > naturalWidth) {
            // Portrait: X is locked at 50, Y is draggable up and down
            focusXInput.value = 50;
            focusXInput.readOnly = true;
            focusXInput.style.opacity = '0.5';
            focusXInput.style.cursor = 'not-allowed';
            
            focusYInput.readOnly = false;
            focusYInput.style.opacity = '1';
            focusYInput.style.cursor = 'auto';
        } else {
            // Landscape/Square: Y is locked at 50, X is draggable side to side
            focusYInput.value = 50;
            focusYInput.readOnly = true;
            focusYInput.style.opacity = '0.5';
            focusYInput.style.cursor = 'not-allowed';
            
            focusXInput.readOnly = false;
            focusXInput.style.opacity = '1';
            focusXInput.style.cursor = 'auto';
        }
    }

    // Position focal square dynamically based on percentage constraints
    function updateSquarePosition() {
        const naturalWidth = img.naturalWidth;
        const naturalHeight = img.naturalHeight;

        if (!naturalWidth || !naturalHeight) {
            focalSquare.style.width = '60px';
            focalSquare.style.height = '60px';
            focalSquare.style.left = '50%';
            focalSquare.style.top = '50%';
            return;
        }

        let pctX = parseFloat(focusXInput.value);
        if (isNaN(pctX)) pctX = 50;
        let pctY = parseFloat(focusYInput.value);
        if (isNaN(pctY)) pctY = 50;

        if (naturalHeight > naturalWidth) {
            // Portrait: Box is as wide as the image (100% width), constrained vertically
            const pctHeight = (naturalWidth / naturalHeight) * 100;
            const halfPctHeight = pctHeight / 2;

            pctX = 50;
            pctY = Math.max(halfPctHeight, Math.min(100 - halfPctHeight, pctY));

            focalSquare.style.width = '100%';
            focalSquare.style.height = pctHeight + '%';
            focalSquare.style.left = '50%';
            focalSquare.style.top = pctY + '%';
        } else {
            // Landscape/Square: Box is as tall as the image (100% height), constrained horizontally
            const pctWidth = (naturalHeight / naturalWidth) * 100;
            const halfPctWidth = pctWidth / 2;

            pctX = Math.max(halfPctWidth, Math.min(100 - halfPctWidth, pctX));
            pctY = 50;

            focalSquare.style.width = pctWidth + '%';
            focalSquare.style.height = '100%';
            focalSquare.style.left = pctX + '%';
            focalSquare.style.top = '50%';
        }

        focusXInput.value = Math.round(pctX);
        focusYInput.value = Math.round(pctY);
    }

    // Set crop center coordinates based on clicking/dragging over overlay boundaries
    function setCoordinates(clientX, clientY) {
        const rect = focalOverlay.getBoundingClientRect();
        const naturalWidth = img.naturalWidth;
        const naturalHeight = img.naturalHeight;

        if (!naturalWidth || !naturalHeight) return;

        let x = clientX - rect.left;
        let y = clientY - rect.top;

        // Force position coordinates inside container boundaries
        x = Math.max(0, Math.min(rect.width, x));
        y = Math.max(0, Math.min(rect.height, y));

        let pctX = 50;
        let pctY = 50;

        if (naturalHeight > naturalWidth) {
            // Portrait: Only vertical drag, locked X at 50%
            const pctHeight = (naturalWidth / naturalHeight) * 100;
            const halfPctHeight = pctHeight / 2;
            const currentPctY = (y / rect.height) * 100;

            pctY = Math.round(Math.max(halfPctHeight, Math.min(100 - halfPctHeight, currentPctY)));
        } else {
            // Landscape: Only horizontal drag, locked Y at 50%
            const pctWidth = (naturalHeight / naturalWidth) * 100;
            const halfPctWidth = pctWidth / 2;
            const currentPctX = (x / rect.width) * 100;

            pctX = Math.round(Math.max(halfPctWidth, Math.min(100 - halfPctWidth, currentPctX)));
        }

        focusXInput.value = pctX;
        focusYInput.value = pctY;

        updateSquarePosition();
    }

    // Handle standard mouse event flows
    focalOverlay.addEventListener('mousedown', function(e) {
        isDragging = true;
        setCoordinates(e.clientX, e.clientY);
        e.preventDefault();
    });

    document.addEventListener('mousemove', function(e) {
        if (!isDragging) return;
        setCoordinates(e.clientX, e.clientY);
    });

    document.addEventListener('mouseup', function() {
        isDragging = false;
    });

    // Touch support for modern mobile devices
    focalOverlay.addEventListener('touchstart', function(e) {
        isDragging = true;
        if (e.touches.length > 0) {
            setCoordinates(e.touches[0].clientX, e.touches[0].clientY);
        }
        e.preventDefault();
    }, { passive: false });

    document.addEventListener('touchmove', function(e) {
        if (!isDragging) return;
        if (e.touches.length > 0) {
            setCoordinates(e.touches[0].clientX, e.touches[0].clientY);
        }
    }, { passive: false });

    document.addEventListener('touchend', function() {
        isDragging = false;
    });

    // Sync manual numerical fields with UI bounds
    focusXInput.addEventListener('input', function() {
        let val = parseInt(focusXInput.value);
        if (isNaN(val)) val = 50;
        focusXInput.value = Math.max(0, Math.min(100, val));
        updateSquarePosition();
    });

    focusYInput.addEventListener('input', function() {
        let val = parseInt(focusYInput.value);
        if (isNaN(val)) val = 50;
        focusYInput.value = Math.max(0, Math.min(100, val));
        updateSquarePosition();
    });

    // Hook listeners for initial load states
    function initFocalSelector() {
        updateInputStates();
        updateSquarePosition();
    }

    if (img.complete) {
        initFocalSelector();
    } else {
        img.addEventListener('load', initFocalSelector);
    }
});
</script>
<?php endif; ?>

<script nonce="<?php echo \Zero\Core\App::getNonce(); ?>">
document.addEventListener('DOMContentLoaded', function() {
    const editForm = document.querySelector('.modern-form');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Determine which button triggered the submit
            let submitAction = 'save_return';
            if (e.submitter) {
                submitAction = e.submitter.value;
            }

            const fd = new FormData(editForm);
            // Append the submit action value to FormData so backend gets it
            fd.append('submit_action', submitAction);

            // Disable buttons to prevent duplicate submission
            const submitButtons = editForm.querySelectorAll('button[type="submit"]');
            submitButtons.forEach(btn => btn.disabled = true);

            // Create or select bottom-left toast notification matching other edit forms
            let toast = document.getElementById('ajax-save-toast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'ajax-save-toast';
                toast.className = 'toast-notification';
                document.body.appendChild(toast);
            }

            // Perform AJAX submit using FormData POST
            fetch(window.location.pathname, {
                method: 'POST',
                body: fd,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(res => {
                submitButtons.forEach(btn => btn.disabled = false);
                if (res.ok) {
                    return res.json();
                }
                throw new Error('Save failed');
            })
            .then(data => {
                if (data.success) {
                    if (submitAction === 'save_return') {
                        // Redirect back to media listing with saved=1 parameter so the list page triggers the toast
                        var redirectUrl = data.redirect || ('/admin/list/files' + (data.folder ? '?folder=' + encodeURIComponent(data.folder) : ''));
                        redirectUrl += (redirectUrl.indexOf('?') !== -1 ? '&' : '?') + 'saved=1';
                        window.location.href = redirectUrl;
                    } else {
                        // Save & Continue: Show springy toast
                        toast.textContent = 'Changes saved successfully!';
                        toast.className = 'toast-notification success';
                        
                        // Force reflow
                        toast.offsetHeight;

                        toast.classList.add('show');
                        
                        setTimeout(function() {
                            toast.classList.remove('show');
                        }, 3000);

                        // If a file was replaced, the image src needs to be updated with a fresh cache-busted URL!
                        if (data.new_path) {
                            const img = document.getElementById('preview-image');
                            if (img) {
                                // Reload with query string to bust browser cache
                                img.src = data.new_path + '?t=' + new Date().getTime();
                            }
                            const publicInput = document.getElementById('public-url-input');
                            if (publicInput) {
                                publicInput.value = data.new_path;
                            }
                        }
                    }
                } else {
                    alert('Failed to save record: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(err => {
                submitButtons.forEach(btn => btn.disabled = false);
                alert('Error saving record: ' + err.message);
            });
        });
    }
});
</script>
