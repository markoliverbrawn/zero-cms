<?php
// src/Modules/Admin/Views/blocks/admin/sub_pages.php
// Sub-Pages List Admin template layout with zero inline styles
?>
<div class="field-group">
    <label>List Title / Header</label>
    <input type="text" class="block-title-input" value="<?php echo htmlspecialchars($blockTitle, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Sub-Topic Guides">
</div>

<div class="field-group">
    <label>Introductory Description Text</label>
    <div class="editor">
        <div class="toolbar">
            <button type="button" data-cmd="bold"><strong>B</strong></button>
            <button type="button" data-cmd="italic"><em>I</em></button>
            <button type="button" data-cmd="underline"><u>U</u></button>
            <button type="button" data-cmd="insertUnorderedList">UL</button>
            <button type="button" data-cmd="insertOrderedList">OL</button>
            <button type="button" data-cmd="createLink">A</button>
            <button type="button" data-cmd="removeFormat">Clear</button>
        </div>
        <div class="editor-area block-editor-area" contenteditable="true"><?php echo $blockContent; ?></div>
    </div>
</div>

<div class="block-child-section-title">
    <span>Dynamic Sub-Pages List</span>
</div>
<p style="color: var(--text-muted, #94a3b8); font-size: 0.85rem; font-style: italic; margin-top: 5px;">This block dynamically queries and lists all published pages whose slugs start with the current page slug (e.g. <code>current-slug/*</code>) in the database. No manual page selections required.</p>
