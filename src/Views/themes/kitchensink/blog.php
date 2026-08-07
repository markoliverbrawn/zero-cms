<?php
// src/Views/themes/kitchensink/blog.php

use Zero\Core\App;
use Zero\Support\Security;
use Zero\Support\Str;
?>
<div class="post-list-container">
  <h2 style="font-size: 2.2rem; margin-bottom: 2rem; background: linear-gradient(90deg, var(--neon-cyan), var(--neon-pink)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Latest Editorial News</h2>

  <?php if (empty($pagination['data'])): ?>
    <p class="text-muted">No news articles have been published yet on this showroom.</p>
  <?php else: ?>
    <div class="post-list">
      <?php foreach ($pagination['data'] as $post): ?>
        <article class="post-row" style="display: flex; gap: 2rem; margin-bottom: 2.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 2rem; align-items: flex-start; cursor: pointer;" onclick="window.location.href='/post/<?php echo Str::escape($post->slug); ?>'">
          <?php if (!empty($post->featured_image)): ?>
            <div class="post-row-image" style="width: 150px; height: 150px; flex-shrink: 0; border: 1px solid var(--border-color); border-radius: var(--border-radius); overflow: hidden; display: flex; align-items: center; justify-content: center;">
              <img src="<?php echo Str::escape($post->featured_image); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;" />
            </div>
          <?php endif; ?>
          <div style="flex-grow: 1;">
            <h3 style="margin-top: 0; margin-bottom: 0.5rem; font-size: 1.5rem;">
              <a href="/post/<?php echo Str::escape($post->slug); ?>" style="color: var(--neon-cyan); text-decoration: none;">
                <?php echo Str::escape($post->title); ?>
              </a>
            </h3>
            <div class="post-meta" style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--neon-pink); margin-bottom: 1rem;">
              Published on: <?php echo date('M d, Y', strtotime($post->created_at)); ?>
            </div>
            <div class="post-summary" style="color: var(--text-color); margin-bottom: 1.25rem; font-size: 1.05rem; line-height: 1.6;">
              <?php echo Str::escape($post->summary ?? ''); ?>
            </div>
            <a href="/post/<?php echo Str::escape($post->slug); ?>" class="read-more-btn" style="font-weight: 700; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.05em; color: var(--neon-pink); text-decoration: none;">
              Read Full Article &rarr;
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <!-- Pagination links -->
    <?php echo App::renderPagination($pagination ?? [], '/blog', $_GET); ?>
  <?php endif; ?>
</div>
