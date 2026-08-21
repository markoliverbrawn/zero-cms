// assets/js/blocks/masonry.js
// Clean, interactive, script-separated masonry lightbox dynamic handlers

document.addEventListener('DOMContentLoaded', () => {
    const triggers = document.querySelectorAll('.masonry-trigger-img');
    const lightbox = document.getElementById('masonry-lightbox');
    const lightboxContent = document.getElementById('masonry-lightbox-content');
    const lightboxImg = document.getElementById('masonry-lightbox-img');
    const lightboxTitle = document.getElementById('masonry-lightbox-title');
    const closeBtn = document.getElementById('masonry-lightbox-close');

    if (triggers.length > 0 && lightbox) {
        triggers.forEach(img => {
            // Click listener to open Lightbox
            img.addEventListener('click', () => {
                // Prefer the large rendition published on data-src, falling back to whatever is
                // already displayed. The tile itself is a small scaled variant, so opening the
                // lightbox on src alone would show a blown-up thumbnail.
                const src = img.getAttribute('data-src') || img.getAttribute('src');
                const title = img.parentElement.querySelector('h4') ? img.parentElement.querySelector('h4').textContent : '';

                // Populate lightbox sources
                lightboxImg.src = src;
                lightboxTitle.textContent = title;

                // Open overlay by adding the 'show' class
                lightbox.classList.add('show');
            });
        });

        const closeLightbox = () => {
            lightbox.classList.remove('show');
            setTimeout(() => {
                lightboxImg.src = '';
            }, 300);
        };

        // Close listeners (close button, backdrop clicks, ESC key)
        closeBtn.addEventListener('click', closeLightbox);
        lightbox.addEventListener('click', (e) => {
            if (e.target === lightbox) {
                closeLightbox();
            }
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && lightbox.classList.contains('show')) {
                closeLightbox();
            }
        });
    }
});
