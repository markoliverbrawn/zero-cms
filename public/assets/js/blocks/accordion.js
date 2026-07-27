(function() {
    // Unified, Bulletproof Document-level Event Delegation!
    // Instantly handles accordion toggles on any public or preview page, completely immune to mounting/timing race conditions.
    document.addEventListener('click', function(e) {
        const trigger = e.target.closest('.accordion-trigger');
        if (!trigger) return;

        e.preventDefault();

        const item = trigger.closest('.accordion-item');
        if (!item) return;

        const panel = item.querySelector('.accordion-panel') || item.querySelector('.accordion-content');
        const title = item.querySelector('.accordion-title');
        const lineV = item.querySelector('.accordion-line-v');

        if (!panel) return;

        const isOpen = item.classList.contains('active');

        // Collapse all other items inside the same accordion list (exclusive single toggle behavior)
        const activeItems = item.parentNode.querySelectorAll('.accordion-item.active');
        activeItems.forEach(activeItem => {
            if (activeItem !== item) {
                activeItem.classList.remove('active');
                activeItem.querySelector('.accordion-panel').style.maxHeight = '0px';
                activeItem.querySelector('.accordion-title').style.color = '#ffffff';
                const activeLineV = activeItem.querySelector('.accordion-line-v');
                if (activeLineV) activeLineV.style.transform = 'rotate(0deg)';
            }
        });

        // Toggle active state
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
})();
