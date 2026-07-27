<?php
use Zero\Support\Str;
// src/Modules/Admin/Views/fields/site_identity.php
?>
<div class="site-identity-cell">
    <div class="site-name-text"><?php echo Str::escape($value ?? ''); ?></div>
    <div class="site-domain-text">
        <a href="http://<?php echo Str::escape($record->domain ?? ''); ?>" target="_blank" rel="noopener noreferrer">
            <?php echo Str::escape($record->domain ?? ''); ?>
        </a>
    </div>
</div>
