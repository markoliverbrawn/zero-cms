<?php
// src/Modules/Admin/Views/blocks/frontend/code.php

use Zero\Support\Security;

$language = $block['language'] ?? 'php';
$code = $block['code'] ?? '';
$title = $block['title'] ?? '';

// Highlight the raw code dynamically using our zero-dependency high-contrast tokenizer!
$highlightedCode = Security::highlightCode($code, $language);
?>
<div class="block-code-container">
    <?php if (!empty($title)): ?>
        <div class="block-code-header">
            <span><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="block-code-lang-label"><?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    <?php endif; ?>
    <pre class="block-code-pre"><code class="language-<?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $highlightedCode; ?></code></pre>
</div>
