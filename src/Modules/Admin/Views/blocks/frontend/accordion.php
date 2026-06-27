<?php
// src/Views/themes/default/blocks/accordion.php

$items = $block['items'] ?? [];
?>
<div class="block-accordion-wrapper" style="margin-bottom: 50px;">
  <?php if (!empty($block['title'])): ?>
    <h3 class="accordion-section-title" style="font-size: 1.8rem; font-weight: 800; margin-top: 0; margin-bottom: 25px; text-transform: uppercase; letter-spacing: 0.1em; text-align: left;">
        <?php echo htmlspecialchars($block['title'], ENT_QUOTES, "UTF-8"); ?>
    </h3>
  <?php endif; ?>

  <div class="accordion-list" style="display: flex; flex-direction: column; border-top: 1px solid var(--border-color, #e2e8f0);">
    <?php if (!empty($items)): ?>
      <?php foreach ($items as $idx => $item): ?>
        <div class="accordion-item" style="border-bottom: 1px solid var(--border-color, #e2e8f0); overflow: hidden;">
          <!-- Accordion Header/Trigger (Uses div to prevent HTML Sanitizer scrubbing) -->
          <div class="accordion-trigger" style="width: 100%; background: none; border: none; padding: 22px 10px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; text-align: left; outline: none; font-family: inherit; box-sizing: border-box;">
            <span class="accordion-title" style="font-size: 1.05rem; font-weight: 700; color: #ffffff; letter-spacing: 0.02em; transition: color 0.2s ease;">
              <?php echo htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <span class="accordion-icon-toggle" style="width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; color: var(--accent-color, #d4af37); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19" class="accordion-line-v" style="transition: transform 0.3s ease; transform-origin: center;"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
              </svg>
            </span>
          </div>
          
          <!-- Accordion Collapsible Panel -->
          <div class="accordion-panel" style="max-height: 0; overflow: hidden; transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1); background-color: rgba(255,255,255,0.01);">
            <div class="accordion-panel-content" style="padding: 0 10px 25px 10px; font-size: 0.9rem; color: var(--text-muted, #64748b); line-height: 1.6;">
              <?php echo nl2br(htmlspecialchars($item['content'] ?? '', ENT_QUOTES, 'UTF-8')); ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p style="color: var(--text-muted); text-align: left; font-style: italic; padding: 20px 0;">No accordion items defined.</p>
    <?php endif; ?>
  </div>
</div>

<script>
(function() {
    // Unified, Bulletproof Document-level Event Delegation!
    document.addEventListener('click', function(e) {
        const trigger = e.target.closest('.accordion-trigger');
        if (!trigger) return;

        e.preventDefault();

        const item = trigger.closest('.accordion-item');
        if (!item) return;

        const panel = item.querySelector('.accordion-panel');
        const title = item.querySelector('.accordion-title');
        const lineV = item.querySelector('.accordion-line-v');

        if (!panel) return;

        const isOpen = item.classList.contains('active');

        // Collapse all other items inside the same accordion list (exclusive single toggle behavior)
        const activeItems = item.parentNode.querySelectorAll('.accordion-item.active');
        activeItems.forEach(activeItem => {
            if (activeItem !== item) {
                activeItem.classList.remove('active');
                activeItem.querySelector('.accordion-panel').style.maxHeight = '0px';
                activeItem.querySelector('.accordion-title').style.color = '#ffffff';
                const activeLineV = activeItem.querySelector('.accordion-line-v');
                if (activeLineV) activeLineV.style.transform = 'rotate(0deg)';
            }
        });

        // Toggle active state
        item.classList.toggle('active', !isOpen);

        if (!isOpen) {
            panel.style.maxHeight = panel.scrollHeight + 'px';
            if (title) title.style.color = 'var(--accent-color, #d4af37)';
            if (lineV) lineV.style.transform = 'rotate(90deg)';
        } else {
            panel.style.maxHeight = '0px';
            if (title) title.style.color = '#ffffff';
            if (lineV) lineV.style.transform = 'rotate(0deg)';
        }
    });
})();
</script>
