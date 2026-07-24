<?php
// src/Modules/Blog/Views/dashboard-widget.php

use Zero\Core\App;
use Zero\Support\I18n;
use Zero\Support\Str;

$renderWidgetKey = $renderWidgetKey ?? '';

if ($renderWidgetKey === 'recent_posts' && in_array('recent_posts', $enabledWidgets ?? [])):
?>
  <!-- WIDGET: RECENT POSTS -->
  <div class="dashboard-card draggable-widget" draggable="true" data-widget="recent_posts">
    <h3>
      <span class="icon-svg">
        <?php echo App::svg('edit-3'); ?>
      </span>
      <?php echo I18n::t('recent_posts'); ?>
    </h3>
    <?php if (empty($recentPosts)): ?>
      <p class="text-muted"><?php echo I18n::t('no_posts_found'); ?></p>
    <?php else: ?>
      <ul class="dashboard-list-items">
        <?php foreach ($recentPosts as $post): ?>
          <li>
            <div>
              <a href="/admin/posts/edit?id=<?php echo $post['id']; ?>" title="<?php echo Str::escape($post['title'] ?? ''); ?>">
                <?php echo Str::escape($post['title'] ?? ''); ?>
              </a>
            </div>
            <span class="text-muted">
              <?php echo Str::escape(I18n::localizeDateTime($post['created_at'], 'Y-m-d')); ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
<?php endif; ?>
