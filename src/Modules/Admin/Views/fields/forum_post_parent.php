<?php
// src/Modules/Admin/Views/fields/forum_post_parent.php

use Zero\Modules\Forum\Models\ForumPost;
use Zero\Support\Str;

if (!empty($value)) {
    $parent = ForumPost::find($value);
    if ($parent) {
        $user = $parent->getUser();
        $authorName = $user ? $user->username : 'Guest';
        $snippet = Str::escape(mb_strimwidth($parent->content ?? '', 0, 40, '...'));
        $id = Str::escape($parent->id);
        echo "<a href='/admin/edit/forum_posts/{$id}'>Reply to {$authorName} (\"{$snippet}\")</a>";
    } else {
        echo Str::escape($value);
    }
} else {
    echo '<span class="text-muted">None (Root Thread Post)</span>';
}
