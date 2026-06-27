<?php
// src/Modules/Admin/Views/fields/forum_board.php

use Zero\Modules\Forum\Models\ForumBoard;

if (!empty($value)) {
    $board = ForumBoard::find($value);
    if ($board) {
        $title = htmlspecialchars($board->title, ENT_QUOTES, 'UTF-8');
        $id = htmlspecialchars($board->id, ENT_QUOTES, 'UTF-8');
        echo "<a href='/admin/edit/forum_boards/{$id}'>{$title}</a>";
    } else {
        echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
} else {
    echo '<span class="text-muted">None</span>';
}
