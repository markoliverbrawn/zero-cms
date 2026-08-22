<?php
// src/Modules/Admin/Views/theme-switcher.php

use Zero\Support\AssetVersion;
use Zero\Support\Str;
?>
<ul class="theme-switcher">
    <li><a href="javascript:void(0);" data-set-theme="light"></a></li>
    <li><a href="javascript:void(0);" data-set-theme="dark"></a></li>
</ul>

<script src="<?php echo Str::escape(AssetVersion::url('/assets/js/admin/theme_switcher.js')); ?>"></script>
