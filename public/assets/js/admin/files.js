document.addEventListener('DOMContentLoaded', function() {
    // -------------------------------------------------------------------------
    // CSP-COMPLIANT EVENT LISTENERS (No Inline Events)
    // -------------------------------------------------------------------------
    // 1. Parent Folder "Go Up" click handler
    var goUpCard = document.querySelector('.go-up-card');
    if (goUpCard) {
        goUpCard.addEventListener('click', function() {
            var parentFolder = goUpCard.getAttribute('data-folder');
            if (parentFolder !== null) {
                window.location.href = '?folder=' + encodeURIComponent(parentFolder);
            }
        });
    }

    // 2. Edit Page: Copy URL click handler
    var copyBtn = document.getElementById('copy-link-btn');
    var publicUrlInput = document.getElementById('public-url-input');
    var copySuccessNotice = document.getElementById('copy-success-notice');
    if (copyBtn && publicUrlInput) {
        copyBtn.addEventListener('click', function() {
            navigator.clipboard.writeText(publicUrlInput.value).then(function() {
                if (copySuccessNotice) {
                    copySuccessNotice.style.display = 'inline-block';
                    setTimeout(function() {
                        copySuccessNotice.style.display = 'none';
                    }, 2500);
                }
            });
        });
    }

    // 3. Edit Page: Re-upload File selection notice change handler
    var reuploadInput = document.getElementById('reupload-file-input');
    var selectedFileNotice = document.getElementById('selected-file-notice');
    if (reuploadInput && selectedFileNotice) {
        reuploadInput.addEventListener('change', function() {
            if (reuploadInput.files && reuploadInput.files.length) {
                selectedFileNotice.textContent = 'Selected file: ' + reuploadInput.files[0].name;
                selectedFileNotice.style.display = 'block';
            } else {
                selectedFileNotice.textContent = '';
                selectedFileNotice.style.display = 'none';
            }
        });
    }
    // -------------------------------------------------------------------------

    var searchInput = document.getElementById('files-search-input');
    var filesGrid = document.getElementById('files-grid');
    var noFilteredMessage = document.getElementById('no-filtered-files-message');

    // Real-time search filter logic
    if (searchInput && filesGrid) {
        searchInput.addEventListener('input', function() {
            var query = searchInput.value.toLowerCase().trim();
            var visibleCount = 0;
            var cards = filesGrid.querySelectorAll('.file-card');

            cards.forEach(function(card) {
                var filename = card.getAttribute('data-filename') || '';
                if (filename.indexOf(query) !== -1) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (visibleCount === 0 && cards.length > 0) {
                if (noFilteredMessage) noFilteredMessage.style.display = 'block';
            } else {
                if (noFilteredMessage) noFilteredMessage.style.display = 'none';
            }
        });
    }

    // Folder creation interaction
    var createFolderBtn = document.getElementById('create-folder-btn');
    var createFolderForm = document.getElementById('create-folder-form');
    var folderNameInput = document.getElementById('folder-name-input');
    if (createFolderBtn && createFolderForm && folderNameInput) {
        createFolderBtn.addEventListener('click', function() {
            var folderName = prompt('Enter a name for the new folder:');
            if (folderName) {
                folderNameInput.value = folderName;
                createFolderForm.submit();
            }
        });
    }

    // Modern file upload area interactions
    var zone = document.getElementById('media-drag-drop-zone');
    var fileInput = document.getElementById('media-file-input');
    var uploadActions = document.getElementById('media-upload-actions');
    var selectedFileName = document.getElementById('selected-file-name');
    var cancelBtn = document.getElementById('cancel-upload-btn');

    if (zone && fileInput) {
        zone.addEventListener('click', function() {
            fileInput.click();
        });

        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            zone.style.backgroundColor = 'color-mix(in srgb, var(--bg-color-inverse) 6%, var(--bg-color) 94%)';
            zone.style.borderColor = 'var(--text-color)';
        });

        zone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            zone.style.backgroundColor = '';
            zone.style.borderColor = '';
        });

        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.style.backgroundColor = '';
            zone.style.borderColor = '';
            if (e.dataTransfer.files && e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                updateSelectedFile();
            }
        });

        fileInput.addEventListener('change', updateSelectedFile);

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                fileInput.value = '';
                updateSelectedFile();
            });
        }
    }

    function updateSelectedFile() {
        if (fileInput.files && fileInput.files.length) {
            selectedFileName.textContent = 'Selected: ' + fileInput.files[0].name;
            uploadActions.style.display = 'flex';
            zone.style.opacity = '0.5';
        } else {
            selectedFileName.textContent = '';
            uploadActions.style.display = 'none';
            zone.style.opacity = '1';
        }
    }

    // Copy path to clipboard logic (Event Delegation)
    document.addEventListener('click', function(e) {
        // Prevent path copy when in selection mode
        if (isSelectionMode) return;

        var btn = e.target.closest('.action-copy-url');
        if (btn) {
            e.preventDefault();
            var path = btn.getAttribute('data-url');
            if (!path) return;

            var copyText = window.location.origin + path;
            
            navigator.clipboard.writeText(copyText).then(function() {
                var originalHTML = btn.innerHTML;
                btn.innerHTML = 'Copied!';
                btn.style.color = '#2ecc71';
                setTimeout(function() {
                    btn.innerHTML = originalHTML;
                    btn.style.color = '';
                }, 2000);
            }).catch(function() {
                // Fallback for older browsers
                var tempInput = document.createElement('input');
                tempInput.value = copyText;
                document.body.appendChild(tempInput);
                tempInput.select();
                try {
                    document.execCommand('copy');
                    var originalHTML = btn.innerHTML;
                    btn.innerHTML = 'Copied!';
                    btn.style.color = '#2ecc71';
                    setTimeout(function() {
                        btn.innerHTML = originalHTML;
                        btn.style.color = '';
                    }, 2000);
                } catch (err) {
                    alert('Could not copy path to clipboard.');
                }
                document.body.removeChild(tempInput);
            });
        }
    });

    // Delegate delete form submission using RESTful Admin API (Event Delegation)
    if (filesGrid) {
        filesGrid.addEventListener('submit', function(e) {
            var form = e.target.closest('form.ajax-delete');
            if (form) {
                e.preventDefault();
                
                window.adminConfirm({
                    title: 'Delete File',
                    message: 'Are you sure you want to delete this media file?',
                    note: 'Note: Force deleting this media file will permanently erase it from disk storage.'
                }).then(function(confirmed) {
                    if (confirmed) {
                        var id = form.querySelector('input[name="id"]').value;
                        var csrfInput = document.querySelector('input[name="csrf"]');
                        var csrfToken = csrfInput ? csrfInput.value : '';

                        fetch('/api/v1/admin/files', {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': csrfToken
                            },
                            body: JSON.stringify({ id: id, csrf: csrfToken }),
                            credentials: 'same-origin'
                        })
                .then(function(res){ if (res.ok) return res.json(); throw new Error('Delete failed'); })
                .then(function(data){
                    if (data.success) {
                        var row = document.getElementById('file-'+id);
                        if (row) {
                            row.parentNode.removeChild(row);
                            var remainingFiles = document.querySelectorAll('.file-item-card');
                            var foldersCount = document.querySelectorAll('.folder-card').length;
                            if (remainingFiles.length === 0 && foldersCount === 0) {
                                window.location.reload();
                            }
                        }
                    } else {
                        throw new Error(data.error || 'Delete failed');
                    }
                }).catch(function(err){ alert(err.message); });
            }
        });
    }
    });
}

    // Drag & Drop Moving files inside Media Library using RESTful Admin API (Event Delegation with Batch Drag support)
    if (filesGrid) {
        filesGrid.addEventListener('dragstart', function(e) {
            var item = e.target.closest('.file-item-card');
            if (item) {
                // If dragging a selected item, drag the entire selection!
                if (item.classList.contains('selected')) {
                    var selected = document.querySelectorAll('.file-card.selected');
                    var ids = Array.from(selected).map(function(card) {
                        return card.id;
                    });
                    e.dataTransfer.setData('application/zero-file-ids', JSON.stringify(ids));
                    selected.forEach(function(card) {
                        card.style.opacity = '0.4';
                    });
                } else {
                    // Single item drag
                    e.dataTransfer.setData('application/zero-file-id', item.id);
                    item.style.opacity = '0.4';
                }
            }
        });

        filesGrid.addEventListener('dragend', function(e) {
            var item = e.target.closest('.file-item-card');
            if (item) {
                var selected = document.querySelectorAll('.file-card.selected');
                selected.forEach(function(card) {
                    card.style.opacity = '1';
                });
                item.style.opacity = '1';
                
                var folderItems = document.querySelectorAll('.folder-card');
                folderItems.forEach(function(folder) {
                    folder.style.outline = '';
                    folder.style.backgroundColor = '';
                });
            }
        });

        filesGrid.addEventListener('dragover', function(e) {
            var folder = e.target.closest('.folder-card');
            if (folder) {
                e.preventDefault(); // Required to allow drop!
                folder.style.outline = '2px dashed var(--text-color)';
                folder.style.backgroundColor = 'color-mix(in srgb, var(--bg-color-inverse) 6%, var(--bg-color) 94%)';
            }
        });

        filesGrid.addEventListener('dragleave', function(e) {
            var folder = e.target.closest('.folder-card');
            if (folder) {
                folder.style.outline = '';
                folder.style.backgroundColor = '';
            }
        });

        filesGrid.addEventListener('drop', function(e) {
            var folder = e.target.closest('.folder-card');
            if (folder) {
                e.preventDefault();
                folder.style.outline = '';
                folder.style.backgroundColor = '';

                var destinationFolderId = folder.getAttribute('data-fid') || 'parent';
                var fileCardIds = [];

                // Check for batch selection drag first
                var batchData = e.dataTransfer.getData('application/zero-file-ids');
                if (batchData) {
                    fileCardIds = JSON.parse(batchData);
                } else {
                    var singleId = e.dataTransfer.getData('application/zero-file-id');
                    if (singleId) {
                        fileCardIds = [singleId];
                    }
                }

                if (fileCardIds.length === 0) return;

                var fileIds = fileCardIds.map(function(id) {
                    return id.replace('file-', '');
                });

                if (fileIds.length > 0 && destinationFolderId) {
                    var csrfInput = document.querySelector('input[name="csrf"]');
                    var csrfToken = csrfInput ? csrfInput.value : '';

                    fetch('/api/v1/admin/files', {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({
                            file_id: fileIds,
                            target_folder_id: destinationFolderId,
                            csrf: csrfToken
                        }),
                        credentials: 'same-origin'
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            fileCardIds.forEach(function(fileCardId) {
                                var fileCard = document.getElementById(fileCardId);
                                if (fileCard) {
                                    fileCard.style.transition = 'all 0.3s ease';
                                    fileCard.style.opacity = '0';
                                    fileCard.style.transform = 'scale(0.8)';
                                    setTimeout(function() {
                                        if (fileCard.parentNode) {
                                            fileCard.parentNode.removeChild(fileCard);
                                        }
                                    }, 300);
                                }
                            });
                            
                            setTimeout(function() {
                                exitSelectionMode();
                                var remainingFiles = document.querySelectorAll('.file-item-card');
                                var foldersCount = document.querySelectorAll('.folder-card').length;
                                if (remainingFiles.length === 0 && foldersCount === 0) {
                                    window.location.reload();
                                }
                            }, 350);
                        } else {
                            alert('Failed to move files: ' + (res.error || 'Unknown error'));
                        }
                    }).catch(function(err) {
                        alert('Error moving files: ' + err.message);
                    });
                }
            }
        });
    }

    // Batch Selection Logic Implementation
    var isSelectionMode = false;
    var lastClickedCard = null;
    var longPressTimeout = null;
    var longPressTarget = null;
    var longPressJustTriggered = false;

    function enterSelectionMode() {
        if (isSelectionMode) return;
        isSelectionMode = true;
        filesGrid.classList.add('selection-mode');
        document.getElementById('batch-actions-bar').style.display = 'flex';
    }

    function exitSelectionMode() {
        isSelectionMode = false;
        if (filesGrid) filesGrid.classList.remove('selection-mode');
        var selectedCards = document.querySelectorAll('.file-card.selected');
        selectedCards.forEach(function(card) {
            card.classList.remove('selected');
        });
        document.getElementById('batch-actions-bar').style.display = 'none';
        lastClickedCard = null;
    }

    function toggleSelectCard(card) {
        card.classList.toggle('selected');
        lastClickedCard = card;
        updateSelectionCount();
    }

    function updateSelectionCount() {
        var selected = document.querySelectorAll('.file-card.selected');
        var count = selected.length;
        document.getElementById('batch-selected-count').textContent = count + ' item' + (count === 1 ? '' : 's') + ' selected';
        
        if (count === 0) {
            exitSelectionMode();
        }
    }

    function selectRange(card) {
        if (!lastClickedCard) {
            toggleSelectCard(card);
            return;
        }

        var cards = Array.from(filesGrid.querySelectorAll('.file-card'));
        // Exclude the parent/go-up card from selection list
        cards = cards.filter(function(c) {
            return c.getAttribute('data-fid') !== 'parent';
        });

        var lastIdx = cards.indexOf(lastClickedCard);
        var currentIdx = cards.indexOf(card);

        if (lastIdx === -1 || currentIdx === -1) return;

        var startIdx = Math.min(lastIdx, currentIdx);
        var endIdx = Math.max(lastIdx, currentIdx);

        for (var i = startIdx; i <= endIdx; i++) {
            cards[i].classList.add('selected');
        }
        updateSelectionCount();
    }

    function startLongPress(card, e) {
        if (e.target.closest('.action-btn') || e.target.closest('form')) return;
        longPressTarget = card;
        longPressTimeout = setTimeout(function() {
            enterSelectionMode();
            toggleSelectCard(card);
            longPressJustTriggered = true; // Set flag to block subsequent click event from untoggling!
            if (navigator.vibrate) navigator.vibrate(50);
        }, 600);
    }

    function cancelLongPress() {
        if (longPressTimeout) {
            clearTimeout(longPressTimeout);
            longPressTimeout = null;
        }
        longPressTarget = null;
    }

    // Long press and Shift/Ctrl click handling via event delegation
    if (filesGrid) {
        filesGrid.addEventListener('mousedown', function(e) {
            longPressJustTriggered = false; // Reset on new click gesture
            
            var card = e.target.closest('.file-card');
            if (!card || card.getAttribute('data-fid') === 'parent') return;
            
            // Standard left click
            if (e.button === 0) {
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    enterSelectionMode();
                    toggleSelectCard(card);
                } else if (e.shiftKey && isSelectionMode) {
                    e.preventDefault();
                    selectRange(card);
                } else {
                    startLongPress(card, e);
                }
            }
        });

        filesGrid.addEventListener('mouseup', function(e) {
            cancelLongPress();
        });

        filesGrid.addEventListener('mousemove', function(e) {
            cancelLongPress();
        });

        // Mobile touch events
        filesGrid.addEventListener('touchstart', function(e) {
            longPressJustTriggered = false; // Reset gesture
            
            var card = e.target.closest('.file-card');
            if (!card || card.getAttribute('data-fid') === 'parent') return;
            startLongPress(card, e);
        }, { passive: true });

        filesGrid.addEventListener('touchend', function(e) {
            cancelLongPress();
        });

        filesGrid.addEventListener('touchmove', function(e) {
            cancelLongPress();
        });

        // Intercept all card clicks in capture phase to prevent routing when in selection mode
        filesGrid.addEventListener('click', function(e) {
            var card = e.target.closest('.file-card');
            if (!card || card.getAttribute('data-fid') === 'parent') return;

            // Block clicks that were just used to trigger long-press select
            if (longPressJustTriggered) {
                longPressJustTriggered = false;
                e.preventDefault();
                e.stopPropagation();
                return;
            }

            if (isSelectionMode) {
                e.preventDefault();
                e.stopPropagation();
                if (e.shiftKey) {
                    selectRange(card);
                } else {
                    toggleSelectCard(card);
                }
            }
        }, true);
    }

    // Clickaway cancellation for selection mode
    document.addEventListener('click', function(e) {
        if (!isSelectionMode) return;
        
        // If the click is inside a grid card, batch action bar, folder creation button or active modals, ignore!
        if (e.target.closest('.file-card') || e.target.closest('#batch-actions-bar') || e.target.closest('#create-folder-btn') || e.target.closest('.modal')) {
            return;
        }
        
        exitSelectionMode();
    });

    // Batch Actions Event Handlers using RESTful Admin API DELETE
    var batchDeleteBtn = document.getElementById('batch-delete-btn');
    var batchClearBtn = document.getElementById('batch-clear-btn');

    if (batchClearBtn) {
        batchClearBtn.addEventListener('click', function() {
            exitSelectionMode();
        });
    }

    if (batchDeleteBtn) {
        batchDeleteBtn.addEventListener('click', function() {
            var selected = document.querySelectorAll('.file-card.selected');
            if (selected.length === 0) return;

            window.adminConfirm({
                title: 'Delete Multiple Files',
                message: 'Are you sure you want to delete ' + selected.length + ' selected item(s)?',
                note: 'Note: Force deleting these media files will permanently erase them from disk storage.'
            }).then(function(confirmed) {
                if (confirmed) {
                    var ids = Array.from(selected).map(function(card) {
                        return card.id.replace('file-', '');
                    });

                    var csrfInput = document.querySelector('input[name="csrf"]');
                    var csrfToken = csrfInput ? csrfInput.value : '';

                    fetch('/api/v1/admin/files', {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({ id: ids, csrf: csrfToken }),
                        credentials: 'same-origin'
            })
            .then(function(res) {
                if (res.ok) {
                    return res.json();
                }
                throw new Error('Delete failed');
            })
            .then(function(data) {
                if (data.success) {
                    selected.forEach(function(card) {
                        card.parentNode.removeChild(card);
                    });
                    exitSelectionMode();
                    var remainingFiles = document.querySelectorAll('.file-item-card');
                    var foldersCount = document.querySelectorAll('.folder-card').length;
                    if (remainingFiles.length === 0 && foldersCount === 0) {
                        window.location.reload();
                    }
                } else {
                    window.adminConfirm({ 
                        title: 'Error', 
                        message: 'Failed to delete selected items: ' + (data.error || 'Unknown error'), 
                        confirmText: 'OK', 
                        confirmClass: 'btn-confirm-primary' 
                    });
                }
            })
            .catch(function(err) {
                window.adminConfirm({ 
                    title: 'Error', 
                    message: 'Error deleting items: ' + err.message, 
                    confirmText: 'OK', 
                    confirmClass: 'btn-confirm-primary' 
                });
            });
                }
            });
        });
    }

    // Infinite Scroll Logic using RESTful Admin API GET with page
    var hasMore = filesGrid ? filesGrid.getAttribute('data-has-more') === 'true' : false;
    var currentPage = filesGrid ? parseInt(filesGrid.getAttribute('data-current-page') || '1') : 1;
    var folder = filesGrid ? filesGrid.getAttribute('data-folder') || '' : '';
    var isLoading = false;
    var loadingIndicator = document.getElementById('infinite-scroll-loading');

    function checkInfiniteScroll() {
        if (!hasMore || isLoading || !filesGrid) return;

        // Trigger when user scrolls within 200px of bottom
        if ((window.innerHeight + window.scrollY) >= (document.documentElement.scrollHeight - 200)) {
            isLoading = true;
            if (loadingIndicator) loadingIndicator.style.display = 'block';

            var nextPage = currentPage + 1;
            fetch('/api/v1/admin/files?folder=' + encodeURIComponent(folder) + '&page=' + nextPage, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.html && res.html.trim() !== '') {
                    filesGrid.insertAdjacentHTML('beforeend', res.html);
                    currentPage = res.current_page;
                    hasMore = res.has_more;
                    filesGrid.setAttribute('data-current-page', currentPage);
                    filesGrid.setAttribute('data-has-more', hasMore ? 'true' : 'false');
                    
                    // Re-trigger search in case of active search query
                    if (searchInput && searchInput.value.toLowerCase().trim() !== '') {
                        searchInput.dispatchEvent(new Event('input'));
                    }
                } else {
                    hasMore = false;
                    filesGrid.setAttribute('data-has-more', 'false');
                }
                isLoading = false;
                if (loadingIndicator) loadingIndicator.style.display = 'none';
            })
            .catch(function(err) {
                console.error('Error fetching next page:', err);
                isLoading = false;
                if (loadingIndicator) loadingIndicator.style.display = 'none';
            });
        }
    }

    window.addEventListener('scroll', checkInfiniteScroll);
    window.addEventListener('resize', checkInfiniteScroll);
    // Initial check in case viewport is larger than the initial load size
    setTimeout(checkInfiniteScroll, 300);
});
