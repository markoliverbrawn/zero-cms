<?php
// src/Modules/Admin/Views/fields/forum_user.php

use Zero\Models\User;
use Zero\Support\Str;

if (!empty($value)) {
    $user = User::find($value);
    echo $user ? Str::escape($user->username) : Str::escape($value);
} else {
    echo '<span class="text-muted">None</span>';
}
