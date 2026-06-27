<!-- src/Views/themes/forum/forum_board.php -->
<link rel="stylesheet" href="/assets/css/forum.css?v=1.0">

<div class="forum-container">
    <div style="margin-bottom: 1.5rem; font-size: 0.9rem;">
        <a href="/forum">➔ Back to Forums</a>
    </div>

    <div class="forum-header-bar" style="margin-bottom: 1rem; border-bottom: none;">
        <div>
            <h1 style="font-size: 2rem;"><?php echo htmlspecialchars($board->title, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p style="margin: 5px 0 0 0; color: var(--text-muted, #6c757d); font-size: 1rem;">
                <?php echo htmlspecialchars($board->description ?? '', ENT_QUOTES, 'UTF-8'); ?>
            </p>
        </div>
        <div>
            <?php if (!empty($user)): ?>
                <button class="form-box" onclick="window.location.href='/forum/board/<?php echo htmlspecialchars($board->slug, ENT_QUOTES, 'UTF-8'); ?>/create'" style="padding: 10px 18px; background-color: var(--accent-color, #0056b3); border: none; border-radius: 6px; color: #ffffff; font-weight: 700; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: background-color 0.2s ease;">
                    + New Thread
                </button>
            <?php else: ?>
                <a href="/login" style="font-weight: bold; background-color: #f1f5f9; padding: 10px 15px; border-radius: 6px; border: 1px solid #cbd5e1;">Sign In to Post</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="threads-table-container" style="margin-top: 2rem;">
        <?php if (empty($threads)): ?>
            <p style="color: var(--text-muted, #6c757d); font-style: italic; text-align: center; padding: 4rem 0; margin: 0;">No threads have been posted in this board yet. Be the first to start a conversation!</p>
        <?php else: ?>
            <table class="threads-table">
                <thead>
                    <tr>
                        <th style="width: 55%;">Topic</th>
                        <th style="text-align: center; width: 15%;">Replies</th>
                        <th style="text-align: center; width: 15%;">Views</th>
                        <th style="text-align: right; width: 15%;">Last Post</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($threads as $thread): ?>
                        <tr>
                            <td>
                                <div class="thread-title-col">
                                    <?php if ($thread->status === 'pinned'): ?>
                                        <span class="thread-status-badge badge-pinned">Pinned</span>
                                    <?php elseif ($thread->status === 'locked'): ?>
                                        <span class="thread-status-badge badge-locked">Locked</span>
                                    <?php endif; ?>
                                    <a href="/forum/thread/<?php echo htmlspecialchars($thread->slug, ENT_QUOTES, 'UTF-8'); ?>" style="font-weight: 700; font-size: 1.05rem;">
                                        <?php echo htmlspecialchars($thread->title, ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </div>
                                <div class="meta-text" style="margin-top: 4px;">
                                    Started by <strong>
                                        <?php echo htmlspecialchars($thread->getAuthorUsername(), ENT_QUOTES, 'UTF-8'); ?>
                                    </strong>
                                </div>
                            </td>
                            <td style="text-align: center; font-weight: bold;" class="meta-text">
                                <?php echo $thread->getRepliesCount(); ?>
                            </td>
                            <td style="text-align: center;" class="meta-text">
                                <?php echo intval($thread->views_count); ?>
                            </td>
                            <td style="text-align: right;" class="meta-text">
                                <?php echo date('M d, Y', strtotime($thread->created_at ?? 'now')); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
