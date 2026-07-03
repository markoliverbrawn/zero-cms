(function() {
    /**
     * Globally coordinates sub-pages search filtering via event delegation,
     * resolving nearest block containers dynamically with zero inline scripts.
     */
    document.addEventListener('input', function(e) {
        if (e.target && e.target.classList.contains('sub-pages-search-input')) {
            var searchInput = e.target;
            var blockContainer = searchInput.closest('.block-sub-pages');
            if (!blockContainer) return;
            
            var query = searchInput.value.toLowerCase().trim();
            var cards = blockContainer.querySelectorAll('.sub-pages-card');
            
            cards.forEach(function(card) {
                var title = card.querySelector('.sub-pages-card-title').textContent.toLowerCase();
                var excerpt = card.querySelector('.sub-pages-card-excerpt').textContent.toLowerCase();
                
                if (title.indexOf(query) !== -1 || excerpt.indexOf(query) !== -1) {
                    card.style.display = 'flex'; // Restore card display
                } else {
                    card.style.display = 'none'; // Hide filtered card
                }
            });
        }
    });
})();
