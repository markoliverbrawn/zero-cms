<?php
// src/Modules/Blog/Views/comments.php
// Decoupled, zero-inline-style blog comments listing and ajax commenting form

use Zero\Modules\Blog\Models\Comment;
use Zero\Support\I18n;

$comments = Comment::getForPost($post->id);
?>
<section class="comments-section" style="margin-top: 4rem; border-top: 1px solid var(--border-color, #222636); padding-top: 3rem;">
  <h3 class="comments-header" style="font-size: 1.5rem; margin-bottom: 2rem; background: linear-gradient(90deg, var(--text-color), var(--neon-cyan)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
    Comments (<?php echo count($comments); ?>)
  </h3>

  <div class="comments-list" style="display: flex; flex-direction: column; gap: 1.5rem; margin-bottom: 3rem;">
    <?php if (empty($comments)): ?>
      <p class="no-comments-msg" style="color: var(--text-muted, #94a3b8); font-style: italic;">No comments yet. Be the first to share your thoughts!</p>
    <?php else: ?>
      <?php foreach ($comments as $comment): ?>
        <div class="comment-card" style="background-color: var(--card-bg, #141722); border: 1px solid var(--border-color, #222636); border-radius: 8px; padding: 1.5rem; box-shadow: 0 2px 10px rgba(0,0,0,0.15);">
          <div class="comment-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
            <strong class="comment-author" style="color: var(--neon-cyan, #06b6d4); font-size: 1rem;"><?php echo htmlspecialchars($comment->author_name, ENT_QUOTES, 'UTF-8'); ?></strong>
            <span class="comment-date" style="color: var(--text-muted, #94a3b8); font-size: 0.82rem; font-family: var(--font-mono, monospace);"><?php echo htmlspecialchars(I18n::localizeDateTime($comment->created_at, 'F d, Y \a\t g:i A'), ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <div class="comment-body" style="color: var(--text-color, #f8fafc); line-height: 1.6; font-size: 0.95rem;">
            <?php echo nl2br(htmlspecialchars($comment->content, ENT_QUOTES, 'UTF-8')); ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php if (isset($post->allow_comments) && intval($post->allow_comments) === 0): ?>
    <div class="comments-closed-box">
      Comments are closed for this article.
    </div>
  <?php else: ?>
    <div class="leave-comment-box" style="background-color: var(--card-bg, #141722); border: 1px solid var(--border-color, #222636); border-radius: 12px; padding: 2rem; box-shadow: 0 4px 20px rgba(0,0,0,0.25);">
      <h4 style="font-size: 1.25rem; margin-bottom: 1.5rem; letter-spacing: -0.01em;">Leave a Comment</h4>
      
      <form class="ajax-comment-form" style="display: flex; flex-direction: column; gap: 1.25rem;">
        <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($post->id, ENT_QUOTES, 'UTF-8'); ?>">
        
        <!-- Hardened Honeypot Decoy Field (styled with .website-field-wrapper in form_builder.css) -->
        <div class="form-group website-field-wrapper">
          <label>Website URL</label>
          <input type="text" name="website_url" autocomplete="off" tabindex="-1">
        </div>

        <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
          <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
            <label style="font-weight: 700; font-size: 0.85rem; color: var(--text-muted, #94a3b8); text-transform: uppercase;">Name *</label>
            <input type="text" name="author_name" required placeholder="Your display name" style="width: 100%; padding: 0.75rem 1rem; background-color: var(--bg-color, #0b0c10); border: 1px solid var(--border-color, #222636); border-radius: 6px; color: var(--text-color, #f8fafc); font-family: inherit; font-size: 0.95rem; box-sizing: border-box; transition: all 0.25s ease;">
            <span class="field-error author_name-error" style="color: #f43f5e; font-size: 0.82rem; display: none; font-weight: 600; margin-top: 4px;"></span>
          </div>

          <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
            <label style="font-weight: 700; font-size: 0.85rem; color: var(--text-muted, #94a3b8); text-transform: uppercase;">Email Address *</label>
            <input type="email" name="author_email" required placeholder="name@domain.com (won't be published)" style="width: 100%; padding: 0.75rem 1rem; background-color: var(--bg-color, #0b0c10); border: 1px solid var(--border-color, #222636); border-radius: 6px; color: var(--text-color, #f8fafc); font-family: inherit; font-size: 0.95rem; box-sizing: border-box; transition: all 0.25s ease;">
            <span class="field-error author_email-error" style="color: #f43f5e; font-size: 0.82rem; display: none; font-weight: 600; margin-top: 4px;"></span>
          </div>
        </div>

        <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
          <label style="font-weight: 700; font-size: 0.85rem; color: var(--text-muted, #94a3b8); text-transform: uppercase;">Comment *</label>
          <textarea name="content" required placeholder="Share your perspective..." rows="4" style="width: 100%; padding: 0.75rem 1rem; background-color: var(--bg-color, #0b0c10); border: 1px solid var(--border-color, #222636); border-radius: 6px; color: var(--text-color, #f8fafc); font-family: inherit; font-size: 0.95rem; box-sizing: border-box; transition: all 0.25s ease; resize: vertical;"></textarea>
          <span class="field-error content-error" style="color: #f43f5e; font-size: 0.82rem; display: none; font-weight: 600; margin-top: 4px;"></span>
        </div>

        <div class="comment-general-error" style="color: #f43f5e; font-weight: bold; text-align: center; display: none; background-color: rgba(244, 63, 150, 0.1); border: 1px solid #f43f5e; padding: 1rem; border-radius: 6px; font-size: 0.9rem;"></div>

        <button type="submit" class="comment-submit-btn" style="padding: 0.85rem 1.5rem; background: linear-gradient(135deg, var(--accent-color, #6366f1), var(--neon-pink, #f43f5e)); border: none; border-radius: 6px; color: #ffffff; font-weight: 700; font-size: 0.95rem; cursor: pointer; transition: all 0.25s ease; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2); outline: none;">
          Post Comment
        </button>
      </form>
    </div>
  <?php endif; ?>
</section>

<script>
(function() {
  var section = document.querySelector('.comments-section');
  if (!section) return;

  var form = section.querySelector('.ajax-comment-form');
  var commentsList = section.querySelector('.comments-list');
  var noCommentsMsg = section.querySelector('.no-comments-msg');
  var submitBtn = form.querySelector('.comment-submit-btn');
  var generalError = form.querySelector('.comment-general-error');

  // Focus border glows
  var inputs = form.querySelectorAll('input, textarea');
  inputs.forEach(function(input) {
    input.addEventListener('focus', function() {
      input.style.borderColor = 'var(--neon-cyan, #06b6d4)';
      input.style.boxShadow = '0 0 8px rgba(6, 182, 212, 0.25)';
      input.style.outline = 'none';
    });
    input.addEventListener('blur', function() {
      input.style.borderColor = 'var(--border-color, #222636)';
      input.style.boxShadow = 'none';
    });
  });

  form.addEventListener('submit', function(e) {
    e.preventDefault();

    // Clear previous errors
    var errors = form.querySelectorAll('.field-error');
    errors.forEach(function(err) {
      err.style.display = 'none';
      err.textContent = '';
    });
    generalError.style.display = 'none';
    generalError.textContent = '';

    // Disable button
    submitBtn.disabled = true;
    submitBtn.textContent = 'Posting Comment...';
    submitBtn.style.opacity = '0.7';

    // Collect data
    var formData = new FormData(form);
    var payload = {};
    formData.forEach(function(value, key){
      payload[key] = value;
    });

    // Send AJAX request
    fetch('/api/v1/blog/comments/submit', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    })
    .then(function(res) {
      return res.json().then(function(data) {
        if (!res.ok) {
          throw { status: res.status, data: data };
        }
        return data;
      });
    })
    .then(function(data) {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Post Comment';
      submitBtn.style.opacity = '1';

      if (data.success) {
        // Clear textarea & email only
        form.querySelector('textarea').value = '';
        form.querySelector('input[name="author_email"]').value = '';

        if (data.comment) {
          if (noCommentsMsg) {
            noCommentsMsg.style.display = 'none';
          }

          // Build and append new comment card
          var card = document.createElement('div');
          card.className = 'comment-card';
          card.style.cssText = 'background-color: var(--card-bg, #141722); border: 1px solid var(--border-color, #222636); border-radius: 8px; padding: 1.5rem; box-shadow: 0 2px 10px rgba(0,0,0,0.15); animation: fadeIn 0.4s ease;';
          var moderationLabel = data.comment.status === 'pending' ? ' <span class="comment-moderation-badge">(Awaiting Moderation)</span>' : '';
          card.innerHTML = `
            <div class="comment-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
              <strong class="comment-author" style="color: var(--neon-cyan, #06b6d4); font-size: 1rem;">${data.comment.author_name}${moderationLabel}</strong>
              <span class="comment-date" style="color: var(--text-muted, #94a3b8); font-size: 0.82rem; font-family: var(--font-mono, monospace);">${data.comment.created_at}</span>
            </div>
            <div class="comment-body" style="color: var(--text-color, #f8fafc); line-height: 1.6; font-size: 0.95rem;">
              ${data.comment.content}
            </div>
          `;
          commentsList.appendChild(card);
          card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      } else {
        throw { status: 400, data: data };
      }
    })
    .catch(function(err) {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Post Comment';
      submitBtn.style.opacity = '1';

      var data = err.data || {};
      if (data.errors) {
        for (var field in data.errors) {
          var errorSpan = form.querySelector('.' + field + '-error');
          if (errorSpan) {
            errorSpan.textContent = data.errors[field][0];
            errorSpan.style.display = 'block';
          }
        }
        generalError.textContent = 'Please correct the validation errors below.';
        generalError.style.display = 'block';
      } else {
        generalError.textContent = data.error || 'A server error occurred. Please try again later.';
        generalError.style.display = 'block';
      }
      generalError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  });
})();
</script>
