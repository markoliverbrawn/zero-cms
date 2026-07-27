<?php

namespace Zero\Modules\Blog\Database\Migrations;

use Zero\Database\DB;

class AddCommentNotifiersToBlogPosts extends \Zero\Database\Migration
{
    public function up(): void
    {
        echo "Adding comment_notifiers column to blog_posts...\n";
        DB::query("ALTER TABLE blog_posts ADD COLUMN comment_notifiers TEXT NULL AFTER allow_comments;");
    }

    public function down(): void
    {
        echo "Removing comment_notifiers column from blog_posts...\n";
        DB::query("ALTER TABLE blog_posts DROP COLUMN comment_notifiers;");
    }
}
