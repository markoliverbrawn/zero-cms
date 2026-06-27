<?php
// src/Modules/Admin/Views/fields/status.php

use Zero\Core\App;

$val = strtolower($value ?? '');
$icon = 'status-draft';
$class = 'status-draft';

if ($val === 'published' || $val === 'completed' || $val === 'shipped' || $val === 'approved') {
    $icon = 'status-published';
    $class = 'status-published';
} elseif ($val === 'archived' || $val === 'rejected' || $val === 'spam') {
    $icon = 'status-archived';
    $class = 'status-archived';
}
?>
<span class="status-badge <?php echo $class; ?>">
    <span class="icon-svg" title="<?php echo htmlspecialchars($val); ?>">
        <?php echo App::svg($icon); ?>
    </span>
    
</span>
