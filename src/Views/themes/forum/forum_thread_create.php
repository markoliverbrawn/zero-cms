<?php
// src/Views/themes/forum/forum_thread_create.php

use Zero\Core\App;
use Zero\Support\Security;
use Zero\Support\Str;

?>
<link rel="stylesheet" href="/assets/css/themes/forum/forum.css?v=1.0">

<div class="forum-container">
    <div style="margin-bottom: 1.5rem; font-size: 0.9rem;">
        <a href="/forum/board/<?php echo Str::escape($board->slug); ?>">➔ Back to Board</a>
    </div>

    <div class="form-box">
        <h2>Start a New Thread inside <span style="color: var(--accent-color, #0056b3);"><?php echo Str::escape($board->title); ?></span></h2>

        <?php if (!empty($errors)): ?>
            <div class="form-error" style="margin-bottom: 1.5rem;">
                <p style="margin-top: 0; margin-bottom: 5px; font-weight: bold;">Validation Failures:</p>
                <ul style="margin: 0; padding-left: 20px; font-size: 0.9rem; font-weight: normal;">
                    <?php foreach ($errors as $field => $msg): ?>
                        <li><?php echo Str::escape($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="/forum/board/<?php echo Str::escape($board->slug); ?>/create" class="forum-form">
            <?php echo Security::csrfInput(); ?>
            
            <div class="form-group">
                <label for="title">Thread Title</label>
                <?php echo App::makeFormField('text', 'title', [
                    'value' => $old_title ?? '',
                    'required' => true,
                    'attributes' => ['id' => 'title', 'placeholder' => 'Enter a descriptive thread title...'],
                    'showLabel' => false,
                    'guessHelperTextKey' => false,
                ])->render(); ?>
            </div>

            <div class="form-group">
                <label for="content">Original Post Content</label>
                <?php echo App::makeFormField('textarea', 'content', [
                    'value' => $old_content ?? '',
                    'required' => true,
                    'attributes' => ['id' => 'content', 'rows' => 10, 'placeholder' => 'Type your original topic content details here...'],
                    'showLabel' => false,
                    'guessHelperTextKey' => false,
                ])->render(); ?>
            </div>

            <button type="submit" class="btn-submit">Launch Thread</button>
        </form>
    </div>
</div>
