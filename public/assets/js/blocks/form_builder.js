/* public/assets/js/blocks/form_builder.js */
(function() {
  // Input focus/blur styles via bubbling focusin/focusout events (Event Delegation)
  document.addEventListener('focusin', function(e) {
    var input = e.target;
    if (input.matches('input, textarea, select') && input.closest('.ajax-contact-form')) {
      input.style.borderColor = 'var(--neon-cyan, #06b6d4)';
      input.style.boxShadow = '0 0 10px rgba(6, 182, 212, 0.3)';
      input.style.outline = 'none';
    }
  });

  document.addEventListener('focusout', function(e) {
    var input = e.target;
    if (input.matches('input, textarea, select') && input.closest('.ajax-contact-form')) {
      input.style.borderColor = 'var(--border-color, #222636)';
      input.style.boxShadow = 'none';
    }
  });

  // Handle Form Submission via event delegation
  document.addEventListener('submit', function(e) {
    var form = e.target.closest('.ajax-contact-form');
    if (!form) return;

    e.preventDefault();

    var blockContainer = form.closest('.block-contact-form');
    var successMsg = blockContainer ? blockContainer.querySelector('.contact-success-msg') : null;
    var submitBtn = form.querySelector('.submit-btn');
    var generalError = form.querySelector('.form-general-error');

    // Clear previous errors
    var errors = form.querySelectorAll('.field-error');
    errors.forEach(function(err) {
      err.style.display = 'none';
      err.textContent = '';
    });
    if (generalError) {
      generalError.style.display = 'none';
      generalError.textContent = '';
    }

    // Disable button & show loading state
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting...';
    }

    // Collect data
    var formData = new FormData(form);
    var payload = {};
    formData.forEach(function(value, key) {
      if (key.endsWith('[]')) {
        var realKey = key.slice(0, -2);
        if (!payload[realKey]) payload[realKey] = [];
        payload[realKey].push(value);
      } else {
        payload[key] = value;
      }
    });

    // Send AJAX request
    fetch('/api/v1/contact/submit', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    })
    .then(function(res) {
      return res.json().then(function(data) {
        if (!res.ok) {
          throw { status: res.status, data: data };
        }
        return data;
      });
    })
    .then(function(data) {
      if (data.success) {
        form.style.display = 'none';
        if (successMsg) {
          successMsg.style.display = 'block';
          successMsg.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      } else {
        throw { status: 400, data: data };
      }
    })
    .catch(function(err) {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Form';
      }

      var data = err.data || {};
      if (data.errors) {
        // Render field specific validation errors
        for (var field in data.errors) {
          var errorSpan = form.querySelector('.' + field + '-error');
          if (errorSpan) {
            errorSpan.textContent = data.errors[field][0];
            errorSpan.style.display = 'block';
          }
        }
        if (generalError) {
          generalError.textContent = 'Please correct the validation errors below.';
          generalError.style.display = 'block';
        }
      } else {
        if (generalError) {
          generalError.textContent = data.error || 'A server error occurred. Please try again later.';
          generalError.style.display = 'block';
        }
      }
      if (generalError) {
        generalError.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
  });
})();
