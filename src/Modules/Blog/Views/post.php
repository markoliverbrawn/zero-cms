<?php
// src/Modules/Blog/Views/post.php
?>
<h2><?php echo htmlspecialchars($post->title); ?></h2>
<div><?php echo $post->content; ?></div>
