<?php
// src/Modules/Blog/Views/blog.php
?>
<h2>Default Blog Fallback</h2>
<ul>
<?php foreach ($posts as $p): ?>
    <li><a href="/post/<?php echo htmlspecialchars($p->slug); ?>"><?php echo htmlspecialchars($p->title); ?></a></li>
<?php endforeach; ?>
</ul>
