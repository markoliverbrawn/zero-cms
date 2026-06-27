<?php
// src/Modules/Admin/Views/fields/queue_error.php

$formatted = $value ?? '';
if (empty($formatted)) {
    $formatted = 'No error backtrace';
}
?>
<pre><code><?php echo htmlspecialchars($formatted, ENT_QUOTES, 'UTF-8'); ?></code></pre>
