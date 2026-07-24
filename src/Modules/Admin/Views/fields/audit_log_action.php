<?php
use Zero\Support\Str;
// src/Modules/Admin/Views/fields/audit_log_action.php

$val = strtolower($value ?? '');
$class = 'badge-info'; // default blue

if (
    str_contains($val, 'fail') || 
    str_contains($val, 'error') || 
    str_contains($val, 'vulnerability') || 
    str_contains($val, 'unauthorized') || 
    str_contains($val, 'blocked') || 
    str_contains($val, 'deny') || 
    str_contains($val, 'denied')
) {
    $class = 'badge-danger'; // red
} elseif (
    str_contains($val, 'success') || 
    str_contains($val, 'pass') || 
    str_contains($val, 'approve')
) {
    $class = 'badge-success'; // green
} elseif (
    str_contains($val, 'warning') || 
    str_contains($val, 'purge') || 
    str_contains($val, 'delete')
) {
    $class = 'badge-warning'; // yellow / amber
}
?>
<span class="security-action-badge <?php echo $class; ?>">
    <?php echo Str::escape($value ?? ''); ?>
</span>
