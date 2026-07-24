<?php
// src/Modules/Admin/Views/fields/forum_thread.php

use Zero\Modules\Forum\Models\ForumThread;
use Zero\Support\Str;

if (!empty($value)) {
    $thread = ForumThread::find($value);
    echo $thread ? Str::escape($thread->title) : Str::escape($value);
} else {
    echo '<span class="text-muted">None</span>';
}
