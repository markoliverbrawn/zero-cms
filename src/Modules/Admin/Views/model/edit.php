<?php
// src/Modules/Admin/Views/model/edit.php

use Zero\Core\App;
use Zero\Models\User;
use Zero\Services\AiService;
use Zero\Support\I18n;
use Zero\Support\Str;

$modelClass = App::getRegisteredModels()[$modelName] ?? null;
$hasBlockBuilderField = $modelClass && method_exists($modelClass, 'getBlockBuilderField');
$blockBuilderField = $hasBlockBuilderField ? $modelClass::getBlockBuilderField() : null;
$usesBlockBuilder = $hasBlockBuilderField;
if ($usesBlockBuilder && $record && method_exists($record, 'usesBlockBuilder')) {
    $usesBlockBuilder = $record->usesBlockBuilder();
}

$hasSidebar = false;
$hasAdvanced = false;

// Scan schema configurations to detect layout structure and active panel tabs
foreach ($config as $field => $fieldConfig) {
    if ($fieldConfig['editable'] ?? false) {
        $section = $fieldConfig['section'] ?? 'main';
        if ($section === 'side') {
            $hasSidebar = true;
        }
        if ($field === 'controller' || $field === 'view' || $field === 'omit_title' || $field === 'precedence' || $field === 'show_in_nav') {
            $hasAdvanced = true;
        }
    }
}

// Extract and organize active fields relative to their defined layout segments
$generalMainFields = [];
$generalSideFields = [];
$advancedFields = [];
foreach ($config as $field => $fieldConfig) {
    if ($fieldConfig['editable'] ?? false) {
        // Skip block builder fields entirely from the edit form if disabled dynamically
        if ($field === $blockBuilderField && !$usesBlockBuilder) {
            continue;
        }
        // Move controller, view, omit_title, precedence, and show_in_nav to the Settings (Advanced) tab panel
        if ($field === 'controller' || $field === 'view' || $field === 'omit_title' || $field === 'precedence' || $field === 'show_in_nav') {
            $advancedFields[$field] = $fieldConfig;
        } else {
            $section = $fieldConfig['section'] ?? 'main';
            if ($section === 'side') {
                $generalSideFields[$field] = $fieldConfig;
            } else {
                $generalMainFields[$field] = $fieldConfig;
            }
        }
    }
}

// Decoupled Form Field Pre-Renderer Lambda Function to preserve strict OOP DRY constraints.
// Delegates the actual markup generation to the FormField component system (Zero\Support\Forms)
// -- this closure's only remaining job is resolving the handful of per-record decisions a static
// getConfig() schema can't express by itself (which concrete record is being edited, whether it
// uses the block builder, and the pages.parent_path circular-reference guard).
$renderField = function ($field, $fieldConfig) use ($record, $modelName, $csrf, $usesBlockBuilder, $blockBuilderField) {
    $type = $fieldConfig['type'];
    $config = $fieldConfig;
    $config['value'] = $record->{$field} ?? '';
    $config['record'] = $record;

    if ($field === 'summary' && AiService::isAvailable()) {
        ?>
        <div class="form-label-row">
            <label><?php echo Str::escape($fieldConfig['label'] ?? ''); ?></label>
            <button type="button" class="btn-ai-generate-icon" id="btn-ai-generate-summary" title="Auto Generate Summary with AI">
                <?php echo App::svg('ai'); ?>
            </button>
        </div>
        <?php
        $config['showLabel'] = false;
    }

    if ($usesBlockBuilder && $field === $blockBuilderField) {
        // Page-builder block editor -- only resolvable per-record (usesBlockBuilder() is a
        // runtime method call), so it can never be a static getConfig() type value.
        $type = 'block_builder';
        $config['modelName'] = $modelName;
        $config['csrf'] = $csrf;
    } elseif ($type === 'image') {
        $config['mediaId'] = $record->{$field . '_id'} ?? '';
    } elseif ($type === 'select' && $modelName === 'pages' && $field === 'parent_path' && !empty($record->slug)) {
        // Circular Loop Protection: Prevent nesting a page under itself or under any of its own
        // children -- depends on the specific record being edited, so it stays a per-record
        // pre-filter rather than a static schema value.
        $options = $config['options'] ?? [];
        $isSequential = (\array_keys($options) === \range(0, \count($options) - 1));
        $filteredOptions = [];
        foreach ($options as $key => $label) {
            $optionVal = $isSequential ? $label : $key;
            if ($optionVal === $record->slug || \strpos((string)$optionVal, $record->slug . '/') === 0) {
                continue;
            }
            $filteredOptions[$key] = $label;
        }
        $config['options'] = $filteredOptions;
    }

    echo App::makeFormField($type, $field, $config)->render();
};
?>

<div class="editrecord">
  <?php if ($modelName === 'submissions'): ?>
    <h2>View Dynamic Form Submission</h2>
    
    <div class="submission-detail-container">
      <div class="submission-header">
        <h3 class="submission-title"><?php echo Str::escape($record->form_title ?? 'Dynamic Form'); ?></h3>
        <span class="submission-date"><?php echo Str::escape($record->created_at ?? ''); ?></span>
      </div>

      <div class="submission-meta-grid">
        <div class="submission-meta-item">
          <span class="submission-meta-label">Source Page</span>
          <span class="submission-meta-value"><?php echo Str::escape($record->source_page ?? 'N/A'); ?></span>
        </div>
        <div class="submission-meta-item">
          <span class="submission-meta-label">Sender Name</span>
          <span class="submission-meta-value"><?php echo Str::escape($record->name ?? 'Anonymous'); ?></span>
        </div>
        <div class="submission-meta-item">
          <span class="submission-meta-label">Sender Email</span>
          <span class="submission-meta-value"><?php echo Str::escape($record->email ?? 'N/A'); ?></span>
        </div>
        <div class="submission-meta-item">
          <span class="submission-meta-label">Sender Phone</span>
          <span class="submission-meta-value"><?php echo Str::escape($record->phone ?? 'N/A'); ?></span>
        </div>
      </div>

      <div class="submission-fields-section">
        <h4 class="submission-fields-title">Submitted Fields Data</h4>
        <div class="submission-fields-list">
          <?php foreach ($record->formatted_fields as $label => $val): ?>
            <div class="submission-field-card">
              <div class="submission-field-label"><?php echo Str::escape($label); ?></div>
              <div class="submission-field-value"><?php echo Str::escape($val); ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <p><a href="/admin/list/submissions">Back to submissions</a></p>
  <?php elseif ($modelName === 'audit_logs'): ?>
    <?php
    $actionLower = strtolower($record->action ?? '');
    $severityClass = 'severity-info'; // default

    if (
        str_contains($actionLower, 'fail') || 
        str_contains($actionLower, 'error') || 
        str_contains($actionLower, 'vulnerability') || 
        str_contains($actionLower, 'unauthorized') || 
        str_contains($actionLower, 'blocked') || 
        str_contains($actionLower, 'deny') || 
        str_contains($actionLower, 'denied')
    ) {
        $severityClass = 'severity-danger';
    } elseif (
        str_contains($actionLower, 'success') || 
        str_contains($actionLower, 'pass') || 
        str_contains($actionLower, 'approve')
    ) {
        $severityClass = 'severity-success';
    } elseif (
        str_contains($actionLower, 'warning') || 
        str_contains($actionLower, 'purge') || 
        str_contains($actionLower, 'delete')
    ) {
        $severityClass = 'severity-warning';
    }
    ?>
    <h2>View Security Log Entry</h2>
    
    <div class="audit-log-detail-container <?php echo $severityClass; ?>">
      <div class="audit-log-header">
        <div class="audit-log-title-wrapper">
          <span class="icon-svg"><?php echo App::svg('shield'); ?></span>
          <h3 class="audit-log-title"><?php echo Str::escape($record->action ?? 'Security Event'); ?></h3>
        </div>
        <span class="audit-log-date"><?php echo Str::escape($record->created_at ? I18n::localizeDateTime($record->created_at) : ''); ?></span>
      </div>

      <div class="audit-log-meta-grid">
        <div class="audit-log-meta-item">
          <span class="audit-log-meta-label">Actor Username</span>
          <span class="audit-log-meta-value">
            <?php 
              $actor = User::find($record->user_id);
              echo Str::escape($actor ? $actor->username : 'Guest / System'); 
            ?>
          </span>
        </div>
        <div class="audit-log-meta-item">
          <span class="audit-log-meta-label">Target Object Type</span>
          <span class="audit-log-meta-value"><?php echo Str::escape($record->object_type ?? 'N/A'); ?></span>
        </div>
        <div class="audit-log-meta-item">
          <span class="audit-log-meta-label">Target Record ID</span>
          <span class="audit-log-meta-value"><?php echo Str::escape($record->object_id ?? 'N/A'); ?></span>
        </div>
      </div>

      <div class="audit-log-fields-section">
        <h4 class="audit-log-fields-title">Diagnostic Metadata Details</h4>
        <div class="audit-log-field-card">
          <pre><code><?php 
            $metaData = json_decode($record->meta ?? '{}', true);
            echo Str::escape(json_encode($metaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
          ?></code></pre>
        </div>
      </div>
    </div>

    <p><a href="/admin/list/audit_logs" class="btn-cancel">➔ Back to Security Logs</a></p>
  <?php else: ?>
    <?php
    $titleKey = strtolower($modelName ?? '');
    $translatedTitle = I18n::t($titleKey);
    $displayName = ($translatedTitle !== $titleKey) ? $translatedTitle : ucwords(str_replace('_', ' ', $modelName ?? ''));
    $isEdit = !empty($record->id);
    ?>
    <div class="model-edit-header">
      <h2><?php if ($isEdit): ?>Edit<?php else: ?>New<?php endif; ?> <?php echo Str::escape($displayName); ?></h2>
      <?php if ($isEdit && method_exists($record, 'getFrontendUrl') && $record->status === 'published'): ?>
          <a href="<?php echo Str::escape($record->getFrontendUrl()); ?>" target="_blank" class="btn-cancel btn-view-frontend">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                  <polyline points="15 3 21 3 21 9"></polyline>
                  <line x1="10" y1="14" x2="21" y2="3"></line>
              </svg>
              View on Frontend
          </a>
      <?php endif; ?>
    </div>
    
    <form method="post" id="<?php echo Str::escape($modelName ?? ''); ?>-form" action="">
      <input type="hidden" name="csrf" value="<?php echo Str::escape($csrf ?? ''); ?>">
      <?php if ($record->id ?? false): ?><input type="hidden" name="id" value="<?php echo Str::escape($record->id ?? ''); ?>"><?php endif; ?>

      <!-- Gorgeous Tabbed Navigation Bar -->
      <?php if ($hasAdvanced): ?>
        <div class="form-tabs-bar">
            <button type="button" class="form-tab-btn active" data-tab="general">General</button>
            <button type="button" class="form-tab-btn" data-tab="advanced">Settings</button>
        </div>
      <?php endif; ?>

      <!-- General Tab Panel Content -->
      <div id="tab-content-general" class="form-tab-content active">
          <?php if ($hasSidebar): ?>
              <div class="form-main-column">
                  <?php foreach ($generalMainFields as $field => $fieldConfig): ?>
                      <div class="form-field-wrapper <?php echo 'field-width-' . ($fieldConfig['width'] ?? 'full'); ?>">
                          <?php $renderField($field, $fieldConfig); ?>
                      </div>
                  <?php endforeach; ?>
              </div>
              <div class="form-sidebar-column">
                  <?php foreach ($generalSideFields as $field => $fieldConfig): ?>
                      <div class="form-field-wrapper <?php echo 'field-width-' . ($fieldConfig['width'] ?? 'full'); ?>">
                          <?php $renderField($field, $fieldConfig); ?>
                      </div>
                  <?php endforeach; ?>
              </div>
          <?php else: ?>
              <?php foreach ($generalMainFields as $field => $fieldConfig): ?>
                  <div class="form-field-wrapper <?php echo 'field-width-' . ($fieldConfig['width'] ?? 'full'); ?>">
                      <?php $renderField($field, $fieldConfig); ?>
                  </div>
              <?php endforeach; ?>
          <?php endif; ?>
      </div>

      <!-- Advanced Tab Panel Content -->
      <?php if ($hasAdvanced): ?>
          <div id="tab-content-advanced" class="form-tab-content">
              <?php foreach ($advancedFields as $field => $fieldConfig): ?>
                  <div class="form-field-wrapper <?php echo 'field-width-' . ($fieldConfig['width'] ?? 'full'); ?>">
                      <?php $renderField($field, $fieldConfig); ?>
                  </div>
              <?php endforeach; ?>
          </div>
      <?php endif; ?>
      
      <?php if (!$usesBlockBuilder && $blockBuilderField): ?>
          <input type="hidden" name="<?php echo Str::escape($blockBuilderField); ?>" value="<?php echo Str::escape($record->{$blockBuilderField} ?? ''); ?>">
      <?php endif; ?>

      <div class="form-actions">
        <button type="submit" name="submit_action" value="save_return" class="btn-save">Save & Return</button>
        <button type="submit" name="submit_action" value="save_continue" class="btn-continue">Save & Continue</button>
        <?php if ($modelName === 'users' && $isEdit): ?>
            <?php $hasValidEmail = !empty($record->email) && filter_var($record->email, FILTER_VALIDATE_EMAIL); ?>
            <button type="button" id="send-welcome-email-btn" class="btn-luxe-outline" data-id="<?php echo Str::escape($record->id ?? ''); ?>" <?php echo !$hasValidEmail ? 'disabled' : ''; ?>>Send Welcome Email</button>
        <?php endif; ?>
      </div>
    </form>

    <p><a href="/admin/list/<?php echo Str::escape($modelName ?? ''); ?>">Back to <?php echo Str::escape($modelName ?? ''); ?></a></p>
  <?php endif; ?>

  <!-- File picker modal -->
  <div id="file-modal" class="modal">
    <div class="modal-content min-w-1/2">
      <h3>Select File</h3>
      <div id="file-list">Loading…</div>
      <p><button id="file-modal-close">Close</button></p>
    </div>
  </div>
</div>

<script src="/assets/js/admin/model_edit.js"></script>
