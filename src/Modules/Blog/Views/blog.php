<?php
use Zero\Support\Str;
// src/Modules/Blog/Views/blog.php
?>
<h2>Default Blog Fallback</h2>
<ul>
<?php foreach ($posts as $p): ?>
    <li><a href="/post/<?php echo Str::escape($p->slug); ?>"><?php echo Str::escape($p->title); ?></a></li>
<?php endforeach; ?>
</ul>
