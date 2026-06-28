document.addEventListener('DOMContentLoaded', function() {
    var container = document.getElementById('blocks-container');
    var triggerBtn = document.getElementById('btn-toggle-inserter');
    var panel = document.getElementById('inserter-panel');
    var form = container ? container.closest('form') : null;

    if (!container || !triggerBtn || !panel || !form) return;

    // Load pre-rendered block templates dictionary dynamically from window object
    const REGISTERED_BLOCK_TEMPLATES = window.REGISTERED_BLOCK_TEMPLATES || {};
    const REGISTERED_BLOCK_SETTINGS_TEMPLATES = window.REGISTERED_BLOCK_SETTINGS_TEMPLATES || {};

    // Vector SVGs loader
    const SVG_CHEVRON_RIGHT = window.SVG_CHEVRON_RIGHT || '';
    const SVG_ARROW_UP = window.SVG_ARROW_UP || '';
    const SVG_ARROW_DOWN = window.SVG_ARROW_DOWN || '';
    const SVG_TRASH_2 = window.SVG_TRASH_2 || '';
    const SVG_SETTINGS = window.SVG_SETTINGS || '';

    function escapeHtmlAttr(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Toggle Inserter Drawer
    function toggleBlockInserter() {
        var btnText = triggerBtn.querySelector('.btn-text');
        var isOpen = panel.classList.toggle('open');
        if (isOpen) {
            if (btnText) btnText.textContent = 'Close Block Inserter';
        } else {
            if (btnText) btnText.textContent = 'Add Content Block';
        }
    }
    triggerBtn.addEventListener('click', toggleBlockInserter);

    // Bind inserter card triggers dynamically for all registered blocks!
    var selectCards = document.querySelectorAll('.block-select-card');
    selectCards.forEach(function(card) {
        card.addEventListener('click', function() {
            var type = card.getAttribute('data-type');
            var label = card.querySelector('h4').textContent;
            var iconSvg = card.querySelector('.icon-wrapper-svg').innerHTML;

            var blockItem = document.createElement('div');
            blockItem.className = 'block-item';
            blockItem.setAttribute('data-type', type);
            blockItem.innerHTML = createBlockHtml(type, label, iconSvg);
            
            container.appendChild(blockItem);
            
            var editorContainer = blockItem.querySelector('.editor');
            if (editorContainer && window.initEditor) {
                window.initEditor(editorContainer);
            }

            toggleBlockInserter(); // Collapse drawer
            updateBlockExcerpts();
            refreshLivePreview(blockItem);
            blockItem.scrollIntoView({ behavior: 'smooth' });
        });
    });

    // Unified JS Block HTML Generator
    function createBlockHtml(type, label, iconSvg) {
        var fieldsHtml = REGISTERED_BLOCK_TEMPLATES[type] || '';
        var settingsHtml = REGISTERED_BLOCK_SETTINGS_TEMPLATES[type] || '';
        var randomId = 'blk_' + Array.from({length: 16}, () => Math.floor(Math.random() * 16).toString(16)).join('');
        return `
            <div class="block-header">
                <div class="block-header-title-area">
                    <span class="icon-svg block-toggle-indicator icon-svg-14">${SVG_CHEVRON_RIGHT}</span>
                    <div class="block-header-icon-wrapper ${type}-icon">
                        ${iconSvg}
                    </div>
                    <div class="block-header-text-container">
                        <h4 class="block-header-title">${escapeHtmlAttr(label)}</h4>
                        <span class="block-preview-excerpt">New, empty block</span>
                    </div>
                </div>
                <div class="block-actions">
                    <button type="button" class="btn-block-settings" title="Row Settings">
                        <span class="icon-svg icon-svg-14">${SVG_SETTINGS}</span>
                    </button>
                    <button type="button" class="btn-move-up" title="Move Up">
                        <span class="icon-svg icon-svg-14">${SVG_ARROW_UP}</span>
                    </button>
                    <button type="button" class="btn-move-down" title="Move Down">
                        <span class="icon-svg icon-svg-14">${SVG_ARROW_DOWN}</span>
                    </button>
                    <button type="button" class="btn-delete" title="Delete">
                        <span class="icon-svg icon-svg-14">${SVG_TRASH_2}</span>
                    </button>
                </div>
            </div>
            <div class="block-body">
                <div class="block-row-settings">
                    ${settingsHtml ? `
                        <div class="block-settings-wrapper">
                            <h4>
                                <span class="icon-svg icon-svg-14">${SVG_SETTINGS}</span>
                                <span>Block Settings</span>
                            </h4>
                            ${settingsHtml}
                            <small>Note: Block-specific layout settings will not affect the preview panel.</small>
                        </div>
                    ` : ''}

                    <div class="row-settings-wrapper ${settingsHtml ? 'has-siblings' : ''}">
                        <h4>
                            <span class="icon-svg icon-svg-14">${SVG_SETTINGS}</span>
                            <span>Row Settings</span>
                        </h4>
                        <div class="form-group">
                            <label>Add Space Before (Top Margin):</label>
                            <select class="block-space_before-select">
                                <option value="none">None (0px)</option>
                                <option value="small">Small (24px)</option>
                                <option value="medium">Medium (48px)</option>
                                <option value="large">Large (80px)</option>
                                <option value="xlarge">Extra Large (120px)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Add Space After (Bottom Margin):</label>
                            <select class="block-space_after-select">
                                <option value="none">None (0px)</option>
                                <option value="small">Small (24px)</option>
                                <option value="medium">Medium (48px)</option>
                                <option value="large">Large (80px)</option>
                                <option value="xlarge">Extra Large (120px)</option>
                            </select>
                        </div>
                        <small>Note: Row spacing changes will not affect the preview panel.</small>
                    </div>
                </div>
                <div class="block-fields-col">
                    <button type="button" class="btn-toggle-preview-inline btn-show-preview-trigger" style="display: none; margin-bottom: 15px;">Show Live Preview</button>
                    <input type="hidden" class="block-id-input" value="${randomId}">
                    ${fieldsHtml}
                </div>
                <div class="block-live-preview-col">
                    <div class="block-live-preview-header">
                        <span>Live Theme Render</span>
                        <div class="block-preview-viewport-controls">
                            <button type="button" class="btn-viewport active" data-viewport="desktop" title="Desktop Viewport">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                            </button>
                            <button type="button" class="btn-viewport" data-viewport="tablet" title="Tablet Viewport">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                            </button>
                            <button type="button" class="btn-viewport" data-viewport="mobile" title="Mobile Viewport">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                            </button>
                        </div>
                        <button type="button" class="btn-toggle-preview-inline btn-hide-preview-trigger">Hide</button>
                    </div>
                    <div class="block-live-preview-iframe-wrapper">
                        <iframe class="block-live-preview-iframe"></iframe>
                    </div>
                </div>
            </div>
        `;
    }

    // Unified, Convention-Based JS Block Fields Serializer
    function serializeBlockFields(blockItem, type) {
        var titleInput = blockItem.querySelector('.block-title-input');
        var editorArea = blockItem.querySelector('.editor-area:not(.block-title-input)');
        if (!editorArea) {
            editorArea = blockItem.querySelector('.editor-area');
        }
        
        var titleVal = '';
        if (titleInput) {
            titleVal = titleInput.hasAttribute('contenteditable') ? titleInput.innerHTML : titleInput.value;
        }
        
        var blockData = {
            type: type,
            title: titleVal,
            content: editorArea ? editorArea.innerHTML : ''
        };

        // 1. Serialize standard fields dynamically by looking for .block-{field}-input / -select classes
        // e.g. .block-image_path-input, .block-image_position-select
        var fields = blockItem.querySelectorAll('[class*="block-"]');
        fields.forEach(function(el) {
            var classes = Array.from(el.classList);
            var fieldClass = classes.find(function(c) {
                return c.indexOf('block-') === 0 && 
                       c !== 'block-item' && 
                       c !== 'block-header' && 
                       c !== 'block-body' && 
                       c !== 'block-fields-col' && 
                       c !== 'block-live-preview-col' && 
                       c !== 'block-title-input' && 
                       c !== 'block-actions';
            });
            if (fieldClass) {
                var fieldName = fieldClass.replace('block-', '').replace('-input', '').replace('-select', '').replace('-textarea', '');
                blockData[fieldName] = el.value;
            }
        });

        // 2. Serialize structured item lists (e.g. gallery, masonry, testimonials, accordion)
        // If the block contains `.gallery-media_id-input` elements:
        var galleryInputs = blockItem.querySelectorAll('.gallery-media_id-input');
        if (galleryInputs.length > 0) {
            var mediaIds = [];
            galleryInputs.forEach(function(inp) {
                if (inp.value) mediaIds.push(inp.value);
            });
            blockData.media_ids = mediaIds;
        }

        // If the block contains item rows like `.masonry-item-row`, `.testimonial-item-row`, `.accordion-item-row`
        var itemRows = blockItem.querySelectorAll('.masonry-item-row, .testimonial-item-row, .accordion-item-row, .grid-item-row, .chart-item-row, .form_field-item-row');
        if (itemRows.length > 0) {
            var items = [];
            itemRows.forEach(function(row) {
                var rowData = {};
                // Find all inputs, textareas, selects, and contenteditable editor-areas inside this row
                var inputs = row.querySelectorAll('input, textarea, select, .editor-area');
                inputs.forEach(function(inp) {
                    var classes = Array.from(inp.classList);
                    var itemInputClass = classes.find(function(c) {
                        return c.indexOf('-item-') !== -1 && c.indexOf('-display') === -1;
                    });
                    if (itemInputClass) {
                        // Extract field name e.g. .masonry-item-title-input -> title, .accordion-item-content-input -> content
                        var parts = itemInputClass.split('-item-');
                        var fieldName = parts[parts.length - 1].replace('-input', '');
                        
                        // If it's a contenteditable editor-area, serialize its innerHTML content, otherwise use value
                        if (inp.classList.contains('editor-area')) {
                            rowData[fieldName] = inp.innerHTML;
                        } else {
                            rowData[fieldName] = inp.value;
                        }
                    }
                });
                items.push(rowData);
            });
            blockData.items = items;
        }

        // Specific overrides (such as testimonials duration)
        if (type === 'testimonials') {
            var durationInput = blockItem.querySelector('.testimonials-duration-input');
            blockData.duration = durationInput ? parseInt(durationInput.value) || 5000 : 5000;
        }

        return blockData;
    }

    // Update block header preview excerpts
    function updateBlockExcerpts() {
        var blockItems = container.querySelectorAll('.block-item');
        blockItems.forEach(function(item) {
            var type = item.getAttribute('data-type');
            var excerptSpan = item.querySelector('.block-preview-excerpt');
            if (!excerptSpan) return;

            var titleInput = item.querySelector('.block-title-input');
            var text = '';
            if (titleInput) {
                var rawText = titleInput.hasAttribute('contenteditable') ? titleInput.innerHTML : titleInput.value;
                // Cleanly strip HTML tags to show pristine plain-text excerpt
                text = rawText.replace(/<\/?[^>]+(>|$)/g, "");
            }

            if (!text) {
                if (type === 'text') {
                    var editorArea = item.querySelector('.editor-area');
                    text = editorArea ? editorArea.textContent.substring(0, 45) + '...' : '';
                } else if (type === 'text_image') {
                    text = 'Text and image layout';
                } else if (type === 'gallery') {
                    var count = item.querySelectorAll('.gallery-image-row').length;
                    text = count + ' gallery images';
                } else if (type === 'masonry') {
                    var count = item.querySelectorAll('.masonry-item-row').length;
                    text = count + ' lookbook items';
                } else if (type === 'testimonials') {
                    var count = item.querySelectorAll('.testimonial-item-row').length;
                    text = count + ' customer reviews';
                } else if (type === 'accordion') {
                    var count = item.querySelectorAll('.accordion-item-row').length;
                    text = count + ' collapsible items';
                } else if (type === 'categories') {
                    text = 'Grid of all category cards';
                } else if (type === 'latest_articles') {
                    var limitSelect = item.querySelector('.block-limit-select');
                    var layoutSelect = item.querySelector('.block-layout-select');
                    var limitVal = limitSelect ? limitSelect.value : '3';
                    var layoutVal = layoutSelect ? layoutSelect.value : 'grid';
                    text = 'Dynamic list of ' + limitVal + ' posts in ' + layoutVal + ' layout';
                } else if (type === 'code') {
                    var codeInput = item.querySelector('.block-code-input');
                    text = codeInput ? codeInput.value.substring(0, 45) + '...' : '';
                }
            }

            if (!text) {
                text = 'New, empty block';
            }
            excerptSpan.textContent = text;
        });
    }

    // Debounce state map to prevent multiple quick fetch requests
    var previewDebounceMap = new Map();

    // Core Function to render live previews directly inside the block's left column iframe
    function updateViewportScale(previewCol) {
        if (!previewCol) return;
        var iframeWrapper = previewCol.querySelector('.block-live-preview-iframe-wrapper');
        if (!iframeWrapper) return;

        var activeBtn = previewCol.querySelector('.btn-viewport.active');
        var viewport = activeBtn ? activeBtn.getAttribute('data-viewport') : 'desktop';

        var colWidth = iframeWrapper.parentNode.clientWidth || 400;
        // Subtract a 24px layout safety margin to account for padding, borders, and column breathing room!
        var usableWidth = Math.max(280, colWidth - 24);

        var virtualWidth = 1200;
        if (viewport === 'tablet') virtualWidth = 820;
        if (viewport === 'mobile') virtualWidth = 375;

        // Account for CSS padding/borders inside clientWidth bounds
        var limitWidth = Math.min(usableWidth, virtualWidth);
        var scale = limitWidth / virtualWidth;

        iframeWrapper.setAttribute('data-scale', scale);
        iframeWrapper.setAttribute('data-virtual-width', virtualWidth);

        // Reset inline style overrides so CSS max-widths control wrapper width
        iframeWrapper.style.width = '';
        iframeWrapper.style.transform = '';
        iframeWrapper.style.height = '';

        // Apply custom CSS variables for our new iframe scaling engine
        iframeWrapper.style.setProperty('--viewport-scale', scale);
        iframeWrapper.style.setProperty('--viewport-width', virtualWidth + 'px');

        iframeWrapper.classList.remove('viewport-desktop', 'viewport-tablet', 'viewport-mobile');
        iframeWrapper.classList.add('viewport-' + viewport);
    }

    function refreshLivePreview(blockItem) {
        if (!blockItem || blockItem.classList.contains('collapsed') || blockItem.classList.contains('preview-hidden')) return;
        
        var iframe = blockItem.querySelector('.block-live-preview-iframe');
        if (!iframe) return;

        // Clear existing debounce timeouts for this specific block to prevent hammering the server
        if (previewDebounceMap.has(blockItem)) {
            clearTimeout(previewDebounceMap.get(blockItem));
        }

        var timeoutId = setTimeout(function() {
            var type = blockItem.getAttribute('data-type');
            var blockData = serializeBlockFields(blockItem, type);
            var csrfToken = document.querySelector('input[name="csrf"]').value;

            fetch('/api/v1/admin/block-preview', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    csrf: csrfToken,
                    block: blockData
                })
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    var doc = iframe.contentWindow.document;
                    doc.open();
                    doc.write('<!DOCTYPE html><html><head><meta charset="utf-8">');
                    data.stylesheets.forEach(function(href) {
                        doc.write('<link rel="stylesheet" href="' + href + '?v=' + Date.now() + '">');
                    });
                    doc.write('<style>');
                    doc.write('body { padding: 30px; background-color: var(--bg-color, #ffffff); color: var(--text-color, #000000); font-family: sans-serif; margin: 0; box-sizing: border-box; }');
                    doc.write('</style>');
                    doc.write('</head><body class="theme-' + data.theme + '">');
                    doc.write('<div class="portfolio-wrapper" style="padding: 0; margin: 0;"><main style="padding: 0; border: none; box-shadow: none;">');
                    doc.write(data.html);
                    doc.write('</main></div>');
                    
                    if (type === 'testimonials') {
                        doc.write('<script src="/assets/js/blocks/testimonials.js?v=' + Date.now() + '"><\/script>');
                    } else if (type === 'accordion') {
                        doc.write('<script src="/assets/js/blocks/accordion.js?v=' + Date.now() + '"><\/script>');
                    } else if (type === 'baseline') {
                        doc.write('<script src="/assets/js/blocks/baseline.js?v=' + Date.now() + '"><\/script>');
                    }
                    doc.write('</body></html>');
                    doc.close();

                    var previewCol = blockItem.querySelector('.block-live-preview-col');
                    if (previewCol) {
                        updateViewportScale(previewCol);
                    }

                    // Absolute, double-guaranteed fallback listener bound directly from parent window
                    setTimeout(function() {
                        try {
                            var iframeWindow = iframe.contentWindow;
                            var iframeDoc = iframeWindow ? iframeWindow.document : null;
                            if (iframeDoc) {
                                iframeDoc.addEventListener('click', function(evt) {
                                    var trigger = evt.target.closest('.accordion-trigger');
                                    if (!trigger) return;
                                    evt.preventDefault();
                                    
                                    var item = trigger.closest('.accordion-item');
                                    if (!item) return;
                                    
                                    var panel = item.querySelector('.accordion-panel');
                                    var title = item.querySelector('.accordion-title');
                                    var lineV = item.querySelector('.accordion-line-v');
                                    
                                    if (!panel) return;
                                    
                                    var isOpen = item.classList.contains('active');
                                    
                                    // Collapse others
                                    var activeItems = item.parentNode.querySelectorAll('.accordion-item.active');
                                    activeItems.forEach(function(activeItem) {
                                        if (activeItem !== item) {
                                            activeItem.classList.remove('active');
                                            activeItem.querySelector('.accordion-panel').style.maxHeight = '0px';
                                            activeItem.querySelector('.accordion-title').style.color = '#ffffff';
                                            var activeLineV = activeItem.querySelector('.accordion-line-v');
                                            if (activeLineV) activeLineV.style.transform = 'rotate(0deg)';
                                        }
                                    });
                                    
                                    item.classList.toggle('active', !isOpen);
                                    
                                    if (!isOpen) {
                                        panel.style.maxHeight = panel.scrollHeight + 'px';
                                        if (title) title.style.color = 'var(--accent-color, #d4af37)';
                                        if (lineV) lineV.style.transform = 'rotate(90deg)';
                                    } else {
                                        panel.style.maxHeight = '0px';
                                        if (title) title.style.color = '#ffffff';
                                        if (lineV) lineV.style.transform = 'rotate(0deg)';
                                    }
                                });
                            }
                        } catch (ex) {
                            // Safe fallback
                        }
                    }, 50);
                }
            })
            .catch(function(err) {
                console.warn('Live preview failed:', err.message);
            });
        }, 300); // 300ms Debounce threshold

        previewDebounceMap.set(blockItem, timeoutId);
    }

    // Bind real-time input and change listeners on content fields to update live previews on typing/selection
    container.addEventListener('input', function(e) {
        updateBlockExcerpts();
        var blockItem = e.target.closest('.block-item');
        if (blockItem) {
            refreshLivePreview(blockItem);
        }
    });
    container.addEventListener('change', function(e) {
        updateBlockExcerpts();
        var blockItem = e.target.closest('.block-item');
        if (blockItem) {
            refreshLivePreview(blockItem);
        }
    });

    // Handle block actions using event delegation
    container.addEventListener('click', function(e) {
        var header = e.target.closest('.block-header');
        if (header && !e.target.closest('button') && !e.target.closest('input') && !e.target.closest('textarea') && !e.target.closest('.editor-area')) {
            var blockItem = header.closest('.block-item');
            if (blockItem) {
                blockItem.classList.toggle('collapsed');
                if (!blockItem.classList.contains('collapsed')) {
                    setTimeout(function() {
                        refreshLivePreview(blockItem);
                    }, 50);
                }
            }
            return;
        }

        var gridHeader = e.target.closest('.grid-item-row-header');
        if (gridHeader && !e.target.closest('button') && !e.target.closest('input') && !e.target.closest('textarea') && !e.target.closest('.editor-area')) {
            var gridRow = gridHeader.closest('.grid-item-row');
            if (gridRow) {
                gridRow.classList.toggle('collapsed');
            }
            return;
        }

        var btnViewport = e.target.closest('.btn-viewport');
        if (btnViewport) {
            var viewportControls = btnViewport.closest('.block-preview-viewport-controls');
            var previewCol = btnViewport.closest('.block-live-preview-col');
            var blockItem = btnViewport.closest('.block-item');

            viewportControls.querySelectorAll('.btn-viewport').forEach(function(b) {
                b.classList.remove('active');
            });
            btnViewport.classList.add('active');

            updateViewportScale(previewCol);
            if (blockItem) {
                refreshLivePreview(blockItem);
            }
            return;
        }

        var btn = e.target.closest('button');
        if (!btn) return;

        var blockItem = btn.closest('.block-item');
        if (!blockItem) return;

        if (btn.classList.contains('btn-block-settings')) {
            var settingsCol = blockItem.querySelector('.block-row-settings');
            var fieldsCol = blockItem.querySelector('.block-fields-col');
            if (settingsCol && fieldsCol) {
                var isSettingsVisible = settingsCol.style.display === 'block';
                if (isSettingsVisible) {
                    settingsCol.style.display = 'none';
                    fieldsCol.style.display = 'block';
                    btn.classList.remove('active');
                } else {
                    settingsCol.style.display = 'block';
                    fieldsCol.style.display = 'none';
                    btn.classList.add('active');
                }
            }
        } else if (btn.classList.contains('btn-delete')) {
            window.adminConfirm({
                title: 'Delete Block',
                message: 'Are you sure you want to delete this block?'
            }).then(function(confirmed) {
                if (confirmed) {
                    blockItem.parentNode.removeChild(blockItem);
                    updateBlockExcerpts();
                }
            });
        } else if (btn.classList.contains('btn-move-up')) {
            var prev = blockItem.previousElementSibling;
            if (prev) {
                container.insertBefore(blockItem, prev);
            }
        } else if (btn.classList.contains('btn-move-down')) {
            var next = blockItem.nextElementSibling;
            if (next) {
                container.insertBefore(blockItem, next.nextSibling);
            }
        } else if (btn.classList.contains('btn-hide-preview-trigger')) {
            blockItem.classList.add('preview-hidden');
            var showBtn = blockItem.querySelector('.btn-show-preview-trigger');
            if (showBtn) showBtn.style.display = 'inline-flex';
            btn.style.display = 'none';
        } else if (btn.classList.contains('btn-show-preview-trigger')) {
            blockItem.classList.remove('preview-hidden');
            var hideBtn = blockItem.querySelector('.btn-hide-preview-trigger');
            if (hideBtn) hideBtn.style.display = 'inline-flex';
            btn.style.display = 'none';
            refreshLivePreview(blockItem);
        } else if (btn.classList.contains('btn-select-block-image')) {
            var displayInput = blockItem.querySelector('.block-media_id-input');
            if (displayInput) {
                window.openImagePicker(function(file){
                    displayInput.value = file.id;
                    updateBlockExcerpts();
                    refreshLivePreview(blockItem);
                });
            }
        } else if (btn.classList.contains('btn-add-gallery-image')) {
            var listContainer = blockItem.querySelector('.gallery-images-list');
            if (listContainer) {
                window.openImagePicker(function(file){
                    var row = document.createElement('div');
                    row.className = 'gallery-image-row';
                    row.innerHTML = `
                        <input type="hidden" class="gallery-media_id-input" value="${escapeHtmlAttr(file.id)}">
                        <div class="gallery-image-preview-wrapper">
                            <img class="gallery-image-preview" src="${escapeHtmlAttr(file.path)}">
                        </div>
                        <div class="gallery-image-filename">${escapeHtmlAttr(file.filename)}</div>
                        <button type="button" class="btn-delete-gallery-image">Remove</button>
                    `;
                    listContainer.appendChild(row);
                    updateBlockExcerpts();
                    refreshLivePreview(blockItem);
                });
            }
        } else if (btn.classList.contains('btn-delete-gallery-image')) {
            var row = btn.closest('.gallery-image-row');
            if (row) {
                row.parentNode.removeChild(row);
                updateBlockExcerpts();
                refreshLivePreview(blockItem);
            }
        } else if (btn.classList.contains('btn-add-masonry-item')) {
            var listContainer = blockItem.querySelector('.masonry-items-list');
            if (listContainer) {
                var row = document.createElement('div');
                row.className = 'masonry-item-row';
                row.innerHTML = `
                    <button type="button" class="btn-delete-masonry-item">Remove</button>
                    <div class="block-child-fields-col">
                        <div class="field-group block-child-field-group-8">
                            <label class="block-child-label-desc">Item Title</label>
                            <input type="text" class="masonry-item-title-input" value="" placeholder="Enter item title...">
                        </div>
                        <div class="field-group block-child-field-group-8">
                            <label class="block-child-label-desc">Item Image</label>
                            <div class="block-child-image-select-row">
                                <input type="hidden" class="masonry-item-media_id-input" value="">
                                <input type="text" class="masonry-item-media-display-input" value="" placeholder="No image selected" readonly style="flex: 1;">
                                <button type="button" class="btn-select-masonry-image">Select</button>
                            </div>
                        </div>
                        <div class="field-group block-child-field-group-0">
                            <label class="block-child-label-desc">Item Description</label>
                            <textarea class="masonry-item-desc-input" placeholder="Enter item description..." rows="2"></textarea>
                        </div>
                    </div>
                `;
                listContainer.appendChild(row);
                updateBlockExcerpts();
                refreshLivePreview(blockItem);
            }
        } else if (btn.classList.contains('btn-select-masonry-image')) {
            var row = btn.closest('.masonry-item-row');
            var idInput = row ? row.querySelector('.masonry-item-media_id-input') : null;
            var displayInput = row ? row.querySelector('.masonry-item-media-display-input') : null;
            if (idInput && displayInput) {
                window.openImagePicker(function(file){
                    idInput.value = file.id;
                    displayInput.value = file.filename;
                    updateBlockExcerpts();
                    refreshLivePreview(blockItem);
                });
            }
        } else if (btn.classList.contains('btn-delete-masonry-item')) {
            var row = btn.closest('.masonry-item-row');
            if (row) {
                row.parentNode.removeChild(row);
                updateBlockExcerpts();
                refreshLivePreview(blockItem);
            }
        } else if (btn.classList.contains('btn-add-grid-item')) {
            var listContainer = blockItem.querySelector('.grid-items-list');
            if (listContainer) {
                var index = listContainer.querySelectorAll('.grid-item-row').length + 1;
                var row = document.createElement('div');
                row.className = 'grid-item-row';
                row.innerHTML = `
                    <button type="button" class="btn-delete-grid-item" title="Remove Grid Card">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                    </button>
                    
                    <!-- Collapsible Header Panel (Clickable to Toggle Collapse/Expand) -->
                    <div class="grid-item-row-header">
                        <div class="grid-item-row-title-label">
                            <span class="grid-item-row-collapse-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                            </span>
                            <span class="grid-item-row-title-text">Grid Card (Untitled)</span>
                        </div>
                        <div class="grid-item-controls">
                            <button type="button" class="btn-sort-grid-item-up" title="Move Card Up">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline></svg>
                            </button>
                            <button type="button" class="btn-sort-grid-item-down" title="Move Card Down">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Collapsible Fields Container -->
                    <div class="block-child-fields-col grid-item-fields-container" style="width: 100%;">
                        <div class="field-group block-child-field-group-8">
                            <label class="block-child-label-desc">Card Title</label>
                            <input type="text" class="grid-item-title-input" value="" placeholder="Enter card title...">
                        </div>
                        <div class="field-group block-child-field-group-8">
                            <label class="block-child-label-desc">Card Image (Optional)</label>
                            <div class="block-child-image-select-row">
                                <input type="hidden" class="grid-item-media_id-input" value="">
                                <input type="text" class="grid-item-media-display-input" value="" placeholder="No image selected" readonly style="flex: 1;">
                                <button type="button" class="btn-select-grid-image">Select</button>
                            </div>
                        </div>
                        <div class="field-group block-child-field-group-0">
                            <label class="block-child-label-desc">Card Description</label>
                            <textarea class="grid-item-desc-input" placeholder="Enter card description..." rows="2"></textarea>
                        </div>
                        <div class="field-group block-child-field-group-0" style="margin-top: 8px;">
                            <label class="block-child-label-desc">Card Click URL Link (e.g. /intro)</label>
                            <input type="text" class="grid-item-link_url-input" value="" placeholder="Enter card target URL...">
                        </div>
                        <div class="block-flex-row" style="margin-top: 8px; display: flex; gap: 10px;">
                            <div class="field-group block-flex-col-1" style="flex: 1;">
                                <label class="block-child-label-desc">Desktop Column Span</label>
                                <select class="grid-item-col_span_desktop-select">
                                    <option value="1" selected>1 Column</option>
                                    <option value="2">2 Columns</option>
                                    <option value="3">3 Columns</option>
                                    <option value="4">4 Columns</option>
                                </select>
                            </div>
                            <div class="field-group block-flex-col-1" style="flex: 1;">
                                <label class="block-child-label-desc">Tablet Column Span</label>
                                <select class="grid-item-col_span_tablet-select">
                                    <option value="1" selected>1 Column</option>
                                    <option value="2">2 Columns</option>
                                    <option value="3">3 Columns</option>
                                </select>
                            </div>
                        </div>
                    </div>
                `;
                listContainer.appendChild(row);
                updateBlockExcerpts();
                refreshLivePreview(blockItem);
            }
        } else if (btn.classList.contains('btn-select-grid-image')) {
            var row = btn.closest('.grid-item-row');
            var idInput = row ? row.querySelector('.grid-item-media_id-input') : null;
            var displayInput = row ? row.querySelector('.grid-item-media-display-input') : null;
            if (idInput && displayInput) {
                window.openImagePicker(function(file){
                    idInput.value = file.id;
                    displayInput.value = file.filename;
                    updateBlockExcerpts();
                    refreshLivePreview(blockItem);
                });
            }
        } else if (btn.classList.contains('btn-delete-grid-item')) {
            var row = btn.closest('.grid-item-row');
            if (row) {
                row.parentNode.removeChild(row);
                updateBlockExcerpts();
                refreshLivePreview(blockItem);
            }
        } else if (btn.classList.contains('btn-sort-grid-item-up')) {
            var row = btn.closest('.grid-item-row');
            if (row) {
                var prev = row.previousElementSibling;
                if (prev) {
                    row.parentNode.insertBefore(row, prev);
                    updateBlockExcerpts();
                    refreshLivePreview(blockItem);
                }
            }
        } else if (btn.classList.contains('btn-sort-grid-item-down')) {
            var row = btn.closest('.grid-item-row');
            if (row) {
                var next = row.nextElementSibling;
                if (next) {
                    row.parentNode.insertBefore(next, row);
                    updateBlockExcerpts();
                    refreshLivePreview(blockItem);
                }
            }
        } else if (btn.classList.contains('btn-add-testimonial-item')) {
            var listContainer = blockItem.querySelector('.testimonials-items-list');
            if (listContainer) {
                var row = document.createElement('div');
                row.className = 'testimonial-item-row';
                row.innerHTML = `
                    <button type="button" class="btn-delete-testimonial-item">Remove</button>
                    <div class="block-child-fields-col">
                        <div class="field-group block-child-field-group-8">
                            <label class="block-child-label-desc">Quote / Content</label>
                            <div class="editor">
                                <div class="toolbar">
                                    <button type="button" data-cmd="bold"><strong>B</strong></button>
                                    <button type="button" data-cmd="italic"><em>I</em></button>
                                    <button type="button" data-cmd="underline"><u>U</u></button>
                                    <button type="button" data-cmd="insertUnorderedList">UL</button>
                                    <button type="button" data-cmd="insertOrderedList">OL</button>
                                    <button type="button" data-cmd="createLink">A</button>
                                    <button type="button" data-cmd="removeFormat">Clear</button>
                                </div>
                                <div class="editor-area block-editor-area testimonial-item-content-input" contenteditable="true" style="min-height: 100px;"></div>
                            </div>
                        </div>
                        <div class="field-group block-child-field-group-0">
                            <label class="block-child-label-desc">Author / Person</label>
                            <input type="text" class="testimonial-item-person-input" value="" placeholder="e.g. Jane Doe, CEO at Studio">
                        </div>
                    </div>
                `;
                listContainer.appendChild(row);

                // Initialize WYSIWYG editor inside the new row
                var newEditor = row.querySelector('.editor');
                if (newEditor && window.initEditor) {
                    window.initEditor(newEditor);
                }

                updateBlockExcerpts();
                refreshLivePreview(blockItem);
            }
        } else if (btn.classList.contains('btn-delete-testimonial-item')) {
            var row = btn.closest('.testimonial-item-row');
            if (row) {
                row.parentNode.removeChild(row);
                updateBlockExcerpts();
                refreshLivePreview(blockItem);
            }
        } else if (btn.classList.contains('btn-add-accordion-item')) {
            var listContainer = blockItem.querySelector('.accordion-items-list');
            if (listContainer) {
                var row = document.createElement('div');
                row.className = 'accordion-item-row';
                row.innerHTML = `
                    <button type="button" class="btn-delete-accordion-item">Remove</button>
                    <div class="block-child-fields-col">
                        <div class="field-group block-child-field-group-8">
                            <label class="block-child-label-desc">Header / Question</label>
                            <input type="text" class="accordion-item-title-input" value="" placeholder="Enter heading question...">
                        </div>
                        <div class="field-group block-child-field-group-0">
                            <label class="block-child-label-desc">Panel Content / Answer</label>
                            <div class="editor">
                                <div class="toolbar">
                                    <button type="button" data-cmd="bold"><strong>B</strong></button>
                                    <button type="button" data-cmd="italic"><em>I</em></button>
                                    <button type="button" data-cmd="underline"><u>U</u></button>
                                    <button type="button" data-cmd="insertUnorderedList">UL</button>
                                    <button type="button" data-cmd="insertOrderedList">OL</button>
                                    <button type="button" data-cmd="createLink">A</button>
                                    <button type="button" data-cmd="removeFormat">Clear</button>
                                </div>
                                <div class="editor-area block-editor-area accordion-item-content-input" contenteditable="true" style="min-height: 100px;"></div>
                            </div>
                        </div>
                    </div>
                `;
                listContainer.appendChild(row);

                // Initialize WYSIWYG editor inside the new row
                var newEditor = row.querySelector('.editor');
                if (newEditor && window.initEditor) {
                    window.initEditor(newEditor);
                }

                updateBlockExcerpts();
                refreshLivePreview(blockItem);
            }
        } else if (btn.classList.contains('btn-add-chart-item')) {
            var listContainer = blockItem.querySelector('.chart-items-list');
            if (listContainer) {
                var row = document.createElement('div');
                row.className = 'chart-item-row';
                row.innerHTML = `
                    <div class="block-child-fields-col">
                        <div class="field-group">
                            <label>Bar Label</label>
                            <input type="text" class="chart-item-label-input" value="" placeholder="e.g. Zero CMS">
                        </div>
                        <div class="field-group">
                            <label>Numeric Value</label>
                            <input type="number" step="any" class="chart-item-value-input" value="" placeholder="e.g. 30.31">
                        </div>
                    </div>
                    <button type="button" class="btn-delete-chart-item" title="Remove Data Point">
                        <span class="icon-svg icon-svg-14">${SVG_TRASH_2}</span>
                    </button>
                `;
                listContainer.appendChild(row);
                updateBlockExcerpts();
                refreshLivePreview(blockItem);
            }
        } else if (btn.classList.contains('btn-delete-chart-item')) {
            var row = btn.closest('.chart-item-row');
            if (row) {
                row.parentNode.removeChild(row);
                updateBlockExcerpts();
                refreshLivePreview(blockItem);
            }
        } else if (btn.classList.contains('btn-add-form-field')) {
            var listContainer = blockItem.querySelector('.form-fields-items-list');
            if (listContainer) {
                var row = document.createElement('div');
                row.className = 'form_field-item-row';
                row.innerHTML = `
                    <button type="button" class="btn-delete-form-field">Remove</button>
                    <div class="block-child-fields-col">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; width: 100%;">
                            <div class="field-group block-child-field-group-8">
                                <label class="block-child-label-desc">Field Database Key *</label>
                                <input type="text" class="form_field-item-name-input" value="" placeholder="e.g. first_name" required>
                            </div>
                            <div class="field-group block-child-field-group-8">
                                <label class="block-child-label-desc">Field Visual Label *</label>
                                <input type="text" class="form_field-item-label-input" value="" placeholder="e.g. First Name" required>
                            </div>
                            <div class="field-group block-child-field-group-8">
                                <label class="block-child-label-desc">Input Type</label>
                                <select class="form_field-item-type-select">
                                    <option value="text">Text Input</option>
                                    <option value="textarea">Text Area</option>
                                    <option value="email">Email Address</option>
                                    <option value="tel">Telephone (Phone)</option>
                                    <option value="number">Number Input</option>
                                    <option value="select">Dropdown Select</option>
                                    <option value="checkbox">Checkboxes List</option>
                                    <option value="radio">Radio Buttons List</option>
                                </select>
                            </div>
                            <div class="field-group block-child-field-group-8">
                                <label class="block-child-label-desc">Required Status</label>
                                <select class="form_field-item-required-select">
                                    <option value="0">Optional</option>
                                    <option value="1">Required</option>
                                </select>
                            </div>
                            <div class="field-group block-child-field-group-8">
                                <label class="block-child-label-desc">Options (Select/Check/Radio)</label>
                                <input type="text" class="form_field-item-options-input" value="" placeholder="Option1, Option2, Option3">
                            </div>
                            <div class="field-group block-child-field-group-8">
                                <label class="block-child-label-desc">Type Validation</label>
                                <select class="form_field-item-validation-select">
                                    <option value="none">No Validation</option>
                                    <option value="email">Email Address</option>
                                    <option value="phone">Telephone Number</option>
                                    <option value="numeric">Any Numeric</option>
                                    <option value="integer">Integer Only</option>
                                </select>
                            </div>
                        </div>
                    </div>
                `;
                listContainer.appendChild(row);
                updateBlockExcerpts();
                refreshLivePreview(blockItem);
            }
        } else if (btn.classList.contains('btn-delete-form-field')) {
            var row = btn.closest('.form_field-item-row');
            if (row) {
                row.parentNode.removeChild(row);
                updateBlockExcerpts();
                refreshLivePreview(blockItem);
            }
        } else if (btn.classList.contains('btn-delete-accordion-item')) {
            var row = btn.closest('.accordion-item-row');
            if (row) {
                row.parentNode.removeChild(row);
                updateBlockExcerpts();
                refreshLivePreview(blockItem);
            }
        }
    });

    // Populate live block previews on DOM load for initially loaded blocks
    var initialBlocks = container.querySelectorAll('.block-item');
    initialBlocks.forEach(function(item) {
        if (!item.classList.contains('collapsed')) {
            refreshLivePreview(item);
            var previewCol = item.querySelector('.block-live-preview-col');
            if (previewCol) {
                updateViewportScale(previewCol);
            }
        }
    });

    window.addEventListener('resize', function() {
        document.querySelectorAll('.block-live-preview-col').forEach(function(col) {
            updateViewportScale(col);
        });
    });

    // On form submit, serialize all blocks into JSON and put them into the hidden input
    if (form) {
        form.addEventListener('submit', function() {
            var blocks = [];
            var blockItems = container.querySelectorAll('.block-item');
            
            blockItems.forEach(function(item) {
                var type = item.getAttribute('data-type');
                var blockData = serializeBlockFields(item, type);
                blocks.push(blockData);
            });

            var outputInput = document.getElementById('block-builder-output');
            if (outputInput) {
                outputInput.value = JSON.stringify(blocks);
            }
        });
    }
});
