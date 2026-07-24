<?php
// src/Views/themes/shop/blog.php

use Zero\Core\App;
use Zero\Support\Str;
?>
<style>
.shop-blog-layout {
    max-width: 900px;
    margin: 0 auto;
    padding: 40px 0;
}
.blog-header {
    text-align: center;
    margin-bottom: 60px;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 40px;
}
.blog-header .prod-studio-tag {
    font-size: 0.75rem;
    letter-spacing: 0.25em;
    text-transform: uppercase;
    color: var(--accent-color);
    font-weight: 700;
}
.blog-header h1 {
    font-size: 2.8rem;
    font-weight: 800;
    letter-spacing: -0.03em;
    margin: 15px 0 10px 0;
    color: var(--text-color);
}
.blog-header p {
    color: #64748b;
    font-size: 0.95rem;
    letter-spacing: 0.05em;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.6;
}
.journal-grid {
    display: flex;
    flex-direction: column;
    gap: 40px;
}
.journal-card {
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 35px;
    background: rgba(255,255,255,0.01);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    cursor: pointer;
    position: relative;
}
.journal-card:hover {
    border-color: var(--accent-color) !important;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05) !important;
}
.journal-card-date {
    font-size: 0.75rem;
    color: var(--accent-color);
    margin-bottom: 12px;
    font-weight: bold;
    letter-spacing: 0.15em;
    text-transform: uppercase;
}
.journal-card h3 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 1.5rem;
    color: var(--text-color);
    font-weight: 800;
    letter-spacing: -0.01em;
    line-height: 1.3;
}
.journal-card h3 a {
    color: inherit;
    text-decoration: none;
}
.journal-card-preview {
    margin: 0;
    font-size: 0.95rem;
    color: #888;
    line-height: 1.7;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.journal-card-btn-container {
    margin-top: 25px;
}
.btn-luxe-link {
    display: inline-block;
    font-size: 0.8rem;
    font-weight: bold;
    color: var(--accent-color);
    text-decoration: none;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    border-bottom: 1.5px solid transparent;
    padding-bottom: 4px;
    transition: border-color 0.2s ease;
}
.journal-card:hover .btn-luxe-link {
    border-color: var(--accent-color) !important;
}
.shop-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 20px;
    margin-top: 60px;
    padding-top: 30px;
    border-top: 1px solid var(--border-color);
}
.pagination-btn {
    color: var(--accent-color);
    text-decoration: none;
    font-size: 0.82rem;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    font-weight: bold;
    border-bottom: 1.5px solid transparent;
    padding-bottom: 2px;
    transition: border-color 0.2s ease;
}
.pagination-btn:hover {
    border-color: var(--accent-color);
}
.pagination-btn-disabled {
    color: #444;
    font-size: 0.82rem;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    font-weight: bold;
    cursor: default;
}
.pagination-numbers {
    color: #64748b;
    font-size: 0.9rem;
    font-family: monospace;
}
</style>

<div class="shop-blog-layout">
  <!-- Blog Parent Page Title -->
  <header class="blog-header">
    <span class="prod-studio-tag">Studio Journal</span>
    <?php
    $hasHeroBlock = false;
    if (!empty($post->content)) {
        $blocks = json_decode($post->content, true);
        if (is_array($blocks)) {
            foreach ($blocks as $b) {
                if (($b['type'] ?? '') === 'baseline') {
                    $hasHeroBlock = true;
                    break;
                }
            }
        }
    }
    $shouldOmitTitle = !empty($post->omit_title) || $hasHeroBlock;
    if (!$shouldOmitTitle): ?>
      <h1><?php echo Str::escape($post->title ?? 'Studio Journal'); ?></h1>
    <?php endif; ?>
    <p>Curated guidelines, material studies, and creative blueprints from our design studio.</p>
  </header>

  <!-- Articles List -->
  <?php if (!empty($posts)): ?>
    <div class="journal-grid">
      <?php foreach ($posts as $p): ?>
        <article class="journal-card" onclick="window.location.href='/post/<?php echo Str::escape($p->slug); ?>'">
          <div>
            <div class="journal-card-date"><?php echo date('F j, Y', strtotime($p->created_at)); ?></div>
            <h3>
              <a href="/post/<?php echo Str::escape($p->slug); ?>">
                <?php echo Str::escape($p->title); ?>
              </a>
            </h3>
            <p class="journal-card-preview">
              <?php echo Str::escape($p->summary ?? ''); ?>
            </p>
          </div>
          <div class="journal-card-btn-container">
            <span class="btn-luxe-link">Read Article ➔</span>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <!-- Monospace Pagination Controls -->
    <?php echo App::renderPagination($pagination ?? [], '/blog', $_GET); ?>
  <?php else: ?>
    <p style="color: #64748b; font-style: italic; text-align: center;">No journal entries were found.</p>
  <?php endif; ?>
</div>
