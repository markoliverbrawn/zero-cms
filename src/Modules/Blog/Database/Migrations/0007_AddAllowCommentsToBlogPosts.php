<?php

namespace Zero\Modules\Blog\Database\Migrations;

use Zero\Database\DB;

class AddAllowCommentsToBlogPosts extends \Zero\Database\Migration
{
    public function up(): void
    {
        echo "Adding allow_comments column to blog_posts...\n";
        DB::query("ALTER TABLE blog_posts ADD COLUMN allow_comments TINYINT(1) DEFAULT 1 AFTER status;");
    }

    public function down(): void
    {
        echo "Removing allow_comments column from blog_posts...\n";
        DB::query("ALTER TABLE blog_posts DROP COLUMN allow_comments;");
    }
}
