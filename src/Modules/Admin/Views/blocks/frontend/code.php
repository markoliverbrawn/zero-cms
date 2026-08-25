<?php
// src/Modules/Admin/Views/blocks/frontend/code.php

use Zero\Support\Security;
use Zero\Support\Str;

$language = $block['language'] ?? 'php';
$code = $block['code'] ?? '';
$title = $block['title'] ?? '';

// Highlight the raw code dynamically using our zero-dependency high-contrast tokenizer!
$highlightedCode = Str::highlightCode($code, $language);
?>
<div class="block-code-container">
    <?php if (!empty($title)): ?>
        <div class="block-code-header">
            <span><?php echo Security::sanitizeTitleHtml($title); ?></span>
            <span class="block-code-lang-label"><?php echo Str::escape($language); ?></span>
        </div>
    <?php endif; ?>
    <pre class="block-code-pre"><code class="language-<?php echo Str::escape($language); ?>"><?php echo $highlightedCode; ?></code></pre>
</div>
