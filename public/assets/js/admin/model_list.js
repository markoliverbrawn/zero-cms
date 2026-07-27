document.addEventListener('DOMContentLoaded', () => {
    // Audit Log Purge Button click handler
    const purgeBtn = document.getElementById('btn-purge-logs');
    if (purgeBtn) {
        purgeBtn.addEventListener('click', () => {
            const csrf = purgeBtn.getAttribute('data-csrf') || '';
            const isSuper = purgeBtn.getAttribute('data-is-super') === '1';

            let optionsHtml = '';
            if (isSuper) {
                optionsHtml = `
                    <label class="modal-checkbox-wrapper" title="Purge logs globally across all sites instead of just this site">
                        <input type="checkbox" id="chk-purge-all-sites" name="purge_all_sites" value="1" />
                        <span>Purge logs for all sites globally (Super Admin only)</span>
                    </label>
                `;
            }

            // Listen for checkbox changes while the modal is open
            const handleCheckboxChange = (e) => {
                if (e.target && e.target.id === 'chk-purge-all-sites') {
                    const msgEl = document.getElementById('admin-modal-message');
                    if (msgEl) {
                        msgEl.textContent = e.target.checked
                            ? 'Are you sure you want to permanently purge audit logs globally across ALL sites? This action is irreversible.'
                            : 'Are you sure you want to permanently purge all audit logs for this site? This action is irreversible.';
                    }
                }
            };
            document.addEventListener('change', handleCheckboxChange);

            window.adminConfirm({
                title: 'Purge Audit Logs',
                message: 'Are you sure you want to permanently purge all audit logs for this site? This action is irreversible.',
                confirmText: 'Purge',
                confirmClass: 'btn-danger',
                optionsHtml: optionsHtml
            }).then((confirmed) => {
                document.removeEventListener('change', handleCheckboxChange);

                if (!confirmed) return;

                const chkPurgeAll = document.getElementById('chk-purge-all-sites');
                const isPurgeAll = chkPurgeAll ? chkPurgeAll.checked : false;

                purgeBtn.disabled = true;
                if (chkPurgeAll) chkPurgeAll.disabled = true;

                fetch('/api/v1/admin/audit-logs/purge', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrf
                    },
                    body: JSON.stringify({
                        csrf: csrf,
                        purge_all_sites: isPurgeAll ? 1 : 0
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.adminAlert('Logs Purged', data.message || 'Successfully purged audit logs.');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        window.adminAlert('Purge Failed', data.error || 'An error occurred.');
                        purgeBtn.disabled = false;
                        if (chkPurgeAll) chkPurgeAll.disabled = false;
                    }
                })
                .catch(err => {
                    window.adminAlert('Purge Error', err.message);
                    purgeBtn.disabled = false;
                    if (chkPurgeAll) chkPurgeAll.disabled = false;
                });
            });
        });
    }

    const tableBody = document.querySelector('.listrecords table tbody');
    if (!tableBody) return;

    let dragSrcEl = null;

    function handleDragStart(e) {
        const row = e.target.closest('tr');
        if (!row || row.getAttribute('draggable') !== 'true') return;

        dragSrcEl = row;
        row.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', row.getAttribute('data-id'));
    }

    function handleDragOver(e) {
        if (e.preventDefault) {
            e.preventDefault();
        }
        e.dataTransfer.dropEffect = 'move';
        return false;
    }

    function handleDragEnter(e) {
        const row = e.target.closest('tr');
        if (row && row !== dragSrcEl && row.getAttribute('draggable') === 'true') {
            row.classList.add('drag-over');
        }
    }

    function handleDragLeave(e) {
        const row = e.target.closest('tr');
        if (row) {
            row.classList.remove('drag-over');
        }
    }

    function updatePrecedenceNumbers() {
        const rows = tableBody.querySelectorAll('tr[data-id]');
        rows.forEach((row, index) => {
            const cell = row.querySelector('td[data-field="precedence"]');
            if (cell) {
                cell.textContent = 10 * (index + 1);
            }
        });
    }

    function handleDrop(e) {
        if (e.stopPropagation) {
            e.stopPropagation();
        }

        const targetRow = e.target.closest('tr');
        if (dragSrcEl && targetRow && targetRow !== dragSrcEl && targetRow.getAttribute('draggable') === 'true') {
            targetRow.classList.remove('drag-over');

            const rect = targetRow.getBoundingClientRect();
            const next = (e.clientY - rect.top) > (rect.height / 2);

            if (next) {
                tableBody.insertBefore(dragSrcEl, targetRow.nextSibling);
            } else {
                tableBody.insertBefore(dragSrcEl, targetRow);
            }

            // Instantly update the displayed precedence numbers in the DOM!
            updatePrecedenceNumbers();

            saveNewOrder();
        }
        return false;
    }

    function handleDragEnd(e) {
        const rows = tableBody.querySelectorAll('tr');
        rows.forEach(row => {
            row.classList.remove('drag-over');
            row.classList.remove('dragging');
            // Reset to prevent dragging from non-handle cells after drag finishes
            if (row.classList.contains('draggable-row')) {
                row.setAttribute('draggable', 'false');
            }
        });
    }

    function saveNewOrder() {
        const rows = tableBody.querySelectorAll('tr[data-id]');
        const ids = Array.from(rows).map(row => row.getAttribute('data-id'));

        const csrfToken = document.querySelector('input[name="csrf"]')?.value || '';
        const modelName = window.ADMIN_MODEL_NAME || '';

        showOrderNotification('Updating precedence order...', 'info');

        fetch('/api/v1/admin/models/' + encodeURIComponent(modelName) + '/reorder', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                ids: ids,
                csrf: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showOrderNotification('Precedence order updated successfully!', 'success');
            } else {
                showOrderNotification('Failed to update order: ' + (data.error || 'Unknown error'), 'error');
            }
        })
        .catch(err => {
            showOrderNotification('Error updating order: ' + err.message, 'error');
        });
    }

    let notificationTimeout = null;
    function showOrderNotification(message, type) {
        let el = document.getElementById('order-notification');
        if (!el) {
            el = document.createElement('div');
            el.id = 'order-notification';
            el.className = 'toast-notification';
            document.body.appendChild(el);
        }

        el.className = 'toast-notification ' + type;
        el.textContent = message;

        // Force a reflow to trigger CSS transitions cleanly
        el.getBoundingClientRect();

        el.classList.add('show');

        if (notificationTimeout) clearTimeout(notificationTimeout);
        if (type !== 'info') {
            notificationTimeout = setTimeout(() => {
                el.classList.remove('show');
            }, 3000);
        }
    }

    // Enable HTML5 draggable only when user clicks/mouses down on the drag handle cell
    tableBody.addEventListener('mousedown', (e) => {
        const handle = e.target.closest('.drag-handle-cell');
        if (handle) {
            const row = handle.closest('tr');
            if (row && row.classList.contains('draggable-row')) {
                row.setAttribute('draggable', 'true');
            }
        }
    });

    // Reset draggable state to false when mouse is released
    tableBody.addEventListener('mouseup', (e) => {
        const rows = tableBody.querySelectorAll('tr.draggable-row');
        rows.forEach(row => {
            row.setAttribute('draggable', 'false');
        });
    });

    // Click handler to toggle showing hidden module pills
    tableBody.addEventListener('click', (e) => {
        const moreBtn = e.target.closest('.module-more');
        if (moreBtn) {
            const container = moreBtn.closest('.module-pills-container');
            if (container) {
                const hiddenPills = container.querySelectorAll('.module-pill[data-hidden="true"]');
                const labelEl = moreBtn.querySelector('.module-label');
                const isExpanded = container.classList.toggle('expanded');
                
                hiddenPills.forEach(pill => {
                    if (isExpanded) {
                        pill.classList.remove('is-hidden');
                    } else {
                        pill.classList.add('is-hidden');
                    }
                });
                
                if (labelEl) {
                    if (isExpanded) {
                        labelEl.textContent = 'less';
                    } else {
                        const count = moreBtn.getAttribute('data-count');
                        labelEl.textContent = '+' + count + ' more';
                    }
                }
            }
        }
    });

    tableBody.addEventListener('dragstart', handleDragStart);
    tableBody.addEventListener('dragover', handleDragOver);
    tableBody.addEventListener('dragenter', handleDragEnter);
    tableBody.addEventListener('dragleave', handleDragLeave);
    tableBody.addEventListener('drop', handleDrop);
    tableBody.addEventListener('dragend', handleDragEnd);
});
