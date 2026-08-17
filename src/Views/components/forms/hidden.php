<?php
// src/Views/components/forms/hidden.php

use Zero\Support\Str;
?>
<input type="hidden" name="<?= Str::escape($name) ?>" value="<?= Str::escape((string)($value ?? '')) ?>" />
