document.addEventListener('DOMContentLoaded', () => {
    const triggers = document.querySelectorAll('.gallery-lightbox-trigger');
    const lightbox = document.getElementById('gallery-lightbox');
    const lightboxContent = document.getElementById('gallery-lightbox-content');
    const lightboxImg = document.getElementById('gallery-lightbox-img');
    const lightboxTitle = document.getElementById('gallery-lightbox-title');
    const closeBtn = document.getElementById('gallery-lightbox-close');

    if (triggers.length > 0 && lightbox) {
        // Toggle zoom effects on hover
        triggers.forEach(img => {
            img.addEventListener('mouseenter', () => img.style.transform = 'scale(1.03)');
            img.addEventListener('mouseleave', () => img.style.transform = 'scale(1)');
        });

        // Click listener to open Lightbox
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('.gallery-lightbox-trigger');
            if (trigger) {
                const src = trigger.getAttribute('data-src');
                const title = trigger.getAttribute('data-title') || '';

                // Populate lightbox sources
                lightboxImg.src = src;
                lightboxTitle.textContent = title;

                // Show Lightbox smoothly
                lightbox.style.display = 'flex';
                // Trigger reflow
                lightbox.offsetHeight;
                
                lightbox.style.opacity = '1';
                lightboxContent.style.transform = 'scale(1)';
            }
        });

        const closeLightbox = () => {
            lightbox.style.opacity = '0';
            lightboxContent.style.transform = 'scale(0.9)';
            setTimeout(() => {
                if (lightbox.style.opacity === '0') {
                    lightbox.style.display = 'none';
                    lightboxImg.src = '';
                }
            }, 300);
        };

        closeBtn.addEventListener('click', closeLightbox);
        lightbox.addEventListener('click', (e) => {
            if (e.target === lightbox) {
                closeLightbox();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && lightbox.style.display === 'flex') {
                closeLightbox();
            }
        });
    }
});
