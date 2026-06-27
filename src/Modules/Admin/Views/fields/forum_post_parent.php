<?php
// src/Modules/Admin/Views/fields/forum_post_parent.php

use Zero\Modules\Forum\Models\ForumPost;

if (!empty($value)) {
    $parent = ForumPost::find($value);
    if ($parent) {
        $user = $parent->getUser();
        $authorName = $user ? $user->username : 'Guest';
        $snippet = htmlspecialchars(mb_strimwidth($parent->content ?? '', 0, 40, '...'), ENT_QUOTES, 'UTF-8');
        $id = htmlspecialchars($parent->id, ENT_QUOTES, 'UTF-8');
        echo "<a href='/admin/edit/forum_posts/{$id}'>Reply to {$authorName} (\"{$snippet}\")</a>";
    } else {
        echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
} else {
    echo '<span class="text-muted">None (Root Thread Post)</span>';
}
