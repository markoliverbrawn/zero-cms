<?php
// src/Modules/Admin/Views/blocks/code.php
$language = $block['language'] ?? 'php';
$code = $block['code'] ?? '';
?>
<div class="field-group">
    <label>Block Label / File Name (Optional)</label>
    <input type="text" class="block-title-input" value="<?php echo htmlspecialchars($blockTitle, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. src/Core/App.php...">
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
    <textarea class="block-code-input" placeholder="Paste or write your source code here..." rows="12"><?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?></textarea>
</div>
