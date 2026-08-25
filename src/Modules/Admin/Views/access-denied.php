<?php
use Zero\Support\Str;
// src/Modules/Admin/Views/access-denied.php
?>
<div class="access-denied-container">
    <div class="access-denied-icon-wrapper">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="72" height="72" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="access-denied-icon">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
    </div>
    <h2 class="access-denied-title">Administrative Access Denied</h2>
    <p class="access-denied-desc">
        Your active user profile is designated as <strong class="access-denied-role-strong">&ldquo;<?php echo Str::escape($currentRole); ?>&rdquo;</strong>. This resource requires the <strong class="access-denied-role-strong">&ldquo;<?php echo Str::escape($requiredPermission); ?>&rdquo;</strong> permission.
    </p>
    <div class="access-denied-footer">
        <a href="/admin/dashboard" class="access-denied-back-btn">Return to Dashboard</a>
    </div>
</div>
