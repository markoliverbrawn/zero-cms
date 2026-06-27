document.addEventListener('DOMContentLoaded', () => {
    // 1. Generic Single Image Picker Preview / Update Logic
    document.querySelectorAll('.image-picker-container').forEach((container) => {
        const fieldName = container.getAttribute('data-field');
        const pickerBtn = container.querySelector('.media-picker-trigger-btn');
        const inputEl = container.querySelector('.image-picker-input');
        const previewBox = container.querySelector('.image-picker-preview-box');
        const previewImg = container.querySelector('.image-picker-preview');

        if (pickerBtn && inputEl) {
            pickerBtn.addEventListener('click', () => {
                // Dynamically route uploads to 'featured-images' folder if editing blog posts featured image
                if (fieldName === 'featured_image') {
                    window.pickerActiveFolder = 'featured-images';
                } else {
                    window.pickerActiveFolder = '';
                }

                window.openImagePicker((file) => {
                    inputEl.value = file.id; // Store the media ID in DB
                    if (previewImg && previewBox) {
                        previewImg.src = file.path; // Render path preview
                        previewBox.style.display = 'flex';
                    }
                });
            });

            // Handle manual input updates
            inputEl.addEventListener('input', () => {
                if (inputEl.value.trim() !== '') {
                    if (inputEl.value.length !== 36) {
                        previewImg.src = inputEl.value;
                        previewBox.style.display = 'flex';
                    }
                } else {
                    previewBox.style.display = 'none';
                }
            });
        }
    });

    // 2. Multi-Image Gallery Picker / Update Logic
    const galleryPickerBtn = document.getElementById('product-gallery-picker-btn');
    const galleryInputEl = document.getElementById('product-media-ids-input');
    const galleryGridEl = document.getElementById('product-gallery-preview-grid');
    
    const updateInputVal = () => {
        if (!galleryGridEl || !galleryInputEl) return;
        const cards = galleryGridEl.querySelectorAll('.gallery-thumb-card');
        const ids = Array.from(cards).map(card => card.getAttribute('data-id'));
        galleryInputEl.value = ids.join(',');
    };
    
    const attachRemoveEvent = (card) => {
        const btn = card.querySelector('.gallery-thumb-remove-btn');
        if (btn) {
            btn.addEventListener('click', () => {
                card.remove();
                updateInputVal();
            });
        }
    };
    
    if (galleryGridEl) {
        galleryGridEl.querySelectorAll('.gallery-thumb-card').forEach(attachRemoveEvent);
    }
    
    if (galleryPickerBtn && galleryInputEl && galleryGridEl) {
        galleryPickerBtn.addEventListener('click', () => {
            window.openImagePicker((file) => {
                // Prevent duplicate selections
                if (galleryGridEl.querySelector(`.gallery-thumb-card[data-id="${file.id}"]`)) {
                    return; 
                }
                
                // Create new thumbnail card
                const card = document.createElement('div');
                card.className = 'gallery-thumb-card';
                card.setAttribute('data-id', file.id);
                card.innerHTML = `
                    <img src="${file.path}" />
                    <button type="button" class="gallery-thumb-remove-btn" title="Remove image">&times;</button>
                `;
                
                galleryGridEl.appendChild(card);
                attachRemoveEvent(card);
                updateInputVal();
            });
        });
    }

    // 3. Gorgeous Tabbed Navigation Bar
    var tabButtons = document.querySelectorAll('.form-tab-btn');
    var tabContents = document.querySelectorAll('.form-tab-content');
    
    tabButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var targetTab = btn.getAttribute('data-tab');
            
            // Deactivate all buttons
            tabButtons.forEach(function(b) {
                b.classList.remove('active');
            });
            
            // Activate clicked button
            btn.classList.add('active');
            
            // Switch visible content
            tabContents.forEach(function(content) {
                if (content.id === 'tab-content-' + targetTab) {
                    content.style.display = 'flex';
                } else {
                    content.style.display = 'none';
                }
            });
        });
    });

    // 4. Send Welcome Email AJAX action (only for Edit User form)
    const sendWelcomeBtn = document.getElementById('send-welcome-email-btn');
    if (sendWelcomeBtn) {
        const emailInput = document.querySelector('input[name="email"]');
        if (emailInput) {
            const checkEmailValue = () => {
                const emailValue = emailInput.value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (emailRegex.test(emailValue)) {
                    sendWelcomeBtn.removeAttribute('disabled');
                } else {
                    sendWelcomeBtn.setAttribute('disabled', 'true');
                }
            };
            emailInput.addEventListener('input', checkEmailValue);
            emailInput.addEventListener('change', checkEmailValue);
            checkEmailValue();
        }

        sendWelcomeBtn.addEventListener('click', () => {
            const userId = sendWelcomeBtn.getAttribute('data-id');
            if (!userId) return;

            // Disable button during transit and show loading state
            sendWelcomeBtn.disabled = true;
            const originalText = sendWelcomeBtn.textContent;
            sendWelcomeBtn.textContent = 'Sending...';

            const csrfEl = document.querySelector('input[name="csrf"]');
            const csrfToken = csrfEl ? csrfEl.value : '';

            fetch('/api/v1/user/send-welcome', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ id: userId, csrf: csrfToken })
            })
            .then(response => response.json())
            .then(data => {
                // Recheck the current state of email input to see if it should stay enabled/disabled
                const currentEmailInput = document.querySelector('input[name="email"]');
                const emailValue = currentEmailInput ? currentEmailInput.value.trim() : '';
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                sendWelcomeBtn.disabled = !emailRegex.test(emailValue);
                sendWelcomeBtn.textContent = originalText;

                // Show toast notification
                if (data && data.success) {
                    showAdminToast(data.message || 'Welcome email sent successfully!', 'success');
                } else {
                    showAdminToast(data.message || 'Failed to send welcome email.', 'error');
                }
            })
            .catch(error => {
                const currentEmailInput = document.querySelector('input[name="email"]');
                const emailValue = currentEmailInput ? currentEmailInput.value.trim() : '';
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                sendWelcomeBtn.disabled = !emailRegex.test(emailValue);
                sendWelcomeBtn.textContent = originalText;
                showAdminToast('Network error occurred while sending email.', 'error');
            });
        });
    }

    // Helper toast notification trigger
    function showAdminToast(message, type = 'success') {
        let toast = document.getElementById('ajax-save-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'ajax-save-toast';
            toast.className = 'toast-notification';
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.className = 'toast-notification ' + type;
        
        // Force reflow
        toast.offsetHeight;

        toast.classList.add('show');
        
        setTimeout(function() {
            toast.classList.remove('show');
        }, 4000);
    }
});
