<?php
// src/Views/themes/forum/forum_index.php

use Zero\Support\Security;
use Zero\Support\Str;

?>
<link rel="stylesheet" href="/assets/css/themes/forum/forum.css?v=1.0">

<div class="forum-container">
    <div class="forum-header-bar">
        <h1>Forums Community</h1>
        <div class="user-auth-badge">
            <?php if (!empty($user)): ?>
                Logged in as <span class="username-span"><?php echo Str::escape($user->username); ?></span>
                <span style="color: #cbd5e1;">|</span>
                <form method="post" action="/admin/logout" class="logout-form" style="display: inline; margin: 0;">
                    <?php echo Security::csrfInput(); ?>
                    <button type="submit" class="logout-link" style="background: none; border: none; padding: 0; font-family: inherit; font-size: inherit; color: var(--accent-color, #3b82f6); cursor: pointer; text-decoration: underline; display: inline;">Sign Out</button>
                </form>
            <?php else: ?>
                <a href="/login" style="font-weight: bold;">Sign In / Register</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="boards-grid">
        <?php if (empty($boards)): ?>
            <p style="color: var(--text-muted, #6c757d); font-style: italic; text-align: center; padding: 3rem 0;">No forum discussion boards configured for this site yet.</p>
        <?php else: ?>
            <?php foreach ($boards as $board): ?>
                <div class="board-card" onclick="window.location.href='/forum/board/<?php echo Str::escape($board->slug); ?>'" style="cursor: pointer;">
                    <h3 class="board-title">
                        <a href="/forum/board/<?php echo Str::escape($board->slug); ?>">
                            <?php echo Str::escape($board->title); ?>
                        </a>
                    </h3>
                    <p class="board-desc"><?php echo Str::escape($board->description ?? ''); ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
