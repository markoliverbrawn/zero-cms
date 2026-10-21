// Shared fullscreen lightbox for gallery blocks. Built once per page; next/previous walk only
// the album (closest [data-gallery], else the whole page) the clicked photo belongs to.
document.addEventListener('DOMContentLoaded', () => {
    if (!document.querySelector('.gallery-lightbox-trigger')) {
        return;
    }

    let lightbox = document.getElementById('gallery-lightbox');
    if (!lightbox) {
        lightbox = document.createElement('div');
        lightbox.id = 'gallery-lightbox';
        lightbox.className = 'gallery-lightbox';
        lightbox.setAttribute('role', 'dialog');
        lightbox.setAttribute('aria-modal', 'true');
        lightbox.setAttribute('aria-label', 'Photo viewer');
        lightbox.innerHTML =
            '<button type="button" class="gallery-lightbox-close" aria-label="Close">&times;</button>' +
            '<button type="button" class="gallery-lightbox-nav gallery-lightbox-prev" aria-label="Previous photo">&lsaquo;</button>' +
            '<button type="button" class="gallery-lightbox-nav gallery-lightbox-next" aria-label="Next photo">&rsaquo;</button>' +
            '<div class="gallery-lightbox-content">' +
            '<img class="gallery-lightbox-img" alt="">' +
            '<p class="gallery-lightbox-caption"><span class="gallery-lightbox-title"></span>' +
            '<span class="gallery-lightbox-counter"></span></p>' +
            '</div>';
        document.body.appendChild(lightbox);
    }

    const img = lightbox.querySelector('.gallery-lightbox-img');
    const title = lightbox.querySelector('.gallery-lightbox-title');
    const counter = lightbox.querySelector('.gallery-lightbox-counter');
    const prevBtn = lightbox.querySelector('.gallery-lightbox-prev');
    const nextBtn = lightbox.querySelector('.gallery-lightbox-next');
    const closeBtn = lightbox.querySelector('.gallery-lightbox-close');

    let album = [];
    let index = 0;
    let lastFocus = null;

    const show = (i) => {
        index = (i + album.length) % album.length; // wraps at both ends
        const trigger = album[index];
        img.src = trigger.dataset.src || trigger.currentSrc || trigger.src;
        img.alt = trigger.alt || '';
        title.textContent = trigger.dataset.title || '';
        counter.textContent = album.length > 1 ? `${index + 1} / ${album.length}` : '';
        const single = album.length < 2;
        prevBtn.hidden = single;
        nextBtn.hidden = single;
        // Warm the neighbours so stepping through feels instant.
        [index - 1, index + 1].forEach((n) => {
            const t = album[(n + album.length) % album.length];
            if (t && t.dataset.src) {
                new Image().src = t.dataset.src;
            }
        });
    };

    const open = (trigger) => {
        const scope = trigger.closest('[data-gallery]') || document;
        album = Array.from(scope.querySelectorAll('.gallery-lightbox-trigger'));
        lastFocus = document.activeElement;
        show(album.indexOf(trigger));
        lightbox.classList.add('is-open');
        document.documentElement.classList.add('gallery-lightbox-open');
        closeBtn.focus();
    };

    const close = () => {
        lightbox.classList.remove('is-open');
        document.documentElement.classList.remove('gallery-lightbox-open');
        img.removeAttribute('src');
        if (lastFocus) {
            lastFocus.focus();
        }
    };

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('.gallery-lightbox-trigger');
        if (trigger) {
            e.preventDefault();
            open(trigger);
        }
    });

    prevBtn.addEventListener('click', () => show(index - 1));
    nextBtn.addEventListener('click', () => show(index + 1));
    closeBtn.addEventListener('click', close);
    lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox) {
            close();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (!lightbox.classList.contains('is-open')) {
            return;
        }
        if (e.key === 'Escape') {
            close();
        } else if (e.key === 'ArrowLeft') {
            show(index - 1);
        } else if (e.key === 'ArrowRight') {
            show(index + 1);
        }
    });

    // Swipe left/right on touch screens.
    let touchX = null;
    lightbox.addEventListener('touchstart', (e) => { touchX = e.touches[0].clientX; }, { passive: true });
    lightbox.addEventListener('touchend', (e) => {
        if (touchX === null) {
            return;
        }
        const dx = e.changedTouches[0].clientX - touchX;
        touchX = null;
        if (Math.abs(dx) > 40) {
            show(index + (dx < 0 ? 1 : -1));
        }
    });
});
