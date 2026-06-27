document.addEventListener('DOMContentLoaded', () => {
    const grid = document.getElementById('dashboard-widgets-grid');
    if (!grid) return;

    let draggedItem = null;

    grid.querySelectorAll('.draggable-widget').forEach(item => {
        // Drag Start
        item.addEventListener('dragstart', (e) => {
            draggedItem = item;
            item.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        // Drag End
        item.addEventListener('dragend', () => {
            draggedItem = null;
            item.classList.remove('dragging');
            
            // Save the new layout order instantly via AJAX!
            saveDashboardLayout();
        });

        // Drag Over
        item.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            
            const target = e.target.closest('.draggable-widget');
            if (target && target !== draggedItem) {
                // Determine bounding box to insert before or after
                const rect = target.getBoundingClientRect();
                const next = (e.clientY - rect.top) > (rect.height / 2);
                grid.insertBefore(draggedItem, next ? target.nextSibling : target);
            }
        });
    });

    const saveDashboardLayout = () => {
        const cards = grid.querySelectorAll('.draggable-widget');
        const layout = Array.from(cards).map(card => card.getAttribute('data-widget'));
        
        const csrfToken = window.ADMIN_CSRF_TOKEN || '';

        // Save layout asynchronously to RESTful Admin preferences API
        fetch('/api/v1/admin/preferences', {
            method: 'POST', // or PATCH
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                action: 'save_layout',
                layout: layout,
                csrf: csrfToken
            }),
            credentials: 'same-origin'
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Show a clean toast notification
                const toast = document.createElement('div');
                toast.className = 'dashboard-toast';
                toast.textContent = 'Dashboard layout rearranged successfully!';
                document.body.appendChild(toast);
                
                // Trigger transition
                setTimeout(() => { toast.classList.add('show'); }, 10);
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 350);
                }, 2500);
            }
        })
        .catch(err => {
            console.error('Error saving dashboard layout:', err);
        });
    };
});
