document.addEventListener('DOMContentLoaded', () => {
    const preferencesForm = document.getElementById('preferences-form');
    if (!preferencesForm) return;

    const themeRadios = document.querySelectorAll('input[name="theme"]');
    const presetSelect = document.getElementById('theme_preset');

    const updateLivePreview = () => {
        let activeTheme = 'light';
        themeRadios.forEach(radio => {
            if (radio.checked) {
                activeTheme = radio.value;
            }
        });

        const activePreset = presetSelect.value;

        // Apply immediately to the live document!
        document.body.setAttribute('data-theme', activeTheme);
        document.body.setAttribute('data-preset', activePreset);
        
        // Save temporary local storage so pages match while navigating
        localStorage.setItem('theme', activeTheme);
        localStorage.setItem('theme_preset', activePreset);
    };

    // Listen to theme radio mode changes
    themeRadios.forEach(radio => {
        radio.addEventListener('change', updateLivePreview);
    });

    // Listen to preset select changes
    if (presetSelect) {
        presetSelect.addEventListener('change', updateLivePreview);
    }

    // Modern AJAX Form Submission & Springy Toast Notifications (matching other edit forms)
    preferencesForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const submitBtn = preferencesForm.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        const widgets = Array.from(preferencesForm.querySelectorAll('.dashboard-widget-toggle-checkbox:checked')).map(el => el.value);
        const themeRadio = preferencesForm.querySelector('input[name="theme"]:checked');
        const theme = themeRadio ? themeRadio.value : 'light';
        const themePreset = preferencesForm.querySelector('select[name="theme_preset"]').value;
        const language = preferencesForm.querySelector('select[name="language"]').value;
        const timezone = preferencesForm.querySelector('select[name="timezone"]').value;
        const perPage = preferencesForm.querySelector('select[name="per_page"]').value;
        const csrfToken = preferencesForm.querySelector('input[name="csrf"]')?.value || '';

        // Create or select the global bottom-left success toast
        let toast = document.getElementById('ajax-save-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'ajax-save-toast';
            toast.className = 'toast-notification';
            document.body.appendChild(toast);
        }

        // Patch request to save preferences
        fetch('/api/v1/admin/preferences', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                theme: theme,
                theme_preset: themePreset,
                widgets: widgets,
                language: language,
                timezone: timezone,
                per_page: perPage,
                csrf: csrfToken
            })
        })
        .then(res => {
            if (submitBtn) submitBtn.disabled = false;
            if (res.ok) {
                // Update layout of checkable blocks dynamically via classes
                preferencesForm.querySelectorAll('.dashboard-widget-toggle').forEach(card => {
                    const input = card.querySelector('.dashboard-widget-toggle-checkbox');
                    if (input) {
                        if (input.checked) {
                            card.classList.add('is-checked');
                        } else {
                            card.classList.remove('is-checked');
                        }
                    }
                });

                // Trigger toast animation matching other edit forms
                // Fetch dynamic success message back from response headers or re-trigger location reload to apply fresh lang!
                // To dynamically apply language changes instantly to all sidebars and elements, we can smoothly trigger page reload!
                toast.textContent = 'Changes saved successfully!';
                toast.className = 'toast-notification success';
                
                // Force reflow to trigger slide-up transition
                toast.offsetHeight;

                toast.classList.add('show');

                // Smoothly reload page after 1 second to apply full multi-language i18n translates!
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                alert('Failed to save preferences.');
            }
        })
        .catch(err => {
            if (submitBtn) submitBtn.disabled = false;
            console.error(err);
            alert('A network error occurred while saving.');
        });
    });

    // Dynamic class update on manual widget checkbox change (real-time feedback)
    preferencesForm.querySelectorAll('.dashboard-widget-toggle-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', () => {
            const card = checkbox.closest('.dashboard-widget-toggle');
            if (card) {
                if (checkbox.checked) {
                    card.classList.add('is-checked');
                } else {
                    card.classList.remove('is-checked');
                }
            }
        });
    });
});
