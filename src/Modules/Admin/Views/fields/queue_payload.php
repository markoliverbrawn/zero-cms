<?php
use Zero\Support\Str;
// src/Modules/Admin/Views/fields/queue_payload.php

$decoded = json_decode($value ?? '{}', true);
if (json_last_error() === JSON_ERROR_NONE) {
    $formatted = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} else {
    $formatted = $value ?? '';
}
?>
<pre><code><?php echo Str::escape($formatted); ?></code></pre>
