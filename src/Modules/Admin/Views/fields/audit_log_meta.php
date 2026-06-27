<?php
// src/Modules/Admin/Views/fields/audit_log_meta.php

$meta = json_decode($value ?? '{}', true);
if (!is_array($meta)) {
    $meta = [];
}

$pills = [];

if (!empty($meta['ip_address'])) {
    $pills[] = '<span class="meta-pill" title="IP Address">IP: ' . htmlspecialchars($meta['ip_address'], ENT_QUOTES, 'UTF-8') . '</span>';
}

if (!empty($meta['scope'])) {
    $pills[] = '<span class="meta-pill" title="Trigger Scope">Scope: ' . htmlspecialchars(ucfirst($meta['scope']), ENT_QUOTES, 'UTF-8') . '</span>';
}

if (!empty($meta['user'])) {
    $pills[] = '<span class="meta-pill" title="Username">User: ' . htmlspecialchars($meta['user'], ENT_QUOTES, 'UTF-8') . '</span>';
}

if (!empty($meta['title'])) {
    $pills[] = '<span class="meta-pill" title="Item Title">"' . htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8') . '"</span>';
}

if (!empty($meta['email'])) {
    $pills[] = '<span class="meta-pill" title="Email">' . htmlspecialchars($meta['email'], ENT_QUOTES, 'UTF-8') . '</span>';
}

// Fallback: If no common fields are recognized, render a clean list of key-value pairs
if (empty($pills) && !empty($meta)) {
    foreach ($meta as $k => $v) {
        if (is_scalar($v) && $v !== '') {
            $pills[] = '<span class="meta-pill">' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . ': ' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '</span>';
        }
    }
}

if (empty($pills)): ?>
    <span class="text-muted-italic">No metadata details</span>
<?php else: ?>
    <div class="audit-log-meta-pills">
        <?php echo implode(' ', $pills); ?>
    </div>
<?php endif; ?>
