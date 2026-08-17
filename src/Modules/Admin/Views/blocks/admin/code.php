<?php
use Zero\Support\Str;
// src/Modules/Admin/Views/blocks/admin/code.php
$language = $block['language'] ?? 'php';
$code = $block['code'] ?? '';
?>
<div class="field-group">
    <label>Block Label / File Name (Rich Text, Optional)</label>
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
    <label>Programming Language</label>
    <select class="block-language-select">
        <option value="php" <?php echo $language === 'php' ? 'selected' : ''; ?>>PHP</option>
        <option value="html" <?php echo $language === 'html' ? 'selected' : ''; ?>>HTML</option>
        <option value="javascript" <?php echo $language === 'javascript' ? 'selected' : ''; ?>>JavaScript</option>
        <option value="json" <?php echo $language === 'json' ? 'selected' : ''; ?>>JSON</option>
        <option value="css" <?php echo $language === 'css' ? 'selected' : ''; ?>>CSS</option>
        <option value="sql" <?php echo $language === 'sql' ? 'selected' : ''; ?>>SQL</option>
        <option value="bash" <?php echo $language === 'bash' ? 'selected' : ''; ?>>Bash / Shell</option>
    </select>
</div>
<div class="field-group">
    <label>Raw Source Code</label>
    <textarea class="block-code-input" placeholder="Paste or write your source code here..." rows="12"><?php echo Str::escape($code); ?></textarea>
</div>
