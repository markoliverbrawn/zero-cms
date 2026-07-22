/* public/assets/js/blocks/demo_creator.js */
(function() {
  document.addEventListener('submit', function(e) {
    var form = e.target.closest('.demo-creator-form');
    if (!form) return;

    e.preventDefault();

    var blockContainer = form.closest('.block-demo-creator');
    var successCard = blockContainer ? blockContainer.querySelector('.demo-success-card') : null;
    var submitBtn = form.querySelector('.submit-btn');
    var generalError = form.querySelector('.form-general-error');
    var progressIndicator = form.querySelector('.demo-progress-indicator');
    var progressText = form.querySelector('.demo-progress-text');

    if (generalError) {
      generalError.style.display = 'none';
      generalError.textContent = '';
    }

    // Disable button & show loading state
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.style.opacity = '0.5';
      submitBtn.textContent = 'Generating Sandbox...';
    }

    if (progressIndicator) {
      progressIndicator.style.display = 'block';
    }

    var stages = [
      'Initializing isolated multi-tenant domain...',
      'Copying database schemas & blueprints...',
      'Generating administrator credentials...',
      'Compiling system config handshake...',
      'Finalizing sandbox setup...'
    ];

    var currentStage = 0;
    var intervalId = setInterval(function() {
      if (progressText && currentStage < stages.length) {
        progressText.textContent = stages[currentStage];
        currentStage++;
      }
    }, 1200);

    // Collect data
    var formData = new FormData(form);

    fetch('/api/v1/demo/create', {
      method: 'POST',
      body: formData
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
      clearInterval(intervalId);
      if (data.success) {
        form.style.display = 'none';
        if (successCard) {
          // Fill login details
          var domainLink = successCard.querySelector('.success-domain-link');
          var adminLink = successCard.querySelector('.success-admin-link');
          var passCode = successCard.querySelector('.success-password-code');
          var userCode = successCard.querySelector('.success-username-code');

          if (domainLink) {
            domainLink.href = 'http://' + data.domain;
            domainLink.textContent = 'http://' + data.domain;
          }
          if (adminLink) {
            adminLink.href = 'http://' + data.domain + '/admin/dashboard';
          }
          if (passCode) {
            passCode.textContent = data.password;
          }
          if (userCode) {
            userCode.textContent = form.querySelector('#demo_email').value;
          }

          successCard.style.display = 'block';
          successCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      } else {
        throw { status: 400, data: data };
      }
    })
    .catch(function(err) {
      clearInterval(intervalId);
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
        submitBtn.textContent = 'Assemble Sandbox Workspace';
      }
      if (progressIndicator) {
        progressIndicator.style.display = 'none';
      }

      var data = err.data || {};
      if (generalError) {
        generalError.textContent = data.error || 'A server error occurred during sandbox creation. Please try again.';
        generalError.style.display = 'block';
        generalError.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
  });
})();
