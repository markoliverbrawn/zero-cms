<?php
use Zero\Support\Str;
// src/Modules/Blog/Views/post.php
?>
<h2><?php echo Str::escape($post->title); ?></h2>
<div><?php echo $post->content; ?></div>
