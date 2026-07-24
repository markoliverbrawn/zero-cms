<?php
// src/Views/themes/default/blocks/latest_articles.php

use Zero\Core\App;
use Zero\Modules\Blog\Models\Post;
use Zero\Support\Str;

$limit = isset($block['limit']) ? (int)$block['limit'] : 3;
if ($limit < 1) $limit = 3;
$layout = $block['layout'] ?? 'grid';
$title = $block['title'] ?? '';

$posts = [];
if (class_exists(Post::class)) {
    $posts = Post::where(
        "status",
        "published",
        "ORDER BY created_at DESC LIMIT " . $limit
    );
}
?>
<div class="block-latest-articles-wrapper">

    <div class="latest-articles-container <?php echo $layout === 'list' ? 'layout-list' : 'layout-grid'; ?>">
        <?php if (!empty($posts)): ?>
            <?php foreach ($posts as $post): ?>
                <div class="latest-article-card" style="cursor: pointer;" onclick="window.location.href='/post/<?php echo Str::escape($post->slug); ?>'">
                    <div class="latest-article-meta">
                        <span class="latest-article-date">
                            <span class="icon-svg icon-svg-12"><?php echo App::svg('clock'); ?></span>
                            <?php echo date('M d, Y', strtotime($post->created_at)); ?>
                        </span>
                    </div>
                    <h4 class="latest-article-title">
                        <a href="/post/<?php echo Str::escape($post->slug); ?>">
                            <?php echo Str::escape($post->title); ?>
                        </a>
                    </h4>
                    <?php if (!empty($post->summary)): ?>
                        <p class="latest-article-summary"><?php echo Str::escape($post->summary); ?></p>
                    <?php endif; ?>
                    <a href="/post/<?php echo Str::escape($post->slug); ?>" class="latest-article-more">
                        <span>Read Article</span>
                        <span class="icon-svg icon-svg-12"><?php echo App::svg('chevron-right'); ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="latest-articles-empty">No articles published yet.</p>
        <?php endif; ?>
    </div>
</div>
