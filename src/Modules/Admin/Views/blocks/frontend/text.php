<?php
// src/Modules/Admin/Views/blocks/frontend/text.php

use Zero\Support\Security;

?>
<div class="block block-text">
  <?php if (!empty($block['title'])): ?>
    <h3><?php echo htmlspecialchars($block['title'], ENT_QUOTES, "UTF-8"); ?></h3>
  <?php endif; ?>
  <div class="block-content">
    <?php echo Security::sanitizeHtml($block['content'] ?? ''); ?>
  </div>
</div>
