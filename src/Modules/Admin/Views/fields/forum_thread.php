<?php
// src/Modules/Admin/Views/fields/forum_thread.php

use Zero\Modules\Forum\Models\ForumThread;

if (!empty($value)) {
    $thread = ForumThread::find($value);
    echo $thread ? htmlspecialchars($thread->title, ENT_QUOTES, 'UTF-8') : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
} else {
    echo '<span class="text-muted">None</span>';
}
