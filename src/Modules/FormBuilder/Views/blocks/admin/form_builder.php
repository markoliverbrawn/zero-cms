<?php
// src/Modules/FormBuilder/Views/blocks/admin/form_builder.php
// Dynamic Form Builder Admin Configuration layout with list overrides and zero inline styles

use Zero\Support\Str;

$recipientEmail = $block['recipient_email'] ?? '';
$blockId = $block['id'] ?? '';
if (empty($blockId)) {
    $blockId = 'cf_' . bin2hex(random_bytes(8));
}
$fields = $block['items'] ?? []; // dynamically serialized fields list!
?>
<input type="hidden" class="block-id-input" value="<?php echo Str::escape($blockId); ?>">

<div class="field-group">
    <label>Form Title / Header (Rich Text)</label>
    <div class="editor">
        <div class="toolbar">
            <button type="button" data-cmd="bold"><strong>B</strong></button>
            <button type="button" data-cmd="italic"><em>I</em></button>
            <button type="button" data-cmd="insertSmall">Small</button>
            <button type="button" data-cmd="removeFormat">Clear</button>
        </div>
        <div class="editor-area block-editor-area block-title-rich-editor block-title-input" contenteditable="true"><?php echo $blockTitle; ?></div>
    </div>
</div>

<div class="field-group">
    <label>Form Recipient Email Address *</label>
    <input type="email" class="block-recipient_email-input" value="<?php echo Str::escape($recipientEmail); ?>" placeholder="e.g. admin@yourdomain.com" required>
</div>

<div class="field-group">
    <label>Introductory / Explanatory Text</label>
    <div class="editor">
        <div class="toolbar">
            <button type="button" data-cmd="bold"><strong>B</strong></button>
            <button type="button" data-cmd="italic"><em>I</em></button>
            <button type="button" data-cmd="underline"><u>U</u></button>
            <button type="button" data-cmd="insertUnorderedList">UL</button>
            <button type="button" data-cmd="insertOrderedList">OL</button>
            <button type="button" data-cmd="createLink">A</button>
            <button type="button" data-cmd="removeFormat">Clear</button>
        </div>
        <div class="editor-area block-editor-area" contenteditable="true"><?php echo $blockContent; ?></div>
    </div>
</div>

<div class="field-group">
    <label class="block-child-section-title">Dynamic Form Fields Builder</label>
    <div class="form-fields-items-list block-child-items-list-container">
        <?php foreach ($fields as $fieldObj): ?>
            <?php
            $fieldName = $fieldObj['name'] ?? '';
            $fieldLabel = $fieldObj['label'] ?? '';
            $fieldType = $fieldObj['type'] ?? 'text';
            $fieldRequired = $fieldObj['required'] ?? '0';
            $fieldOptions = $fieldObj['options'] ?? '';
            $fieldValidation = $fieldObj['validation'] ?? 'none';
            ?>
            <div class="form_field-item-row">
                <button type="button" class="btn-delete-form-field">Remove</button>
                <div class="block-child-fields-col">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; width: 100%;">
                        <div class="field-group block-child-field-group-8">
                            <label class="block-child-label-desc">Field Database Key *</label>
                            <input type="text" class="form_field-item-name-input" value="<?php echo Str::escape($fieldName); ?>" placeholder="e.g. first_name" required>
                        </div>
                        <div class="field-group block-child-field-group-8">
                            <label class="block-child-label-desc">Field Visual Label *</label>
                            <input type="text" class="form_field-item-label-input" value="<?php echo Str::escape($fieldLabel); ?>" placeholder="e.g. First Name" required>
                        </div>
                        <div class="field-group block-child-field-group-8">
                            <label class="block-child-label-desc">Input Type</label>
                            <select class="form_field-item-type-select">
                                <option value="text" <?php echo $fieldType === 'text' ? 'selected' : ''; ?>>Text Input</option>
                                <option value="textarea" <?php echo $fieldType === 'textarea' ? 'selected' : ''; ?>>Text Area</option>
                                <option value="email" <?php echo $fieldType === 'email' ? 'selected' : ''; ?>>Email Address</option>
                                <option value="tel" <?php echo $fieldType === 'tel' ? 'selected' : ''; ?>>Telephone (Phone)</option>
                                <option value="number" <?php echo $fieldType === 'number' ? 'selected' : ''; ?>>Number Input</option>
                                <option value="select" <?php echo $fieldType === 'select' ? 'selected' : ''; ?>>Dropdown Select</option>
                                <option value="checkbox" <?php echo $fieldType === 'checkbox' ? 'selected' : ''; ?>>Checkboxes List</option>
                                <option value="radio" <?php echo $fieldType === 'radio' ? 'selected' : ''; ?>>Radio Buttons List</option>
                            </select>
                        </div>
                        <div class="field-group block-child-field-group-8">
                            <label class="block-child-label-desc">Required Status</label>
                            <select class="form_field-item-required-select">
                                <option value="0" <?php echo $fieldRequired === '0' ? 'selected' : ''; ?>>Optional</option>
                                <option value="1" <?php echo $fieldRequired === '1' ? 'selected' : ''; ?>>Required</option>
                            </select>
                        </div>
                        <div class="field-group block-child-field-group-8">
                            <label class="block-child-label-desc">Options (Select/Check/Radio)</label>
                            <input type="text" class="form_field-item-options-input" value="<?php echo Str::escape($fieldOptions); ?>" placeholder="Option1, Option2, Option3">
                        </div>
                        <div class="field-group block-child-field-group-8">
                            <label class="block-child-label-desc">Type Validation</label>
                            <select class="form_field-item-validation-select">
                                <option value="none" <?php echo $fieldValidation === 'none' ? 'selected' : ''; ?>>No Validation</option>
                                <option value="email" <?php echo $fieldValidation === 'email' ? 'selected' : ''; ?>>Email Address</option>
                                <option value="phone" <?php echo $fieldValidation === 'phone' ? 'selected' : ''; ?>>Telephone Number</option>
                                <option value="numeric" <?php echo $fieldValidation === 'numeric' ? 'selected' : ''; ?>>Any Numeric</option>
                                <option value="integer" <?php echo $fieldValidation === 'integer' ? 'selected' : ''; ?>>Integer Only</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn-luxe-outline btn-add-form-field">Add Dynamic Field</button>
</div>
