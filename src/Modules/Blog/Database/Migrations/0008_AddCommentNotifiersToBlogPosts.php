<?php
/**
 * File: src/Modules/Blog/Database/Migrations/0008_AddCommentNotifiersToBlogPosts.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Blog\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Blog\Database\Migrations;

use Zero\Database\DB;

/**
 * Class AddCommentNotifiersToBlogPosts
 *
 * Provides structural platform implementation and operational encapsulation.
 */
class AddCommentNotifiersToBlogPosts extends \Zero\Database\Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Adding comment_notifiers column to blog_posts...\n";
        DB::query("ALTER TABLE blog_posts ADD COLUMN comment_notifiers TEXT NULL AFTER allow_comments;");
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Removing comment_notifiers column from blog_posts...\n";
        DB::query("ALTER TABLE blog_posts DROP COLUMN comment_notifiers;");
    }
}
