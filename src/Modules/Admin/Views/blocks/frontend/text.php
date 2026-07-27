<?php
// src/Modules/Admin/Views/blocks/frontend/text.php

use Zero\Support\Security;

?>
<div class="block block-text">
  <div class="block-content">
    <?php echo Security::sanitizeHtml($block['content'] ?? ''); ?>
  </div>
</div>
