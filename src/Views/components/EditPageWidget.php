<?php
/**
 * File: src/Views/components/EditPageWidget.php
 * Architectural Purpose: Render engine for the frontend "Edit This Page" shortcut widget.
 * Package: Zero\Views\Components
 * Systemic Role: Displays a floating overlay link to the admin page editor, shown only to
 * logged-in users holding an editor/super_admin role while viewing a CMS page.
 */

use Zero\Support\Str;

?>
<!-- Custom Zero-Dependency Frontend Edit-Page Shortcut -->
<div id="edit-page-widget" style="position: fixed; bottom: 20px; left: 20px; z-index: 99999999; font-family: monospace;">
    <a href="<?php echo Str::escape('/admin/edit/pages/' . $pageId); ?>" id="edit-page-widget-link" style="display: flex; align-items: center; gap: 8px; padding: 12px 18px; background: #0f172a; border: 1px solid #1e293b; border-radius: 999px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); color: #f8fafc; text-decoration: none; font-weight: bold; font-size: 0.82rem; white-space: nowrap;">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#00ffcc" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 20h9"></path>
            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
        </svg>
        <span>EDIT THIS PAGE</span>
    </a>
</div>
