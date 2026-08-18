<?php

declare(strict_types=1);

/**
 * File: src/Modules/Blog/Controllers/Api/CommentsController.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Blog\Controllers\Api
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Blog\Controllers\Api;

use Zero\Core\App;
use Zero\Core\Validator;
use Zero\Database\DB;
use Zero\Interfaces\Controller;
use Zero\Support\Emailer;
use Zero\Support\Security;
use Zero\Support\Str;

/**
 * Class CommentsController
 *
 * Public comment submission endpoint. Validates and rate-limits the submission, stores it as
 * pending so nothing appears unmoderated, and notifies the post's configured comment notifiers.
 */
class CommentsController implements Controller
{
    /**
     * Handles the incoming HTTP action request context and dispatches response frames.
     *
     * @param mixed $param Argument descriptor.
     * @return mixed Response output.
     */
    public function handle($param)
    {
        // Enforce POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            \http_response_code(405);
            \header('Content-Type: application/json; charset=utf-8');
            echo \json_encode(['success' => false, 'error' => 'Method Not Allowed']);
            exit;
        }

        \header('Content-Type: application/json; charset=utf-8');

        // Parse JSON payload
        $json = \json_decode(\file_get_contents('php://input'), true);
        if (!\is_array($json)) {
            \http_response_code(400);
            echo \json_encode(['success' => false, 'error' => 'Invalid JSON Payload']);
            exit;
        }

        // SPAM HONEYPOT TRAP: If the hidden bait field is filled, silently discard
        if (!empty($json['website_url'])) {
            echo \json_encode([
                'success' => true,
                'note' => 'Spam filtered successfully.'
            ]);
            exit;
        }

        $site = App::getCurrentSite();

        // DYNAMIC RATE LIMITER MIDDLEWARE: Prevent comment flood abuse (site-configurable window)
        $rateLimitSeconds = $site ? (int)$site->getModuleSetting('blog', 'comment_rate_limit_seconds', 10) : 10;
        App::applyRateLimitMiddleware('comments_submission', $rateLimitSeconds);

        // Perform validation using the Core Validator
        $rules = [
            'post_id' => 'required',
            'author_name' => 'required|min:2|max:100',
            'author_email' => 'required|email|max:100',
            'content' => 'required|min:5|max:2000'
        ];

        $validator = new Validator($json, $rules);

        if (!$validator->validate()) {
            \http_response_code(400);
            echo \json_encode([
                'success' => false,
                'errors' => $validator->getErrors()
            ]);
            exit;
        }

        $validated = $validator->getValidatedData();

        // Check if the targeted post exists under the current tenant site
        $siteId = App::getCurrentSiteId();
        $post = DB::query("SELECT id, title, allow_comments, comment_notifiers FROM blog_posts WHERE site_id = ? AND id = ? AND deleted_at IS NULL", [
            $siteId,
            $validated['post_id']
        ])->fetch();

        if (!$post) {
            \http_response_code(404);
            echo \json_encode(['success' => false, 'error' => 'Target post not found.']);
            exit;
        }

        // Verify that comments are allowed/enabled on this post
        if (isset($post['allow_comments']) && \intval($post['allow_comments']) === 0) {
            \http_response_code(403);
            echo \json_encode(['success' => false, 'error' => 'Comments are disabled for this article.']);
            exit;
        }

        // Insert new comment record
        $commentId = Security::uuidv7();
        $defaultStatus = $site ? $site->getModuleSetting('blog', 'default_comment_status', 'pending') : 'pending';

        try {
            DB::query("
                INSERT INTO blog_comments (id, site_id, post_id, author_name, author_email, content, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ", [
                $commentId,
                $siteId,
                $validated['post_id'],
                $validated['author_name'],
                $validated['author_email'],
                $validated['content'],
                $defaultStatus
            ]);

            // Dispatch notification emails if comment_notifiers is set
            $notifiersJson = $post['comment_notifiers'] ?? '';
            if (!empty($notifiersJson)) {
                $notifierIds = \json_decode($notifiersJson, true);
                if (\is_array($notifierIds) && !empty($notifierIds)) {
                    // Fetch email addresses of all selected users
                    $placeholders = \implode(',', \array_fill(0, \count($notifierIds), '?'));
                    $usersList = DB::query("SELECT email FROM users WHERE id IN ($placeholders) AND deleted_at IS NULL", $notifierIds)->fetchAll();
                    
                    if (!empty($usersList)) {
                        $subject = "New Comment on your article: " . $post['title'];
                        $htmlBody = "
                            <h2>New Comment Submitted</h2>
                            <p>A new comment has been posted on your blog article: <strong>" . Str::escape($post['title']) . "</strong>.</p>
                            <hr style='border: none; border-top: 1px solid #ddd; margin: 15px 0;'>
                            <p><strong>Author:</strong> " . Str::escape($validated['author_name']) . " (" . Str::escape($validated['author_email']) . ")</p>
                            <p><strong>Content:</strong></p>
                            <div style='background-color: #f7f9fa; border: 1px solid #e1e4e6; padding: 15px; border-radius: 6px;'>
                                " . \nl2br(Str::escape($validated['content'])) . "
                            </div>
                        ";
                        
                        foreach ($usersList as $u) {
                            if (!empty($u['email'])) {
                                Emailer::send($u['email'], $subject, $htmlBody);
                            }
                        }
                    }
                }
            }

            // Return success with formatted dates
            echo \json_encode([
                'success' => true,
                'comment' => [
                    'id' => $commentId,
                    'author_name' => Str::escape($validated['author_name']),
                    'content' => \nl2br(Str::escape($validated['content'])),
                    'created_at' => \date('F d, Y \a\t g:i A'),
                    'status' => $defaultStatus
                ]
            ]);
            exit;
        } catch (\Exception $e) {
            \http_response_code(500);
            echo \json_encode([
                'success' => false,
                'error' => 'Could not save your comment: ' . $e->getMessage()
            ]);
            exit;
        }
    }
}
