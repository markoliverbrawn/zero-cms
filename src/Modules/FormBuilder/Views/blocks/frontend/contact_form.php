<?php
// src/Modules/FormBuilder/Views/blocks/frontend/contact_form.php

use Zero\Support\Security;

$fields = $block['items'] ?? [];
?>
<div class="block block-contact-form">
  <?php if (!empty($block['title'])): ?>
    <h2><?php echo htmlspecialchars($block['title'], ENT_QUOTES, "UTF-8"); ?></h2>
  <?php endif; ?>
  
  <?php if (!empty($block['content'])): ?>
    <div class="block-content">
      <?php echo Security::sanitizeHtml($block['content'] ?? ''); ?>
    </div>
  <?php endif; ?>

  <div class="contact-form-wrapper">
    <div class="contact-success-msg">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"></polyline>
      </svg>
      Thank you! Your message has been successfully sent. We'll be in touch soon.
    </div>

    <?php if (empty($fields)): ?>
      <p class="text-muted" style="font-style: italic;">This form has no fields configured yet. Add fields inside the Page Builder.</p>
    <?php else: ?>
      <form class="ajax-contact-form">
        <input type="hidden" name="block_id" value="<?php echo htmlspecialchars($block['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        
        <!-- Decoy Website URL Honeypot (hides via .website-field-wrapper globally!) -->
        <div class="form-group website-field-wrapper">
          <label>Website URL</label>
          <input type="text" name="website_url" autocomplete="off" tabindex="-1">
        </div>

        <?php foreach ($fields as $fieldObj): ?>
          <?php
          $name = $fieldObj['name'] ?? '';
          $label = $fieldObj['label'] ?? '';
          $type = $fieldObj['type'] ?? 'text';
          $required = ($fieldObj['required'] ?? '0') === '1';
          $optionsStr = $fieldObj['options'] ?? '';
          $options = !empty($optionsStr) ? array_map('trim', explode(',', $optionsStr)) : [];
          ?>
          <div class="form-group">
            <label><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?> <?php echo $required ? '*' : ''; ?></label>
            
            <?php if ($type === 'textarea'): ?>
              <textarea name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $required ? 'required' : ''; ?> placeholder="Enter <?php echo htmlspecialchars(strtolower($label), ENT_QUOTES, 'UTF-8'); ?>..." rows="4"></textarea>
            
            <?php elseif ($type === 'select'): ?>
              <select name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $required ? 'required' : ''; ?>>
                <option value="">-- Select Option --</option>
                <?php foreach ($options as $opt): ?>
                  <option value="<?php echo htmlspecialchars($opt, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($opt, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            
            <?php elseif ($type === 'checkbox'): ?>
              <div class="checkboxes-group">
                <?php foreach ($options as $index => $opt): ?>
                  <label class="checkbox-option-label">
                    <input type="checkbox" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>[]" value="<?php echo htmlspecialchars($opt, ENT_QUOTES, 'UTF-8'); ?>">
                    <span><?php echo htmlspecialchars($opt, ENT_QUOTES, 'UTF-8'); ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            
            <?php elseif ($type === 'radio'): ?>
              <div class="radios-group">
                <?php foreach ($options as $index => $opt): ?>
                  <label class="radio-option-label">
                    <input type="radio" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($opt, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($index === 0 && $required) ? 'required' : ''; ?>>
                    <span><?php echo htmlspecialchars($opt, ENT_QUOTES, 'UTF-8'); ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            
            <?php else: // text, email, tel, number ?>
              <input type="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $required ? 'required' : ''; ?> placeholder="Enter <?php echo htmlspecialchars(strtolower($label), ENT_QUOTES, 'UTF-8'); ?>...">
            <?php endif; ?>
            
            <span class="field-error <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>-error"></span>
          </div>
        <?php endforeach; ?>

        <div class="form-general-error"></div>

        <button type="submit" class="submit-btn">
          Submit Form
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
(function() {
  // Select the newly loaded form to isolate from any other forms on the page
  var scripts = document.getElementsByTagName('script');
  var currentScript = scripts[scripts.length - 1];
  var blockContainer = currentScript.previousElementSibling.closest('.block-contact-form');
  var form = blockContainer.querySelector('.ajax-contact-form');
  var successMsg = blockContainer.querySelector('.contact-success-msg');
  var submitBtn = form.querySelector('.submit-btn');
  var generalError = form.querySelector('.form-general-error');

  if (!form) return;

  // Input border glow on focus
  var inputs = form.querySelectorAll('input, textarea, select');
  inputs.forEach(function(input) {
    input.addEventListener('focus', function() {
      input.style.borderColor = 'var(--neon-cyan, #06b6d4)';
      input.style.boxShadow = '0 0 10px rgba(6, 182, 212, 0.3)';
      input.style.outline = 'none';
    });
    input.addEventListener('blur', function() {
      input.style.borderColor = 'var(--border-color, #222636)';
      input.style.boxShadow = 'none';
    });
  });

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Clear previous errors
    var errors = form.querySelectorAll('.field-error');
    errors.forEach(function(err) {
      err.style.display = 'none';
      err.textContent = '';
    });
    generalError.style.display = 'none';
    generalError.textContent = '';

    // Disable button & show loading state
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';

    // Collect data
    var formData = new FormData(form);
    var payload = {};
    formData.forEach(function(value, key){
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
        successMsg.style.display = 'block';
        successMsg.scrollIntoView({ behavior: 'smooth', block: 'center' });
      } else {
        throw { status: 400, data: data };
      }
    })
    .catch(function(err) {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Submit Form';

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
        generalError.textContent = 'Please correct the validation errors below.';
        generalError.style.display = 'block';
      } else {
        generalError.textContent = data.error || 'A server error occurred. Please try again later.';
        generalError.style.display = 'block';
      }
      generalError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  });
})();
</script>
