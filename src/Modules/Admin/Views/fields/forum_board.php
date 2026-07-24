<?php
// src/Modules/Admin/Views/fields/forum_board.php

use Zero\Modules\Forum\Models\ForumBoard;
use Zero\Support\Str;

if (!empty($value)) {
    $board = ForumBoard::find($value);
    if ($board) {
        $title = Str::escape($board->title);
        $id = Str::escape($board->id);
        echo "<a href='/admin/edit/forum_boards/{$id}'>{$title}</a>";
    } else {
        echo Str::escape($value);
    }
} else {
    echo '<span class="text-muted">None</span>';
}
