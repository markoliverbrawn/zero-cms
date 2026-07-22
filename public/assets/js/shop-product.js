/* public/assets/js/shop-product.js */
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Gallery Thumbnail Switcher
        const mainImg = document.getElementById('main-product-img');
        const thumbBoxes = document.querySelectorAll('.thumb-box');

        if (mainImg && thumbBoxes.length > 0) {
            thumbBoxes.forEach(thumb => {
                thumb.addEventListener('click', () => {
                    // Prune active class from other items
                    thumbBoxes.forEach(item => {
                        item.classList.remove('active');
                        item.style.borderColor = 'var(--border-color)';
                    });
                    // Highlight selected
                    thumb.classList.add('active');
                    thumb.style.borderColor = 'var(--accent-color)';
                    
                    // Swap main source
                    mainImg.src = thumb.getAttribute('data-src');
                });
            });
        }

        // 2. Quantity buttons
        const qtyInput = document.getElementById('qty-input');
        const qtyMinus = document.getElementById('qty-minus');
        const qtyPlus = document.getElementById('qty-plus');

        if (qtyInput && qtyMinus && qtyPlus) {
            qtyMinus.addEventListener('click', () => {
                const val = parseInt(qtyInput.value);
                if (val > 1) qtyInput.value = val - 1;
            });

            qtyPlus.addEventListener('click', () => {
                const val = parseInt(qtyInput.value);
                qtyInput.value = val + 1;
            });
        }

        // 3. Variant selection updates (SKU, Price, Stock warning!)
        const variantRadios = document.querySelectorAll('.variant-chip-radio');
        const variantChips = document.querySelectorAll('.variant-chip');
        const activePriceEl = document.getElementById('active-product-price');
        const activeSkuEl = document.getElementById('meta-variant-sku');
        const activeStockEl = document.getElementById('meta-variant-stock');
        const submitBtn = document.getElementById('add-to-cart-submit');

        if (variantChips.length > 0 && variantRadios.length > 0 && activePriceEl && activeSkuEl && activeStockEl && submitBtn) {
            variantChips.forEach((chip, idx) => {
                const radio = variantRadios[idx];
                chip.addEventListener('click', () => {
                    // Clear selections
                    variantChips.forEach(v => {
                        v.classList.remove('selected');
                        v.style.backgroundColor = 'transparent';
                        v.style.color = 'var(--text-color)';
                        v.style.borderColor = 'var(--border-color)';
                    });
                    // Mark selected
                    chip.classList.add('selected');
                    chip.style.backgroundColor = 'var(--accent-color)';
                    chip.style.color = '#000000';
                    chip.style.borderColor = 'var(--accent-color)';
                    if (radio) radio.checked = true;

                    // Update interactive indicators
                    const price = parseFloat(chip.getAttribute('data-variant-price'));
                    const sku = chip.getAttribute('data-variant-sku');
                    const stock = parseInt(chip.getAttribute('data-variant-stock'));

                    activePriceEl.innerHTML = '$' + price.toFixed(2);
                    activeSkuEl.innerHTML = sku;

                    if (stock === 0) {
                        activeStockEl.innerHTML = 'OUT OF STOCK';
                        activeStockEl.style.color = '#ef4444';
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = 'OUT OF STOCK';
                        submitBtn.style.opacity = '0.5';
                    } else {
                        submitBtn.disabled = false;
                        submitBtn.style.opacity = '1';
                        submitBtn.innerHTML = 'Add To Shop Cart';
                        if (stock < 5) {
                            activeStockEl.innerHTML = 'ONLY ' + stock + ' LEFT IN STOCK';
                            activeStockEl.style.color = '#f59e0b';
                        } else {
                            activeStockEl.innerHTML = 'IN STOCK (' + stock + ' units)';
                            activeStockEl.style.color = 'var(--accent-color)';
                        }
                    }
                });
            });
        }
    });
})();
