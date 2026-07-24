<?php
// src/Modules/Admin/Views/blocks/frontend/accordion.php

use Zero\Support\Security;

$items = $block['items'] ?? [];
?>
<div class="block block-accordion">
  <div class="accordion-list">
    <?php if (!empty($items)): ?>
      <?php foreach ($items as $i => $item): ?>
        <div class="accordion-item <?php echo $i === 0 ? 'active' : ''; ?>">
          <button type="button" class="accordion-trigger">
            <span class="accordion-title"><?php echo htmlspecialchars($item['title'] ?? '', ENT_QUOTES, "UTF-8"); ?></span>
            <span class="accordion-icon-wrapper">
              <span class="accordion-line-h"></span>
              <span class="accordion-line-v" style="<?php echo $i === 0 ? 'transform: rotate(90deg);' : ''; ?>"></span>
            </span>
          </button>
          <div class="accordion-panel" style="<?php echo $i === 0 ? 'max-height: none;' : 'max-height: 0px;'; ?>">
            <div class="accordion-content">
              <?php echo Security::sanitizeHtml($item['content'] ?? ''); ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p style="color: var(--text-muted); text-align: left; font-style: italic; padding: 20px 0;">No accordion items defined.</p>
    <?php endif; ?>
  </div>
</div>
