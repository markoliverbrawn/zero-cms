<?php
// src/Modules/Admin/Views/fields/forum_user.php

use Zero\Models\User;

if (!empty($value)) {
    $user = User::find($value);
    echo $user ? htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') : htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
} else {
    echo '<span class="text-muted">None</span>';
}
