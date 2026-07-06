<?php
// src/Views/themes/forum/forum_thread.php

use Zero\Modules\Forum\Models\ForumPost;
use Zero\Support\Security;

$replyErrorMsg = $_SESSION['reply_error_msg'] ?? null;
unset($_SESSION['reply_error_msg']);

$replyParentId = $_SESSION['reply_parent_id'] ?? null;
unset($_SESSION['reply_parent_id']);

$replyContentDraft = $_SESSION['reply_content_draft'] ?? null;
unset($_SESSION['reply_content_draft']);

$replyQuickContentDraft = $_SESSION['reply_quick_content_draft'] ?? null;
unset($_SESSION['reply_quick_content_draft']);

$replyParentAuthorName = '';
if (!empty($replyParentId)) {
    $parentPost = ForumPost::find($replyParentId);
    if ($parentPost) {
        $parentUser = $parentPost->getUser();
        $replyParentAuthorName = $parentUser ? $parentUser->username : 'Guest';
    }
}
?>
<link rel="stylesheet" href="/assets/css/themes/forum/forum.css?v=1.0">

<div class="forum-container">
    <div class="forum-breadcrumb">
        <a href="/forum">Forums</a>
        <span>/</span>
        <a href="/forum/board/<?php echo htmlspecialchars($board->slug, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($board->title, ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <span>/</span>
        <span class="active-crumb"><?php echo htmlspecialchars($thread->title, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>

    <!-- Moderation Toolbar for Admin/Staff -->
    <?php 
    $isModerator = $user && ($user->role === 'super_admin' || $user->role === 'editor');
    if ($isModerator): 
    ?>
        <div class="moderation-toolbar">
            <span>Moderation Panel:</span>
            <form method="post" action="/forum/thread/<?php echo htmlspecialchars($thread->slug, ENT_QUOTES, 'UTF-8'); ?>/moderate">
                <?php echo Security::csrfInput(); ?>
                <input type="hidden" name="action" value="pin">
                <button type="submit" class="btn-mod-action">
                    <?php echo ($thread->status === 'pinned') ? 'Unpin Thread' : 'Pin Thread'; ?>
                </button>
            </form>
            <form method="post" action="/forum/thread/<?php echo htmlspecialchars($thread->slug, ENT_QUOTES, 'UTF-8'); ?>/moderate">
                <?php echo Security::csrfInput(); ?>
                <input type="hidden" name="action" value="lock">
                <button type="submit" class="btn-mod-action">
                    <?php echo ($thread->status === 'locked') ? 'Unlock Thread' : 'Lock Thread'; ?>
                </button>
            </form>
        </div>
    <?php endif; ?>

    <div class="posts-thread-container">
        <?php foreach ($posts as $index => $post): ?>
            <?php 
            $author = $post->getUser();
            $authorName = $author ? $author->username : 'Guest';
            $authorRole = $author ? $author->role : 'member';
            
            // Assign custom classes for styling
            $roleClass = 'role-member';
            $roleLabel = 'Member';
            if ($authorRole === 'super_admin') {
                $roleClass = 'role-admin';
                $roleLabel = 'Super Admin';
            } elseif ($authorRole === 'editor') {
                $roleClass = 'role-moderator';
                $roleLabel = 'Moderator';
            }

            $isNested = !empty($post->parent_id);
            ?>
            <div class="post-card <?php echo $isNested ? 'post-nested' : ''; ?>" id="post-<?php echo $post->id; ?>">
                <div class="post-sidebar">
                    <span class="post-author"><?php echo htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="post-author-role <?php echo $roleClass; ?>"><?php echo $roleLabel; ?></span>
                    <span class="post-date"><?php echo date('M d, Y \a\t g:i A', strtotime($post->created_at ?? 'now')); ?></span>
                </div>
                <div class="post-content-area">
                    <p class="post-body"><?php echo htmlspecialchars($post->content, ENT_QUOTES, 'UTF-8'); ?></p>
                    
                    <div class="post-actions-toolbar">
                        <?php if ($thread->status !== 'locked' && !empty($user)): ?>
                            <button type="button" onclick="openReplyModal('<?php echo $post->id; ?>', '<?php echo htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8'); ?>')">
                                Reply
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($isModerator && $index > 0): // Only moderators can delete replies, and don't allow deleting original topic post here ?>
                            <form method="post" action="/forum/thread/<?php echo htmlspecialchars($thread->slug, ENT_QUOTES, 'UTF-8'); ?>/moderate" class="moderator-delete-form">
                                <?php echo Security::csrfInput(); ?>
                                <input type="hidden" name="action" value="delete_post">
                                <input type="hidden" name="post_id" value="<?php echo $post->id; ?>">
                                <button type="submit" class="btn-delete" onclick="return confirm('Are you sure you want to delete this reply?')">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Quick Reply Form (Visible if thread is unlocked and user is logged in) -->
    <div class="quick-reply-wrapper">
        <?php if ($thread->status === 'locked'): ?>
            <div class="comments-closed-box">
                This thread has been locked by a staff moderator and cannot receive any more posts.
            </div>
        <?php elseif (empty($user)): ?>
            <div class="comments-closed-box normal-font">
                You must <a href="/login" class="login-inline-link">Sign In</a> to reply to this thread.
            </div>
        <?php else: ?>
            <div class="form-box">
                <h2>Post a Quick Reply</h2>
                
                <?php if (!empty($replyErrorMsg) && empty($replyParentId)): ?>
                    <div class="form-error">
                        <?php echo htmlspecialchars($replyErrorMsg, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="/forum/thread/<?php echo htmlspecialchars($thread->slug, ENT_QUOTES, 'UTF-8'); ?>/reply" class="forum-form">
                    <?php echo Security::csrfInput(); ?>
                    <input type="hidden" name="parent_id" value="">
                    
                    <div class="editor-toolbar">
                        <span onclick="insertFormat(this.closest('.forum-form').querySelector('textarea'), 'bold')" class="btn-bold" title="Bold">B</span>
                        <span onclick="insertFormat(this.closest('.forum-form').querySelector('textarea'), 'italic')" class="btn-italic" title="Italic">I</span>
                        <span onclick="insertFormat(this.closest('.forum-form').querySelector('textarea'), 'code')" class="btn-code" title="Code Block">&lt;/&gt;</span>
                        <span class="toolbar-separator">|</span>
                        <span onclick="insertFormat(this.closest('.forum-form').querySelector('textarea'), 'quote')" class="btn-quote" title="Insert Quote">“ ” Quote</span>
                    </div>

                    <div class="form-group form-group-editor">
                        <textarea name="content" rows="6" placeholder="Write your reply here..." required><?php echo htmlspecialchars($replyQuickContentDraft ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn-submit">Submit Reply</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Nested Reply Overlay Modal (Moved inside forum-container to inherit layout styling correctly) -->
    <div class="thread-reply-overlay <?php if (!empty($replyParentId)) echo 'show-modal'; ?>" id="reply-modal" onclick="closeReplyModal(event)">
        <div class="reply-modal-content" onclick="event.stopPropagation()">
            <button class="btn-close-modal" onclick="closeReplyModal()">×</button>
            <h2 class="modal-title">Reply to <span id="reply-author-placeholder"><?php echo htmlspecialchars($replyParentAuthorName, ENT_QUOTES, 'UTF-8'); ?></span></h2>
            
            <?php if (!empty($replyErrorMsg) && !empty($replyParentId)): ?>
                <div class="form-error">
                    <?php echo htmlspecialchars($replyErrorMsg, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/forum/thread/<?php echo htmlspecialchars($thread->slug, ENT_QUOTES, 'UTF-8'); ?>/reply" class="forum-form">
                <?php echo Security::csrfInput(); ?>
                <input type="hidden" name="parent_id" id="modal-parent-id" value="<?php echo htmlspecialchars($replyParentId ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                
                <div class="editor-toolbar">
                    <span onclick="insertFormat(this.closest('.forum-form').querySelector('textarea'), 'bold')" class="btn-bold" title="Bold">B</span>
                    <span onclick="insertFormat(this.closest('.forum-form').querySelector('textarea'), 'italic')" class="btn-italic" title="Italic">I</span>
                    <span onclick="insertFormat(this.closest('.forum-form').querySelector('textarea'), 'code')" class="btn-code" title="Code Block">&lt;/&gt;</span>
                    <span class="toolbar-separator">|</span>
                    <span onclick="insertFormat(this.closest('.forum-form').querySelector('textarea'), 'quote')" class="btn-quote" title="Insert Quote">“ ” Quote</span>
                </div>

                <div class="form-group form-group-editor">
                    <textarea name="content" rows="5" placeholder="Write your nested response..." required><?php echo htmlspecialchars($replyContentDraft ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                
                <button type="submit" class="btn-submit">Post Nested Reply</button>
            </form>
        </div>
    </div>
</div>

<script>
function insertFormat(textarea, formatType) {
    if (!textarea) return;
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    const selectedText = text.substring(start, end);
    
    let replacement = '';
    switch (formatType) {
        case 'bold':
            replacement = `**${selectedText || 'bold text'}**`;
            break;
        case 'italic':
            replacement = `*${selectedText || 'italic text'}*`;
            break;
        case 'code':
            replacement = `\`${selectedText || 'code'}\``;
            break;
        case 'quote':
            replacement = `\n> ${selectedText || 'quoted text'}\n`;
            break;
    }
    
    textarea.value = text.substring(0, start) + replacement + text.substring(end);
    textarea.focus();
    textarea.selectionStart = start + replacement.length;
    textarea.selectionEnd = start + replacement.length;
}

function openReplyModal(postId, authorName) {
    document.getElementById('modal-parent-id').value = postId;
    document.getElementById('reply-author-placeholder').textContent = authorName;
    document.getElementById('reply-modal').classList.add('show-modal');
}

function closeReplyModal(e) {
    if (!e || e.target === document.getElementById('reply-modal') || e.target.className === 'btn-close-modal') {
        document.getElementById('reply-modal').classList.remove('show-modal');
    }
}
</script>
