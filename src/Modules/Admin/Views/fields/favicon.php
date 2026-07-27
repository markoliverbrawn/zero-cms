<?php
use Zero\Support\Str;
// src/Modules/Admin/Views/fields/favicon.php

$siteDomain = $record->domain ?? '';
$faviconUrl = '';

if ($siteDomain) {
    // Check if there is an SVG icon matching this domain's theme slug
    $theme = $record->theme ?? 'default';
    if ($theme === 'default') {
        $theme = 'corporate';
    }
    
    $fullPath = APPLICATION_ROOT . '/public/assets/favicons/' . $theme . '.svg';
    if (file_exists($fullPath)) {
        $faviconUrl = '/assets/favicons/' . $theme . '.svg';
    }
}

if (empty($faviconUrl)) {
    $faviconUrl = '/assets/favicons/corporate.svg'; // Safe fallback
}
?>
<img src="<?php echo Str::escape($faviconUrl); ?>" alt="Favicon" class="admin-favicon-preview" />
