<?php
// src/Views/themes/kitchensink/post.php

use Zero\Core\App;
use Zero\Core\Storage\Storage;
use Zero\Core\Template;
use Zero\Database\DB;
use Zero\Models\Media;
use Zero\Modules\Blog\Models\Post;
use Zero\Support\BlockHelper;
use Zero\Support\Security;
use Zero\Support\Str;

$isBlogPost = $post instanceof Post;

$hasHeroBlock = false;
if (!empty($post->content)) {
    $blocks = json_decode($post->content, true);
    if (is_array($blocks)) {
        foreach ($blocks as $b) {
            if (($b['type'] ?? '') === 'hero') {
                $hasHeroBlock = true;
                break;
            }
        }
    }
}
$shouldOmitTitle = !empty($post->omit_title) || $hasHeroBlock;
?>
<article class="post-detail-container" style="background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: 3rem; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
  <?php if (!$shouldOmitTitle): ?>
    <h1 style="font-size: 2.5rem; margin-bottom: 0.5rem; background: linear-gradient(90deg, var(--text-color), var(--neon-cyan)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
      <?php echo Str::escape($post->title ?? ''); ?>
    </h1>
  <?php endif; ?>
  
  <?php if ($isBlogPost): ?>
    <div class="post-meta" style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--neon-pink); margin-bottom: 2rem;">
      Published: <?php echo date('F d, Y', strtotime($post->created_at)); ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($post->featured_image)): ?>
    <div class="post-featured-image-wrapper" style="margin-bottom: 2.5rem; border-radius: var(--border-radius); overflow: hidden; max-height: 400px; border: 1px solid var(--border-color); box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
      <img src="<?php echo Str::escape($post->featured_image); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover; display: block;" />
    </div>
  <?php endif; ?>

  <?php include APPLICATION_ROOT . '/src/Views/themes/kitchensink/blocks.php'; ?>

  <?php if (!$isBlogPost): ?>
    <div class="post-meta meta-bottom" style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--neon-pink); margin-top: 3rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); margin-bottom: 0;">
      Published: <?php echo date('F d, Y', strtotime($post->created_at)); ?>
    </div>
  <?php endif; ?>
</article>
