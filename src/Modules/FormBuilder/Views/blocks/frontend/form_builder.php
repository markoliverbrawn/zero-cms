<?php
// src/Modules/FormBuilder/Views/blocks/frontend/form_builder.php

use Zero\Support\Security;
use Zero\Support\Str;

$fields = $block['items'] ?? [];
?>
<div class="block block-contact-form">
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
        <input type="hidden" name="block_id" value="<?php echo Str::escape($block['id'] ?? ''); ?>">
        
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
            <label><?php echo Str::escape($label); ?> <?php echo $required ? '*' : ''; ?></label>
            
            <?php if ($type === 'textarea'): ?>
              <textarea name="<?php echo Str::escape($name); ?>" <?php echo $required ? 'required' : ''; ?> placeholder="Enter <?php echo Str::escape(strtolower($label)); ?>..." rows="4"></textarea>
            
            <?php elseif ($type === 'select'): ?>
              <select name="<?php echo Str::escape($name); ?>" <?php echo $required ? 'required' : ''; >>
                <option value="">-- Select Option --</option>
                <?php foreach ($options as $opt): ?>
                  <option value="<?php echo Str::escape($opt); ?>"><?php echo Str::escape($opt); ?></option>
                <?php endforeach; ?>
              </select>
            
            <?php elseif ($type === 'checkbox'): ?>
              <div class="checkboxes-group">
                <?php foreach ($options as $index => $opt): ?>
                  <label class="checkbox-option-label">
                    <input type="checkbox" name="<?php echo Str::escape($name); ?>[]" value="<?php echo Str::escape($opt); ?>">
                    <span><?php echo Str::escape($opt); ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            
            <?php elseif ($type === 'radio'): ?>
              <div class="radios-group">
                <?php foreach ($options as $index => $opt): ?>
                  <label class="radio-option-label">
                    <input type="radio" name="<?php echo Str::escape($name); ?>" value="<?php echo Str::escape($opt); ?>" <?php echo ($index === 0 && $required) ? 'required' : ''; ?>>
                    <span><?php echo Str::escape($opt); ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            
            <?php else: // text, email, tel, number ?>
              <input type="<?php echo Str::escape($type); ?>" name="<?php echo Str::escape($name); ?>" <?php echo $required ? 'required' : ''; ?> placeholder="Enter <?php echo Str::escape(strtolower($label)); ?>...">
            <?php endif; ?>
            
            <span class="field-error <?php echo Str::escape($name); ?>-error"></span>
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
