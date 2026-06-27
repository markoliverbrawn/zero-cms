<?php
// src/Modules/Admin/Views/fields/site_identity.php
?>
<div class="site-identity-cell">
    <div class="site-name-text"><?php echo htmlspecialchars($value ?? '', ENT_QUOTES, "UTF-8"); ?></div>
    <div class="site-domain-text">
        <a href="http://<?php echo htmlspecialchars($record->domain ?? '', ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
            <?php echo htmlspecialchars($record->domain ?? '', ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </div>
</div>
