// Zero CMS - Core Administrative WYSIWYG Editor and Back-Office Controllers


// WYSIWYG editor: global helper to insert node at caret
function insertNodeAtCaret(editor, node) {
  var sel = window.getSelection();
  if (sel.getRangeAt && sel.rangeCount) {
    var range = sel.getRangeAt(0);
    range.deleteContents();
    range.insertNode(node);
    range.setStartAfter(node);
    range.collapse(true);
    sel.removeAllRanges();
    sel.addRange(range);
  } else {
    editor.appendChild(node);
  }
}

// Escape HTML securely
function escapeHtml(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// Global WYSIWYG initializer
window.initEditor = function(editorContainer) {
  if (editorContainer.dataset.initialized) return;
  editorContainer.dataset.initialized = 'true';

  var editor = editorContainer.querySelector('.editor-area[contenteditable="true"]');
  var toolbar = editorContainer.querySelector('.toolbar');
  var contentInput = editorContainer.querySelector('.content-input');
  if (!editor || !toolbar) return; 

  // Store range reference securely on the element node itself
  editor._savedRange = null;

  function saveEditorSelection() {
    var sel = window.getSelection();
    if (sel.rangeCount > 0) {
      var range = sel.getRangeAt(0);
      if (editor.contains(range.commonAncestorContainer)) {
        editor._savedRange = range.cloneRange();
      }
    }
  }

  function restoreEditorSelection() {
    var sel = window.getSelection();
    sel.removeAllRanges();
    if (editor._savedRange) {
      sel.addRange(editor._savedRange);
    }
  }

  // Handle all toolbar actions safely isolated inside THIS container
  toolbar.addEventListener('mousedown', function(e) {
    var btn = e.target.closest('[data-cmd]');
    if (!btn) return; // Ignore clicks on toolbar background gaps

    e.preventDefault(); // Prevents toolbar from stealing cursor focus from editor
    
    var cmd = btn.getAttribute('data-cmd');

    if (cmd === 'createLink') {
      var url = prompt('Enter URL', 'https://');
      if (url) document.execCommand('createLink', false, url);
    } else if (cmd === 'insertImage') {
      saveEditorSelection(); // Preserve caret position before opening modal
      window.openImagePicker(function(file){
        restoreEditorSelection();
        
        var img = document.createElement('img');
        img.src = file.path;
        img.alt = file.filename;
        img.style.maxWidth = '100%';
        
        insertNodeAtCaret(editor, img);
      });
    } else if (cmd === 'insertTable') {
      saveEditorSelection(); // Preserve caret position before opening dropdown
      activeTableEditor = editor;
      activeTableButton = btn;
      
      // Position and toggle our beautiful HTML dropdown panel right below the clicked Table toolbar button
      var btnRect = btn.getBoundingClientRect();
      tableDropdown.style.left = (btnRect.left + window.scrollX) + "px";
      tableDropdown.style.top = (btnRect.bottom + window.scrollY + 6) + "px";
      tableDropdown.style.display = "block";
    } else if (cmd === 'insertSmall') {
      var selection = window.getSelection();
      if (selection.rangeCount > 0) {
        var range = selection.getRangeAt(0);
        var selectedText = range.toString() || 'Version 1.0';
        var small = document.createElement('small');
        small.textContent = selectedText;
        range.deleteContents();
        range.insertNode(small);
      }
    } else {
      document.execCommand(cmd, false, null);
    }
    
    editor.focus();
  });

  // Tracking caret position changes locally
  editor.addEventListener('keyup', saveEditorSelection);
  editor.addEventListener('mouseup', saveEditorSelection);
  editor.addEventListener('blur', saveEditorSelection);

  // Sync data to the hidden field on form submit
  var form = editorContainer.closest('form');
  if (form && contentInput) {
    form.addEventListener('submit', function(){
      contentInput.value = editor.innerHTML;
    });
  }
};

// Unified Image Picker System
window.openImagePicker = function(onSelect) {
  var fileModal = document.getElementById('file-modal');
  if (!fileModal) return;

  // Track the active folder inside the picker modal
  window.pickerActiveFolder = '';

  // Render our beautiful modal interior if we haven't already
  if (!fileModal.dataset.gorgeousPickerInitialized) {
    fileModal.innerHTML = `
      <div class="modal-content">
        <div class="modal-header">
          <h3>Select Media File</h3>
          <button type="button" class="modal-close-btn" id="file-modal-close">&times;</button>
        </div>
        <div class="picker-breadcrumbs" id="picker-breadcrumbs" style="font-size: 0.8rem; margin-bottom: 15px; padding-bottom: 5px; border-bottom: 1px dashed #ccc;"></div>
        <div class="picker-controls">
          <input type="text" class="picker-search-input" id="picker-search" placeholder="Search files by name...">
          
          <div class="picker-upload-section" id="picker-dropzone">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-color); margin-bottom: 5px;">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"></path>
            </svg>
            <strong>Click or drag file here to upload</strong>
            <span id="picker-active-folder-label" style="font-size: 0.75rem; color: #777;">Uploading to: Root</span>
            <input type="file" id="picker-file-input" style="display: none;" accept="image/*,video/*,application/pdf">
            <div id="picker-upload-progress" style="display: none; margin-top: 5px; font-weight: bold; font-size: 0.8rem; color: #3498db;">Uploading...</div>
          </div>
        </div>
        <div class="picker-grid-container">
          <div class="picker-grid" id="picker-file-grid">Loading media files...</div>
        </div>
        <div class="picker-footer">
          <button type="button" class="btn" id="picker-close-btn">Close</button>
        </div>
      </div>
    `;
    fileModal.dataset.gorgeousPickerInitialized = 'true';

    // Set up standard close event listeners
    var closeBtn = fileModal.querySelector('#file-modal-close');
    var footerCloseBtn = fileModal.querySelector('#picker-close-btn');
    var closeFn = function() {
      fileModal.style.display = 'none';
    };
    if (closeBtn) closeBtn.onclick = closeFn;
    if (footerCloseBtn) footerCloseBtn.onclick = closeFn;

    // Set up search and upload logic once
    setupPickerLogic(fileModal);
  } else {
    // Reset path label to Root on subsequent opens
    var folderLabel = fileModal.querySelector('#picker-active-folder-label');
    if (folderLabel) folderLabel.innerHTML = 'Uploading to: <strong>Root</strong>';
  }

  // Save the callback globally or locally on the modal
  fileModal._onSelectCallback = onSelect;

  // Open the modal
  fileModal.style.display = 'block';

  // Load files from the server and render
  if (window.loadAndRenderPickerFiles) {
    window.loadAndRenderPickerFiles('');
  }
};

function setupPickerLogic(fileModal) {
  var grid = fileModal.querySelector('#picker-file-grid');
  var searchInput = fileModal.querySelector('#picker-search');
  var fileInput = fileModal.querySelector('#picker-file-input');
  var dropzone = fileModal.querySelector('#picker-dropzone');
  var progress = fileModal.querySelector('#picker-upload-progress');
  var breadcrumbsContainer = fileModal.querySelector('#picker-breadcrumbs');
  var activeFolderLabel = fileModal.querySelector('#picker-active-folder-label');

  var allFiles = [];

  window.loadAndRenderPickerFiles = function(searchQuery) {
    grid.innerHTML = '<div style="grid-column: span 3; text-align: center; padding: 20px;">Loading media files...</div>';
    
    var fetchPromise;
    if (allFiles.length > 0 && searchQuery !== undefined) {
      // Filter locally for super fast search response!
      fetchPromise = Promise.resolve(allFiles);
    } else {
      fetchPromise = fetch('/api/v1/admin/files', {credentials: 'same-origin'})
        .then(function(r) { return r.json(); })
        .then(function(files) {
          allFiles = files;
          return files;
        });
    }

    fetchPromise.then(function(files) {
      var query = (searchQuery || '').toLowerCase().trim();
      
      // Filter by query name OR filter by active folder if query is empty
      var filtered = [];
      if (query !== '') {
        // Global search across all folders!
        filtered = files.filter(function(f) {
          return f.filename.toLowerCase().indexOf(query) !== -1;
        });
      } else {
        // Browse specific folder!
        filtered = files.filter(function(f) {
          var fileFolder = f.folder || '';
          return fileFolder === window.pickerActiveFolder;
        });
      }

      // Update breadcrumbs UI
      renderBreadcrumbs();

      if (!filtered.length && query === '') {
        var emptyHtml = '';
        if (window.pickerActiveFolder !== '') {
          emptyHtml += `
            <div class="picker-item picker-go-up" style="cursor:pointer; display: flex; flex-direction: column; align-items: center; justify-content: center;">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color: #777; margin-bottom: 5px;">
                <path d="M19 12H5M12 19l-7-7 7-7"></path>
              </svg>
              <span style="font-size: 0.7rem; font-weight: bold;">Go Up</span>
            </div>
          `;
        }
        emptyHtml += '<div style="grid-column: span 3; text-align: center; padding: 20px; font-weight: bold;">This folder is empty.</div>';
        grid.innerHTML = emptyHtml;
        setupGoUpHandler();
        return;
      } else if (!filtered.length) {
        grid.innerHTML = '<div style="grid-column: span 3; text-align: center; padding: 20px;">No files found matching search query.</div>';
        return;
      }

      var html = '';
      
      // Add visual "Go Up" tile if we are inside a subfolder and not searching globally
      if (window.pickerActiveFolder !== '' && query === '') {
        html += `
          <div class="picker-item picker-go-up" style="cursor:pointer; display: flex; flex-direction: column; align-items: center; justify-content: center;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color: #777; margin-bottom: 5px;">
              <path d="M19 12H5M12 19l-7-7 7-7"></path>
            </svg>
            <span style="font-size: 0.75rem; font-weight: bold; color: #777;">Go Up</span>
          </div>
        `;
      }

      filtered.forEach(function(f) {
        var isImage = f.mime && f.mime.startsWith('image/');
        var isDir = f.mime === 'directory';
        var thumbnailHtml = '';

        if (isDir) {
          thumbnailHtml = `
            <div class="picker-item-mime-icon">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-color);">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
              </svg>
            </div>
          `;
        } else if (isImage) {
          thumbnailHtml = '<img src="' + f.path + '" alt="' + escapeHtml(f.filename) + '">';
        } else {
          var ext = f.filename.split('.').pop() || 'file';
          thumbnailHtml = `
            <div class="picker-item-mime-icon" style="position: relative;">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--text-color);">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
              </svg>
              <span class="picker-item-mime-name" style="position: absolute; bottom: 12px; font-size: 0.6rem; font-weight: bold; text-transform: uppercase; background-color: var(--bg-color-inverse); color: var(--text-color-inverse); padding: 1px 4px; border-radius: 2px;">${ext}</span>
            </div>
          `;
        }

        html += `
          <div class="picker-item ${isDir ? 'picker-dir-item' : 'picker-file-item'}" data-path="${f.path}" data-filename="${escapeHtml(f.filename)}" data-fid="${f.id}" data-mime="${f.mime}">
            ${thumbnailHtml}
            <div class="picker-item-name" title="${escapeHtml(f.filename)}">${escapeHtml(f.filename)}</div>
          </div>
        `;
      });
      grid.innerHTML = html;

      // Handle folder clicks and file clicks
      grid.querySelectorAll('.picker-item').forEach(function(item) {
        item.onclick = function(e) {
          e.preventDefault();
          var path = item.getAttribute('data-path');
          var filename = item.getAttribute('data-filename');
          var id = item.getAttribute('data-fid');
          var mime = item.getAttribute('data-mime');

          if (mime === 'directory') {
            // Navigate inside the folder!
            window.pickerActiveFolder = window.pickerActiveFolder ? window.pickerActiveFolder + '/' + filename : filename;
            searchInput.value = ''; // Reset search on navigate
            window.loadAndRenderPickerFiles('');
          } else if (item.classList.contains('picker-go-up')) {
            // Handled below
          } else {
            // Select the file!
            if (fileModal._onSelectCallback) {
              fileModal._onSelectCallback({
                path: path,
                filename: filename,
                id: id
              });
            }
            fileModal.style.display = 'none';
          }
        };
      });

      setupGoUpHandler();
    }).catch(function(err) {
      grid.innerHTML = '<div style="grid-column: span 3; text-align: center; padding: 20px; color: red;">Failed to load files: ' + err.message + '</div>';
    });
  };

  function setupGoUpHandler() {
    var goUpBtn = grid.querySelector('.picker-go-up');
    if (goUpBtn) {
      goUpBtn.onclick = function(e) {
        e.preventDefault();
        var parts = window.pickerActiveFolder.split('/');
        parts.pop();
        window.pickerActiveFolder = parts.join('/');
        searchInput.value = '';
        window.loadAndRenderPickerFiles('');
      };
    }
  }

  function renderBreadcrumbs() {
    var breadcrumbsHtml = `<strong style="margin-right: 5px;">Location:</strong> <a href="#" class="picker-breadcrumb-link" data-path="">Root</a>`;
    if (window.pickerActiveFolder !== '') {
      var parts = window.pickerActiveFolder.split('/');
      var accumulated = '';
      parts.forEach(function(part) {
        accumulated = accumulated ? accumulated + '/' + part : part;
        breadcrumbsHtml += ` <span style="color: #ccc; margin: 0 4px;">/</span> <a href="#" class="picker-breadcrumb-link" data-path="${accumulated}">${part}</a>`;
      });
    }
    breadcrumbsContainer.innerHTML = breadcrumbsHtml;

    // Set up click handlers on breadcrumbs
    breadcrumbsContainer.querySelectorAll('.picker-breadcrumb-link').forEach(function(link) {
      link.onclick = function(e) {
        e.preventDefault();
        window.pickerActiveFolder = link.getAttribute('data-path');
        searchInput.value = '';
        window.loadAndRenderPickerFiles('');
      };
    });

    // Sync upload section description label
    activeFolderLabel.innerHTML = 'Uploading to: <strong>' + (window.pickerActiveFolder || 'Root') + '</strong>';
  }

  // Real-time search/filter
  searchInput.oninput = function() {
    window.loadAndRenderPickerFiles(searchInput.value);
  };

  // Trigger file selection on click
  dropzone.onclick = function(e) {
    if (e.target !== fileInput && progress.style.display === 'none') {
      fileInput.click();
    }
  };

  // Drag & drop logic
  dropzone.ondragover = function(e) {
    e.preventDefault();
    dropzone.classList.add('dragover');
  };

  // Drag & drop leave
  dropzone.ondragleave = function(e) {
    e.preventDefault();
    dropzone.classList.remove('dragover');
  };

  dropzone.ondrop = function(e) {
    e.preventDefault();
    dropzone.classList.remove('dragover');
    if (e.dataTransfer.files && e.dataTransfer.files.length) {
      handleFileUpload(e.dataTransfer.files[0]);
    }
  };

  // Handle standard file input change
  fileInput.onchange = function() {
    if (fileInput.files && fileInput.files.length) {
      handleFileUpload(fileInput.files[0]);
    }
  };

  function handleFileUpload(file) {
    if (!file) return;

    // Show uploading status
    progress.style.display = 'block';
    progress.textContent = 'Uploading: 0%';

    var fd = new FormData();
    fd.append('file', file);
    fd.append('folder', window.pickerActiveFolder); // Upload directly to active picker directory!
    
    // Get csrf token from page
    var csrfInput = document.querySelector('input[name="csrf"]');
    var csrfToken = csrfInput ? csrfInput.value : '';
    if (csrfToken) {
      fd.append('csrf', csrfToken);
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/admin/files/upload', true);
    xhr.withCredentials = true;

    // Progress updates!
    xhr.upload.onprogress = function(e) {
      if (e.lengthComputable) {
        var percent = Math.round((e.loaded / e.total) * 100);
        progress.textContent = 'Uploading: ' + percent + '%';
      }
    };

    xhr.onload = function() {
      progress.style.display = 'none';
      if (xhr.status === 200) {
        try {
          var res = JSON.parse(xhr.responseText);
          if (res.success) {
            // Success! Refresh the list by re-fetching and clear search
            allFiles = []; // Clear cache to force reload
            searchInput.value = ''; // Clear search
            window.loadAndRenderPickerFiles('');
          } else {
            window.adminAlert('Upload Failed', res.error || 'Unknown error');
          }
        } catch(e) {
          window.adminAlert('Upload Error', 'Invalid server response');
        }
      } else {
        window.adminAlert('Upload Error', 'Upload failed with status ' + xhr.status);
      }
    };

    xhr.onerror = function() {
      progress.style.display = 'none';
      window.adminAlert('Upload Error', 'Network error occurred during upload.');
    };

    xhr.send(fd);
  }
}

// WYSIWYG editor: toolbar and file manager integration on load
document.addEventListener('DOMContentLoaded', function(){
  // Dynamic top-level URL query parameter notification toast triggers
  var urlParams = new URLSearchParams(window.location.search);
  if (urlParams.has('saved') || urlParams.has('success')) {
      var toast = document.getElementById('ajax-save-toast');
      if (!toast) {
        toast = document.createElement('div');
        toast.id = 'ajax-save-toast';
        toast.className = 'toast-notification';
        document.body.appendChild(toast);
      }
      toast.textContent = 'Changes saved successfully!';
      toast.className = 'toast-notification success';
      
      // Force reflow
      toast.offsetHeight;

      toast.classList.add('show');
      
      setTimeout(function() {
        toast.classList.remove('show');
      }, 3000);

      // Clean up parameters to prevent repeat toasts on refreshing
      var newUrl = window.location.pathname;
      var remainingParams = [];
      urlParams.forEach(function(value, key) {
          if (key !== 'saved' && key !== 'success') {
              remainingParams.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
          }
      });
      if (remainingParams.length > 0) {
          newUrl += '?' + remainingParams.join('&');
      }
      window.history.replaceState({}, document.title, newUrl);
  }

  // Global AJAX deleting of list items
  document.querySelectorAll('form.ajax-delete').forEach(function(form){
    form.addEventListener('submit', function(e){
      e.preventDefault();
      
      window.adminConfirm({
          title: 'Delete Confirmation',
          message: 'Are you sure you want to delete this item?',
          confirmText: 'Delete',
          confirmClass: 'btn-confirm'
      }).then(function(confirmed) {
          if (confirmed) {
              var id = form.querySelector('input[name="id"]').value;
              var actionUrl = form.getAttribute('action') || '';
              var parts = actionUrl.split('/');
              var modelName = parts[parts.length - 1];
              var csrfToken = form.querySelector('input[name="csrf"]')?.value || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

              fetch('/api/v1/admin/models/' + modelName + '/' + id, {
                method: 'DELETE',
                headers: {
                  'Content-Type': 'application/json',
                  'X-CSRF-Token': csrfToken
                },
                credentials: 'same-origin'
              })
              .then(function(res){ if (res.ok) return res.json(); throw new Error('Delete failed'); })
              .then(function(data){
                if (data.success) {
                  var row = document.getElementById('file-'+id) || form.closest('tr');
                  if (row) row.parentNode.removeChild(row);
                } else {
                  throw new Error(data.error || 'Delete failed');
                }
              }).catch(function(err){ 
                  window.adminConfirm({ 
                      title: 'Error', 
                      message: err.message, 
                      confirmText: 'OK', 
                      confirmClass: 'btn-confirm-primary' 
                  }); 
              });
          }
      });
    });
  });

  var fileModal = document.getElementById('file-modal');
  var fileModalCloseBtn = document.getElementById('file-modal-close');
  document.execCommand('defaultParagraphSeparator', false, 'p');

  if (fileModalCloseBtn) {
    fileModalCloseBtn.addEventListener('click', function(){ fileModal.style.display = 'none'; });
  }

  // Initialize each editor container on the page
  document.querySelectorAll('.editor').forEach(function(editorContainer) {
    window.initEditor(editorContainer);
  });

  // Global AJAX saving of admin edit forms
  var editForm = document.querySelector('.editrecord form');
  if (editForm) {
    editForm.addEventListener('submit', function(e) {
      e.preventDefault();

      // Determine which button triggered the submit
      var submitAction = 'save_return';
      if (e.submitter) {
        submitAction = e.submitter.value;
      }

      // Sync rich text editors before serializing form
      document.querySelectorAll('.editor').forEach(function(editorContainer) {
        var editor = editorContainer.querySelector('.editor-area[contenteditable="true"]');
        var contentInput = editorContainer.querySelector('.content-input');
        if (editor && contentInput) {
          contentInput.value = editor.innerHTML;
        }
      });

      var fd = new FormData(editForm);
      
      // Convert FormData to structured JSON object
      var obj = {};
      fd.forEach(function(value, key){
          if (key.endsWith('[]')) {
              var cleanKey = key.slice(0, -2);
              if (!obj[cleanKey]) {
                  obj[cleanKey] = [];
              }
              obj[cleanKey].push(value);
          } else {
              obj[key] = value;
          }
      });

      // Extract modelName and id from the current path /admin/edit/{modelName}/{id}
      var pathParts = window.location.pathname.split('/');
      var modelName = pathParts[3];
      var recordId = pathParts[4];

      var endpoint = '/api/v1/admin/models/' + modelName;
      var method = 'POST';

      if (recordId && recordId !== 'new') {
          endpoint += '/' + recordId;
          method = 'PATCH';
      }

      // Disable buttons to prevent duplicate submission
      var submitButtons = editForm.querySelectorAll('button[type="submit"]');
      submitButtons.forEach(function(btn) { btn.disabled = true; });

      // Create or select success toast at bottom-left of viewport
      var toast = document.getElementById('ajax-save-toast');
      if (!toast) {
        toast = document.createElement('div');
        toast.id = 'ajax-save-toast';
        toast.className = 'toast-notification';
        document.body.appendChild(toast);
      }

      var csrfToken = obj['csrf'] || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

      fetch(endpoint, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(obj),
        credentials: 'same-origin'
      })
      .then(function(res) {
        submitButtons.forEach(function(btn) { btn.disabled = false; });
        if (res.ok) {
            return res.json();
        }
        throw new Error('Save failed');
      })
      .then(function(data) {
          if (data.success) {
              if (submitAction === 'save_return') {
                  window.location.href = '/admin/list/' + modelName + '?saved=1';
              } else {
                  // Save & Continue
                  if (recordId === 'new') {
                      // Redirect to the newly created record edit path
                      window.location.href = data.redirect || ('/admin/edit/' + modelName + '/' + data.id);
                  } else {
                      // Show springy toast
                      toast.textContent = 'Changes saved successfully!';
                      toast.className = 'toast-notification success';
                      
                      // Force reflow
                      toast.offsetHeight;

                      toast.classList.add('show');
                      
                      setTimeout(function() {
                        toast.classList.remove('show');
                      }, 3000);
                  }
              }
          } else {
              window.adminAlert('Save Failed', data.error || 'Unknown error');
          }
      })
      .catch(function(err) {
        submitButtons.forEach(function(btn) { btn.disabled = false; });
        window.adminAlert('Save Error', err.message);
      });
    });
  }

  // Intercept Ctrl+S / Cmd+S on record edit forms for Save & Continue
  window.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      var editForm = document.querySelector('.editrecord form');
      if (editForm) {
        e.preventDefault();
        
        // Find the "Save & Continue" button (which has value="save_continue")
        var saveContinueBtn = editForm.querySelector('button[value="save_continue"]');
        if (saveContinueBtn) {
          saveContinueBtn.click();
        } else {
          // Fallback: If no explicit button found, dispatch submit event
          editForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
        }
      }
    }
  });

  // Global Entire Sidebar Toggle (Desktop Collapse / Mobile Slide-in)
  var sidebarToggle = document.getElementById('sidebar-toggle');
  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function(e) {
      e.preventDefault();
      if (window.innerWidth < 768) {
        document.body.classList.toggle('sidebar-mobile-open');
        document.body.classList.remove('sidebar-collapsed');
      } else {
        document.body.classList.toggle('sidebar-collapsed');
        document.body.classList.remove('sidebar-mobile-open');
        // Persist desktop collapsed preference
        var isCollapsed = document.body.classList.contains('sidebar-collapsed');
        localStorage.setItem('admin_sidebar_collapsed', isCollapsed ? 'true' : 'false');
      }
    });
  }

  // Dismiss mobile slide-in menu when user clicks outside (backdrop click)
  var sidebarBackdrop = document.getElementById('sidebar-backdrop');
  if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener('click', function(e) {
      e.preventDefault();
      document.body.classList.remove('sidebar-mobile-open');
    });
  }

  // Load and apply persisted sidebar collapsed preference on load
  if (window.innerWidth >= 768) {
    var persistedSidebar = localStorage.getItem('admin_sidebar_collapsed');
    if (persistedSidebar === 'true') {
      document.body.classList.add('sidebar-collapsed');
    }
  }

  // Left Sidebar Collapsible Sections Toggle Handler
  document.querySelectorAll('.sidebar-section-toggle').forEach(function(toggle) {
    toggle.addEventListener('click', function(e) {
      e.preventDefault();
      var section = toggle.closest('.sidebar-section');
      if (section) {
        section.classList.toggle('collapsed');
        
        // Persist collapsed state in localStorage
        var titleEl = section.querySelector('.sidebar-section-title');
        if (titleEl) {
          var title = titleEl.textContent.trim();
          var isCollapsed = section.classList.contains('collapsed');
          localStorage.setItem('sidebar_collapsed_' + title.replace(/\s+/g, '_'), isCollapsed);
        }
      }
    });
  });

  // Load persisted collapsible sidebar states on page load
  document.querySelectorAll('.sidebar-section').forEach(function(section) {
    var titleEl = section.querySelector('.sidebar-section-title');
    if (titleEl) {
      var title = titleEl.textContent.trim();
      var persisted = localStorage.getItem('sidebar_collapsed_' + title.replace(/\s+/g, '_'));
      if (persisted === 'true') {
        section.classList.add('collapsed');
      } else if (persisted === 'false') {
        section.classList.remove('collapsed');
      }
    }
    
    // Now that state has been restored without transition, enable transition for user clicks!
    var submenu = section.querySelector('.sidebar-submenu');
    if (submenu) {
      // Force reflow to paint the restored state instantly
      submenu.offsetHeight;
      submenu.classList.remove('no-transition');
      }
      });
      });

      // Global variables for shared WYSIWYG Table components
      var activeTableEditor = null;
      var activeTableButton = null;
      var activeSelectedCell = null;

      // Initialize shared Table Insertion Dropdown
      var tableDropdown = document.getElementById('wysiwyg-table-dropdown');
      if (!tableDropdown) {
      tableDropdown = document.createElement('div');
      tableDropdown.id = 'wysiwyg-table-dropdown';
      tableDropdown.style.cssText = 'display: none; position: absolute; background: white; border: 1px solid #cbd5e1; border-radius: 6px; padding: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); z-index: 10000; width: 180px;';
      tableDropdown.innerHTML = `
      <div style="margin-bottom: 10px;">
      <label style="font-size: 0.75rem; font-weight: bold; display: block; margin-bottom: 4px; color: #475569;">Rows</label>
      <input type="number" class="wysiwyg-table-rows" value="3" min="1" max="10" style="width: 100%; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.82rem; box-sizing: border-box;">
      </div>
      <div style="margin-bottom: 12px;">
      <label style="font-size: 0.75rem; font-weight: bold; display: block; margin-bottom: 4px; color: #475569;">Columns</label>
      <input type="number" class="wysiwyg-table-cols" value="3" min="1" max="10" style="width: 100%; padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.82rem; box-sizing: border-box;">
      </div>
      <button type="button" class="btn-confirm-insert-table" style="width: 100%; padding: 8px; font-size: 0.8rem; font-weight: bold; background-color: var(--accent-color, #2563eb); color: white; border: none; border-radius: 4px; cursor: pointer; transition: background-color 0.15s ease;">Insert Table</button>
      `;
      document.body.appendChild(tableDropdown);

      // Handle actual Insertion confirm click
      tableDropdown.querySelector('.btn-confirm-insert-table').addEventListener('click', function() {
      if (!activeTableEditor) return;

      var rows = parseInt(tableDropdown.querySelector('.wysiwyg-table-rows').value) || 3;
      var cols = parseInt(tableDropdown.querySelector('.wysiwyg-table-cols').value) || 3;

      if (rows > 0 && cols > 0) {
      if (activeTableEditor._savedRange) {
      var sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(activeTableEditor._savedRange);
      }

      var table = document.createElement('table');
      table.style.width = '100%';
      table.style.borderCollapse = 'collapse';
      table.style.margin = '20px 0';

      // Build table head
      var thead = document.createElement('thead');
      var headRow = document.createElement('tr');
      for (var j = 0; j < cols; j++) {
      var th = document.createElement('th');
      th.textContent = 'Header ' + (j + 1);
      th.style.border = '1px solid #cbd5e1';
      th.style.padding = '10px';
      th.style.backgroundColor = '#f8fafc';
      th.style.textAlign = 'left';
      th.style.fontWeight = 'bold';
      headRow.appendChild(th);
      }
      thead.appendChild(headRow);
      table.appendChild(thead);

      // Build table body
      var tbody = document.createElement('tbody');
      var bodyRows = rows > 1 ? rows - 1 : 0;
      for (var i = 0; i < bodyRows; i++) {
      var tr = document.createElement('tr');
      for (var j = 0; j < cols; j++) {
        var td = document.createElement('td');
        td.textContent = 'Data';
        td.style.border = '1px solid #cbd5e1';
        td.style.padding = '10px';
        tr.appendChild(td);
      }
      tbody.appendChild(tr);
      }
      table.appendChild(tbody);

      insertNodeAtCaret(activeTableEditor, table);

      // Focus and trigger preview updates
      activeTableEditor.focus();
      var inputEvent = new Event('input', { bubbles: true });
      activeTableEditor.dispatchEvent(inputEvent);
      }

      tableDropdown.style.display = 'none';
      activeTableEditor = null;
      activeTableButton = null;
      });
      }

      // Initialize shared Context Menu
      var tableContextMenu = document.getElementById('wysiwyg-table-context-menu');
      if (!tableContextMenu) {
      tableContextMenu = document.createElement('div');
      tableContextMenu.id = 'wysiwyg-table-context-menu';
      tableContextMenu.style.cssText = 'display: none; position: absolute; background: #1e293b; border-radius: 6px; padding: 4px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); z-index: 10000; gap: 4px; align-items: center; border: 1px solid #334155;';
      tableContextMenu.innerHTML = `
      <button type="button" class="btn-table-action" data-action="add-row" title="Add Row Below" style="background: none; border: none; color: #e2e8f0; font-size: 0.72rem; padding: 6px 10px; cursor: pointer; font-weight: bold; border-radius: 4px; transition: background 0.15s ease;">+ Row</button>
      <button type="button" class="btn-table-action" data-action="delete-row" title="Delete Current Row" style="background: none; border: none; color: #f87171; font-size: 0.72rem; padding: 6px 10px; cursor: pointer; font-weight: bold; border-radius: 4px; transition: background 0.15s ease;">- Row</button>
      <button type="button" class="btn-table-action" data-action="add-col" title="Add Column Right" style="background: none; border: none; color: #e2e8f0; font-size: 0.72rem; padding: 6px 10px; cursor: pointer; font-weight: bold; border-radius: 4px; transition: background 0.15s ease;">+ Col</button>
      <button type="button" class="btn-table-action" data-action="delete-col" title="Delete Current Column" style="background: none; border: none; color: #f87171; font-size: 0.72rem; padding: 6px 10px; cursor: pointer; font-weight: bold; border-radius: 4px; transition: background 0.15s ease;">- Col</button>
      <button type="button" class="btn-table-action" data-action="toggle-header" title="Toggle Header / Data Cell" style="background: none; border: none; color: #38bdf8; font-size: 0.72rem; padding: 6px 10px; cursor: pointer; font-weight: bold; border-radius: 4px; transition: background 0.15s ease;">Header</button>
      `;
      document.body.appendChild(tableContextMenu);

      // Style menu buttons hover inline
      tableContextMenu.querySelectorAll('button').forEach(function(btn) {
      btn.addEventListener('mouseover', function() { btn.style.background = '#334155'; });
      btn.addEventListener('mouseout', function() { btn.style.background = 'none'; });
      });

      // Handle all Context Menu click actions
      tableContextMenu.addEventListener('click', function(e) {
      var actionBtn = e.target.closest('[data-action]');
      if (!actionBtn || !activeSelectedCell) return;

      var action = actionBtn.getAttribute('data-action');
      var cell = activeSelectedCell;
      var row = cell.parentNode;
      var table = row.closest('table');
      var editor = table.closest('.editor-area[contenteditable="true"]');

      if (action === 'add-row') {
      var colCount = row.children.length;
      var newRow = document.createElement('tr');
      for (var i = 0; i < colCount; i++) {
      var cellType = row.children[i].tagName.toLowerCase();
      var newCell = document.createElement(cellType);
      newCell.textContent = cellType === 'th' ? 'Header' : 'Data';
      newCell.style.border = '1px solid #cbd5e1';
      newCell.style.padding = '10px';
      if (cellType === 'th') {
        newCell.style.backgroundColor = '#f8fafc';
        newCell.style.textAlign = 'left';
        newCell.style.fontWeight = 'bold';
      }
      newRow.appendChild(newCell);
      }
      row.parentNode.insertBefore(newRow, row.nextSibling);
      } else if (action === 'delete-row') {
      var parent = row.parentNode;
      if (parent.children.length > 1) {
      row.remove();
      } else {
      table.remove();
      }
      tableContextMenu.style.display = 'none';
      activeSelectedCell = null;
      } else if (action === 'add-col') {
      var cellIndex = Array.from(row.children).indexOf(cell);
      var allRows = table.querySelectorAll('tr');
      allRows.forEach(function(r) {
      var existingCell = r.children[cellIndex];
      var cellType = existingCell ? existingCell.tagName.toLowerCase() : 'td';
      var newCell = document.createElement(cellType);
      newCell.textContent = cellType === 'th' ? 'Header' : 'Data';
      newCell.style.border = '1px solid #cbd5e1';
      newCell.style.padding = '10px';
      if (cellType === 'th') {
        newCell.style.backgroundColor = '#f8fafc';
        newCell.style.textAlign = 'left';
        newCell.style.fontWeight = 'bold';
      }
      if (existingCell) {
        r.insertBefore(newCell, existingCell.nextSibling);
      } else {
        r.appendChild(newCell);
      }
      });
      } else if (action === 'delete-col') {
      var cellIndex = Array.from(row.children).indexOf(cell);
      var allRows = table.querySelectorAll('tr');
      var colCount = row.children.length;
      if (colCount > 1) {
      allRows.forEach(function(r) {
        if (r.children[cellIndex]) {
          r.children[cellIndex].remove();
        }
      });
      } else {
      table.remove();
      }
      tableContextMenu.style.display = 'none';
      activeSelectedCell = null;
      } else if (action === 'toggle-header') {
      var cellType = cell.tagName.toLowerCase();
      var newType = cellType === 'th' ? 'td' : 'th';
      var newCell = document.createElement(newType);
      newCell.innerHTML = cell.innerHTML;
      newCell.style.border = '1px solid #cbd5e1';
      newCell.style.padding = '10px';
      if (newType === 'th') {
      newCell.style.backgroundColor = '#f8fafc';
      newCell.style.textAlign = 'left';
      newCell.style.fontWeight = 'bold';
      } else {
      newCell.style.backgroundColor = '';
      newCell.style.textAlign = '';
      newCell.style.fontWeight = '';
      }
      cell.parentNode.replaceChild(newCell, cell);
      activeSelectedCell = newCell;

      // Reposition context menu below newly transformed cell
      var cellRect = newCell.getBoundingClientRect();
      tableContextMenu.style.left = (cellRect.left + window.scrollX) + 'px';
      tableContextMenu.style.top = (cellRect.bottom + window.scrollY + 6) + 'px';
      }

      // Trigger preview and hidden serialize field syncs
      if (editor) {
      var inputEvent = new Event('input', { bubbles: true });
      editor.dispatchEvent(inputEvent);
      }
      });
      }

      // Global click/keyup interceptor to float context menu and close dropdowns
      document.addEventListener('click', function(e) {
      // Toggle context menu on clicking td/th cells
      var cell = e.target.closest('td, th');
      var editor = e.target.closest('.editor-area[contenteditable="true"]');
      if (cell && editor) {
      activeSelectedCell = cell;
      var cellRect = cell.getBoundingClientRect();
      tableContextMenu.style.left = (cellRect.left + window.scrollX) + 'px';
      tableContextMenu.style.top = (cellRect.bottom + window.scrollY + 6) + 'px';
      tableContextMenu.style.display = 'flex';
      } else if (!e.target.closest('#wysiwyg-table-context-menu')) {
      tableContextMenu.style.display = 'none';
      activeSelectedCell = null;
      }

      // Dismiss table dropdown on clicking outside
      if (tableDropdown.style.display === 'block' && !e.target.closest('#wysiwyg-table-dropdown') && !e.target.closest('[data-cmd="insertTable"]')) {
      tableDropdown.style.display = 'none';
      activeTableEditor = null;
      activeTableButton = null;
      }
      });

  // Global Dynamic Integrated Confirmation Modal Promise Handler
  window.adminConfirm = function(options) {
      return new Promise(function(resolve) {
          var overlay = document.getElementById('admin-confirm-modal');
          if (!overlay) {
              // Fallback to native confirm if modal markup is missing
              resolve(confirm(options.message || 'Are you sure?'));
              return;
          }

          var card = overlay.querySelector('.admin-modal-card');
          var titleEl = document.getElementById('admin-modal-title');
          var msgEl = document.getElementById('admin-modal-message');
          var detailsEl = document.getElementById('admin-modal-details');
          var noteEl = document.getElementById('admin-modal-note');
          var confirmBtn = document.getElementById('admin-modal-btn-confirm');
          var cancelBtn = document.getElementById('admin-modal-btn-cancel');
          var optionsEl = document.getElementById('admin-modal-options');

          // Populate contents
          titleEl.textContent = options.title || 'Confirm Action';
          msgEl.textContent = options.message || 'Are you sure you want to proceed?';

          if (options.details) {
              detailsEl.textContent = options.details;
              detailsEl.style.display = 'block';
          } else {
              detailsEl.style.display = 'none';
          }

          if (options.note) {
              noteEl.textContent = options.note;
              noteEl.style.display = 'block';
          } else {
              noteEl.style.display = 'none';
          }

          if (optionsEl) {
              if (options.optionsHtml) {
                  optionsEl.innerHTML = options.optionsHtml;
                  optionsEl.classList.add('active');
              } else {
                  optionsEl.innerHTML = '';
                  optionsEl.classList.remove('active');
              }
          }

          confirmBtn.textContent = options.confirmText || 'Confirm';
          confirmBtn.className = options.confirmClass || 'btn-confirm';

          // Open transitions
          overlay.style.display = 'flex';
          overlay.offsetHeight; // Reflow
          overlay.classList.add('active');
          if (card) card.classList.add('active');

          function close(result) {
              overlay.classList.remove('active');
              if (card) card.classList.remove('active');
              setTimeout(function() {
                  if (optionsEl) {
                      optionsEl.innerHTML = '';
                      optionsEl.classList.remove('active');
                  }
                  overlay.style.display = 'none';
                  resolve(result);
              }, 200);
          }

          confirmBtn.onclick = function() { close(true); };
          cancelBtn.onclick = function() { close(false); };
          overlay.onclick = function(e) {
              if (e.target === overlay) {
                  close(false);
              }
          };
      });
  };

  // Intercept standard submit actions for back-office list delete forms
  document.addEventListener('submit', function(e) {
      if (e.target.classList.contains('admin-delete-form')) {
          var form = e.target;
          if (form.dataset.confirmed === 'true') {
              form.dataset.confirmed = '';
              return; // Proceed with submission
          }

          e.preventDefault();

          var details = form.getAttribute('data-cascade-details') || '';
          var note = form.getAttribute('data-cascade-note') || '';

          if (details) {
              // Details already loaded via background lazy-fetch! Show modal instantly.
              window.adminConfirm({
                  title: 'Delete Confirmation',
                  message: 'Are you sure you want to delete this record?',
                  details: details,
                  note: note,
                  confirmText: 'Delete',
                  confirmClass: 'btn-confirm'
              }).then(function(confirmed) {
                  if (confirmed) {
                      form.dataset.confirmed = 'true';
                      form.submit();
                  }
              });
          } else {
              // Fallback on-demand fetch if they clicked too quickly before background-fetch finished
              var recordId = form.getAttribute('data-id');
              var modelName = form.getAttribute('data-model');
              if (!recordId || !modelName) {
                  window.adminConfirm({
                      title: 'Delete Confirmation',
                      message: 'Are you sure you want to delete this record?',
                      confirmText: 'Delete',
                      confirmClass: 'btn-confirm'
                  }).then(function(confirmed) {
                      if (confirmed) {
                          form.dataset.confirmed = 'true';
                          form.submit();
                      }
                  });
                  return;
              }

              var submitBtn = form.querySelector('button[type="submit"]');
              var originalText = submitBtn.innerHTML;
              submitBtn.disabled = true;
              submitBtn.innerHTML = '...';

              fetch('/api/v1/admin/models/' + modelName + '/' + recordId + '/cascade-check', {
                  method: 'GET',
                  headers: { 'Accept': 'application/json' }
              })
              .then(function(res) { return res.json(); })
              .then(function(data) {
                  submitBtn.disabled = false;
                  submitBtn.innerHTML = originalText;

                  var details = data.details || '';
                  var note = details ? 'Note: Restoring this record later will NOT automatically restore these related cascading records.' : '';

                  window.adminConfirm({
                      title: 'Delete Confirmation',
                      message: 'Are you sure you want to delete this record?',
                      details: details,
                      note: note,
                      confirmText: 'Delete',
                      confirmClass: 'btn-confirm'
                  }).then(function(confirmed) {
                      if (confirmed) {
                          form.dataset.confirmed = 'true';
                          form.submit();
                      }
                  });
              })
              .catch(function() {
                  submitBtn.disabled = false;
                  submitBtn.innerHTML = originalText;
                  window.adminConfirm({
                      title: 'Delete Confirmation',
                      message: 'Are you sure you want to delete this record?',
                      confirmText: 'Delete',
                      confirmClass: 'btn-confirm'
                  }).then(function(confirmed) {
                      if (confirmed) {
                          form.dataset.confirmed = 'true';
                          form.submit();
                      }
                  });
              });
          }
      }
  });

  // Intercept listing links click events (restore and force delete)
  document.addEventListener('click', function(e) {
      // Force Delete
      if (e.target.classList.contains('btn-force-delete')) {
          var link = e.target;
          if (link.dataset.confirmed === 'true') {
              link.dataset.confirmed = '';
              return;
          }

          e.preventDefault();

          var details = link.getAttribute('data-cascade-details') || '';
          var message = 'Are you sure you want to permanently delete this record? This action is completely irreversible';
          if (details) {
              message += ' and will permanently wipe the following associated child records:';
          } else {
              message += ' and will permanently wipe all associated records.';
          }

          window.adminConfirm({
              title: 'Permanent Deletion',
              message: message,
              details: details,
              confirmText: 'Delete Permanently',
              confirmClass: 'btn-confirm'
          }).then(function(confirmed) {
              if (confirmed) {
                  link.dataset.confirmed = 'true';
                  link.click();
              }
          });
      }

      // Restore
      if (e.target.classList.contains('btn-restore')) {
          var link = e.target;
          if (link.dataset.confirmed === 'true') {
              link.dataset.confirmed = '';
              return;
          }

          e.preventDefault();

          window.adminConfirm({
              title: 'Restore Record',
              message: 'Are you sure you want to restore this record?',
              note: 'Note: Restoring this record will NOT automatically restore its cascade-deleted related child records.',
              confirmText: 'Restore',
              confirmClass: 'btn-confirm-primary'
          }).then(function(confirmed) {
              if (confirmed) {
                  link.dataset.confirmed = 'true';
                  link.click();
              }
          });
      }
  });

  // Lazily background-fetch cascade details for listed records to populate tooltips and prevent on-click network delays!
  window.addEventListener('load', function() {
      // 1. Soft Delete Forms background fetch
      document.querySelectorAll('.admin-delete-form').forEach(function(form) {
          var recordId = form.getAttribute('data-id');
          var modelName = form.getAttribute('data-model');
          if (!recordId || !modelName) return;

          fetch('/api/v1/admin/models/' + modelName + '/' + recordId + '/cascade-check', {
              method: 'GET',
              headers: { 'Accept': 'application/json' }
          })
          .then(function(res) { return res.json(); })
          .then(function(data) {
              if (data && data.success && data.details) {
                  form.setAttribute('data-cascade-details', data.details);
                  form.setAttribute('data-cascade-note', 'Note: Restoring this record later will NOT automatically restore these related cascading records.');
                  
                  var deleteBtn = form.querySelector('.btn-delete-link');
                  if (deleteBtn && data.has_cascade) {
                      deleteBtn.title = 'Warning: This record contains cascading child items!';
                  }
              }
          })
          .catch(function() { /* Ignore background errors */ });
      });

      // 2. Permanent Force Delete Links background fetch
      document.querySelectorAll('.btn-force-delete').forEach(function(link) {
          var recordId = link.getAttribute('data-id');
          var modelName = link.getAttribute('data-model');
          if (!recordId || !modelName) return;

          fetch('/api/v1/admin/models/' + modelName + '/' + recordId + '/cascade-check', {
              method: 'GET',
              headers: { 'Accept': 'application/json' }
          })
          .then(function(res) { return res.json(); })
          .then(function(data) {
              if (data && data.success && data.details) {
                  link.setAttribute('data-cascade-details', data.details);
              }
          })
          .catch(function() { /* Ignore background errors */ });
      });
  });

  // Global Dynamic Integrated Alert Modal Promise Handler
  window.adminAlert = function(title, message) {
      return window.adminConfirm({
          title: title || 'Notification',
          message: message,
          confirmText: 'OK',
          confirmClass: 'btn-confirm-primary'
      });
  };

  // Search Index Reindex Widget Coordinator (Multi-batch timeout-proof indexing)
  (function() {
      window.addEventListener('DOMContentLoaded', function() {
          var btn = document.getElementById('btn-trigger-reindex');
          if (!btn) return;

          btn.addEventListener('click', function() {
              var btnLabel = document.getElementById('btn-reindex-label');
              var progressContainer = document.getElementById('reindex-progress-container');
              var statusText = document.getElementById('reindex-progress-status');
              var percentText = document.getElementById('reindex-progress-percent');
              var fillBar = document.getElementById('reindex-progress-fill');
              var widgetCount = document.getElementById('search-widget-count');

              btn.disabled = true;
              progressContainer.classList.add('active');
              btnLabel.textContent = 'Initializing...';
              statusText.textContent = 'Clearing index...';
              percentText.textContent = '0%';
              fillBar.style.width = '0%';

              // 1. Trigger Reindex Start (clears index, retrieves all searchable record IDs)
              fetch('/api/v1/admin/search/reindex/start', {
                  method: 'POST',
                  headers: {
                      'Content-Type': 'application/json',
                      'X-CSRF-Token': window.ADMIN_CSRF_TOKEN || ''
                  }
              })
              .then(function(res) { return res.json(); })
              .then(function(data) {
                  if (!data || !data.success) {
                      throw new Error(data.error || 'Failed to initialize re-indexing.');
                  }

                  var total = data.total || 0;
                  var batches = data.batches || [];

                  if (total === 0) {
                      fillBar.style.width = '100%';
                      percentText.textContent = '100%';
                      statusText.textContent = 'Completed (0 items)';
                      widgetCount.textContent = '0';
                      btn.disabled = false;
                      btnLabel.textContent = 'Run Full Re-index';
                      window.adminAlert('Search Index', 'The search index was cleared, but there were no searchable records to index.');
                      return;
                  }

                  // 2. Compile flat chunks of work to execute sequentially
                  var tasks = [];
                  var chunkSize = 15;

                  batches.forEach(function(batch) {
                      var model = batch.model;
                      var ids = batch.ids || [];
                      for (var i = 0; i < ids.length; i += chunkSize) {
                          tasks.push({
                              model: model,
                              ids: ids.slice(i, i + chunkSize)
                          });
                      }
                  });

                  var currentTaskIndex = 0;
                  var indexedCount = 0;

                  function runNextChunk() {
                      if (currentTaskIndex >= tasks.length) {
                          // All batches complete!
                          fillBar.style.width = '100%';
                          percentText.textContent = '100%';
                          statusText.textContent = 'Successfully indexed ' + total + ' items!';
                          widgetCount.textContent = total;
                          btn.disabled = false;
                          btnLabel.textContent = 'Run Full Re-index';
                          window.adminAlert('Search Index Complete', 'Successfully re-indexed ' + total + ' items across all multi-tenant domains!');
                          
                          // Hide progress bar with delay
                          setTimeout(function() {
                              progressContainer.classList.remove('active');
                          }, 3000);
                          return;
                      }

                      var task = tasks[currentTaskIndex];
                      statusText.textContent = 'Indexing ' + indexedCount + ' / ' + total + '...';
                      btnLabel.textContent = 'Indexing... ' + Math.round((indexedCount / total) * 100) + '%';

                      fetch('/api/v1/admin/search/reindex/batch', {
                          method: 'POST',
                          headers: {
                              'Content-Type': 'application/json',
                              'X-CSRF-Token': window.ADMIN_CSRF_TOKEN || ''
                          },
                          body: JSON.stringify(task)
                      })
                      .then(function(res) { return res.json(); })
                      .then(function(resData) {
                          if (resData && resData.success) {
                              indexedCount += resData.indexed || 0;
                              var progressPercent = Math.round((indexedCount / total) * 100);
                              fillBar.style.width = progressPercent + '%';
                              percentText.textContent = progressPercent + '%';
                              
                              currentTaskIndex++;
                              runNextChunk();
                          } else {
                              throw new Error(resData.error || 'Failed to process batch.');
                          }
                      })
                      .catch(function(err) {
                          console.error(err);
                          btn.disabled = false;
                          btnLabel.textContent = 'Run Full Re-index';
                          statusText.textContent = 'Error occurred.';
                          window.adminAlert('Search Index Error', 'An error occurred during re-indexing: ' + err.message);
                      });
                  }

                  // Start the recursive queue!
                  runNextChunk();
              })
              .catch(function(err) {
                  console.error(err);
                  btn.disabled = false;
                  btnLabel.textContent = 'Run Full Re-index';
                  window.adminAlert('Search Index Error', 'Failed to start re-indexing: ' + err.message);
              });
          });
      });
  })();

