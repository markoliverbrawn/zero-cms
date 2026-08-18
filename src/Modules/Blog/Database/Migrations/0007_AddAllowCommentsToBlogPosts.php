<?php

declare(strict_types=1);

/**
 * File: src/Modules/Blog/Database/Migrations/0007_AddAllowCommentsToBlogPosts.php
 * Architectural Purpose: Modular backend controller, back-office views manager, or module bootstrapping registry hook.
 * Package: Zero\Modules\Blog\Database\Migrations
 * Systemic Role: Standardized, zero-dependency engine component supporting secure platform execution.
 */

namespace Zero\Modules\Blog\Database\Migrations;

use Zero\Database\DB;

/**
 * Class AddAllowCommentsToBlogPosts
 *
 * Adds the allow_comments flag to blog_posts, letting commenting be closed per post.
 */
class AddAllowCommentsToBlogPosts extends \Zero\Database\Migration
{
    /**
     * Runs the database transactional migrations to compile schemas.
     *
     * @return void Response output.
     */
    public function up(): void
    {
        echo "Adding allow_comments column to blog_posts...\n";
        DB::query("ALTER TABLE blog_posts ADD COLUMN allow_comments TINYINT(1) DEFAULT 1 AFTER status;");
    }

    /**
     * Reverses database schema migrations, rolling back table columns cleanly.
     *
     * @return void Response output.
     */
    public function down(): void
    {
        echo "Removing allow_comments column from blog_posts...\n";
        DB::query("ALTER TABLE blog_posts DROP COLUMN allow_comments;");
    }
}
