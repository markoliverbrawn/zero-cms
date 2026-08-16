<?php
// src/Views/components/forms/readonly_field.php

use Zero\Support\Str;
?>
<?php if ($showLabel): ?><label><?= Str::escape($label) ?></label><?php endif; ?>
<div class="readonly-field-card"><?php
    // $displayHtml arrives already-safe (either Str::escape()'d or rendered by a fields/*.php
    // listView partial that already escapes internally) -- echoed raw here to avoid double-escaping.
    echo $displayHtml;
?></div>
<input type="hidden" name="<?= Str::escape($name) ?>" value="<?= Str::escape((string)($value ?? '')) ?>">
