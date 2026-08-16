<?php
// src/Modules/FormBuilder/Views/blocks/frontend/form_builder.php

use Zero\Core\App;
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

          // FormBuilder's own schema vocabulary uses 'checkbox' for a GROUP of checkboxes and
          // 'radio' for a group of radios -- translate locally to the FormField registry's
          // distinct group types rather than have the shared registry special-case this caller.
          $resolvedType = $type;
          if ($type === 'checkbox') {
              $resolvedType = 'checkbox_group';
          } elseif ($type === 'radio') {
              $resolvedType = 'radio_group';
          }

          $fieldOptions = $options;
          if ($resolvedType === 'select') {
              // Reshape the plain option-string list into an associative value=>label array
              // (value === label, as this schema has no separate value/label distinction) with a
              // leading blank placeholder entry, so an intentionally-unselected optional dropdown
              // still casts through as a valid, legitimate submission rather than being rejected
              // by Select's options allow-list.
              $fieldOptions = ['' => '-- Select Option --'] + \array_combine($options, $options);
          }

          $attributes = [];
          if (!\in_array($resolvedType, ['select', 'checkbox_group', 'radio_group'], true)) {
              $attributes['placeholder'] = 'Enter ' . \strtolower($label) . '...';
          }
          if ($resolvedType === 'textarea') {
              $attributes['rows'] = 4;
          }
          ?>
          <div class="form-group">
            <label><?php echo Str::escape($label); ?> <?php echo $required ? '*' : ''; ?></label>
            <?php echo App::makeFormField($resolvedType, $name, [
                'label' => $label,
                'required' => $required,
                'options' => $fieldOptions,
                'attributes' => $attributes,
                'showLabel' => false,
                // Field names here are arbitrary, site-admin-chosen strings (not a fixed,
                // developer-controlled vocabulary) -- never guess an i18n help-text key from one,
                // or an unrelated key from a completely different context (e.g. "email_help",
                // written for the admin User model) could leak into a public contact form.
                'guessHelperTextKey' => false,
            ])->render(); ?>
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
