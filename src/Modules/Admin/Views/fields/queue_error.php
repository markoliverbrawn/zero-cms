<?php
use Zero\Support\Str;
// src/Modules/Admin/Views/fields/queue_error.php

$formatted = $value ?? '';
if (empty($formatted)) {
    $formatted = 'No error backtrace';
}
?>
<pre><code><?php echo Str::escape($formatted); ?></code></pre>
